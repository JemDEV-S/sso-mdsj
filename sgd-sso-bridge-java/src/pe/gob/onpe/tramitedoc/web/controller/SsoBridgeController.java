package pe.gob.onpe.tramitedoc.web.controller;

import java.io.UnsupportedEncodingException;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.util.Iterator;
import java.util.Map;
import java.util.concurrent.ConcurrentHashMap;

import javax.annotation.Resource;
import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpSession;
import javax.sql.DataSource;

import org.apache.commons.codec.binary.Base64;
import org.codehaus.jackson.JsonNode;
import org.codehaus.jackson.map.ObjectMapper;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Controller;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestMethod;
import org.springframework.web.bind.annotation.RequestParam;

import pe.gob.onpe.autentica.dao.UsuarioDao;
import pe.gob.onpe.autentica.model.DatosUsuario;
import pe.gob.onpe.autentica.model.Usuario;
import pe.gob.onpe.autentica.service.UsuarioService;
import pe.gob.onpe.libreria.util.Mensaje;
import pe.gob.onpe.tramitedoc.bean.UsuarioConfigBean;
import pe.gob.onpe.tramitedoc.service.UsuarioConfigService;

/**
 * Controlador puente SSO para el SGD — ETAPA 2.
 *
 * Expone dos entradas que construyen una HttpSession del SGD aceptada por el
 * SessionFilter (atributo "usuario" con nuDni y sessionId no vacios, mas
 * "usuarioConfig") REUTILIZANDO los servicios y DAOs existentes, y entran al
 * panel (/1.0.0/mainpanel.do) via FORWARD:
 *
 *  - PASO 1 (MOCK): GET /sso-mock-login. Identidad fija (DNI_PRUEBA), SIN
 *    autenticacion. Solo para pruebas. NO debe quedar habilitado en produccion.
 *
 *  - PASO 2 (REAL): GET /sso-callback?t=<jwt>. La identidad llega en un JWT
 *    HS256 de corta duracion y un solo uso que firma el Bridge Node tras validar
 *    el ID token del SSO. El SGD valida la firma con el secreto compartido, el
 *    exp, el aud/iss y el jti (anti-replay en memoria), extrae el DNI y crea la
 *    sesion. El DNI NUNCA se lee de un query param en claro: viaja firmado.
 *
 * Compatibilidad: escrito para Java 6 / Spring 3.2.6. Validacion HS256 a mano
 * (javax.crypto.Mac, desde Java 1.4) + Jackson (jackson-mapper-asl, ya en el
 * SGD) + commons-codec (Base64 url-safe, ya en el SGD). Sin dependencias nuevas.
 *
 * Al estar en pe.gob.onpe.tramitedoc, el component-scan existente lo detecta.
 * NO modifica LoginController, UsuarioServiceImpl, UsuarioDaoImpl, SessionFilter
 * ni MainPanelController.
 */
@Controller("ssoBridgeController")
public class SsoBridgeController {

    /**
     * DNI de prueba del endpoint MOCK. DEBE corresponder a un usuario REAL de
     * SEG_USUARIOS1 con acceso al aplicativo 9. Es una constante: el mock nunca
     * lee el DNI de la URL.
     */
    private static final String DNI_PRUEBA = "73129103";

    /** Codigo del aplicativo SGD (ver application.properties, co_aplicativo descifrado = 9). */
    private static final String COD_APLICA = "9";

    /** Version configurada del SGD; el panel vive en /{version}/mainpanel.do. */
    private static final String VERSION = "1.0.0";

    /** Codigo de respuesta de exito usado por los servicios/DAO del SGD. */
    private static final String RPTA_OK = "00";

    /** Estado "activo" en SEG_USUARIOS1.ES_USUARIO (verificado en BD: 'A'). */
    private static final String ES_USUARIO_ACTIVO = "A";

    // --- Parametros del traspaso seguro (JWT) ---

    /**
     * Secreto compartido HS256 entre el Bridge Node y el SGD. Se lee de la
     * variable de entorno SGD_SSO_SHARED_SECRET (o la propiedad de sistema del
     * mismo nombre). NUNCA se hardcodea ni se versiona. Debe ser el MISMO valor
     * configurado en el Bridge (.env: SGD_SHARED_SECRET).
     */
    private static final String SHARED_SECRET = resolveSecret();

    /** Emisor esperado del JWT (lo pone el Bridge en el claim "iss"). */
    private static final String EXPECTED_ISS = "sgd-sso-bridge";

    /** Audiencia esperada del JWT (claim "aud"): identifica al SGD como destino. */
    private static final String EXPECTED_AUD = "sgd";

    /**
     * Margen de tolerancia de reloj (segundos) al validar exp. Cubre pequenas
     * desincronizaciones entre el servidor del Bridge y el del SGD.
     */
    private static final long CLOCK_SKEW_SECONDS = 30L;

    /**
     * Anti-replay: jti ya consumidos -> epoch (segundos) de expiracion del token.
     * Garantiza el "un solo uso". En memoria: suficiente para un unico nodo
     * Payara. Si se escala a cluster, migrar a tabla/almacen compartido.
     */
    private static final Map<String, Long> USED_JTIS = new ConcurrentHashMap<String, Long>();

    private static final ObjectMapper JSON = new ObjectMapper();

    @Autowired
    private UsuarioService usuarioService;

    @Autowired
    private UsuarioDao usuarioDao;

    @Autowired
    private UsuarioConfigService usuarioConfigService;

    /**
     * DataSource de seguridad (JNDI jdbc/tdSecurity_SqlDS). Se usa solo para la
     * consulta aislada de ES_USUARIO, que la interfaz de UsuarioDao no expone.
     */
    @Resource(name = "dataSource1")
    private DataSource dataSource1;

    /**
     * PASO 1 (MOCK) — identidad fija, SIN autenticacion. Ruta de un solo segmento
     * y sin .do (ver nota sobre FORWARD y estilos en entrarAlPanel). Solo pruebas.
     */
    @RequestMapping(value = "/sso-mock-login", method = RequestMethod.GET)
    public String mockLogin(HttpServletRequest request) {
        return crearSesionYEntrar(DNI_PRUEBA, request);
    }

    /**
     * PASO 2 (REAL) — la identidad llega en un JWT HS256 firmado por el Bridge.
     * Valida firma/exp/aud/iss/jti, extrae el DNI y crea la sesion. Ruta de un
     * solo segmento y sin .do, por el mismo motivo que el mock.
     */
    @RequestMapping(value = "/sso-callback", method = RequestMethod.GET)
    public String ssoCallback(@RequestParam(value = "t", required = false) String token,
                              HttpServletRequest request) {
        if (SHARED_SECRET == null || SHARED_SECRET.length() == 0) {
            // Mala configuracion del servidor: falta el secreto compartido.
            return "redirect:/login.do?ssoError=config";
        }
        String dni;
        try {
            dni = validarTokenYObtenerDni(token);
        } catch (SsoTokenException e) {
            return "redirect:/login.do?ssoError=" + e.getCode();
        }
        return crearSesionYEntrar(dni, request);
    }

    // ------------------------------------------------------------------
    // Logica comun: a partir de un DNI ya confiable, construye la sesion.
    // ------------------------------------------------------------------

    /**
     * Pasos 1-11 compartidos por el mock y el callback real. Asume que el DNI YA
     * es de confianza (constante en el mock; extraido de un JWT valido en el
     * callback). Devuelve el nombre de vista Spring (forward o redirect a error).
     */
    private String crearSesionYEntrar(String dni, HttpServletRequest request) {
        // 1. Verificar que el usuario este activo (ES_USUARIO) en el SGD.
        if (!esUsuarioActivo(dni)) {
            return "redirect:/login.do?ssoError=inactivo";
        }

        // 2. Resolver identidad local y recuperar empleado/dependencia.
        DatosUsuario datos = usuarioService.getDepUsuario(dni);
        if (datos == null) {
            return "redirect:/login.do?ssoError=sinDatos";
        }

        Usuario usuario = new Usuario();
        usuario.setCoUsuario(dni);
        usuario.setNuDni(dni);
        usuario.setCempCodemp(datos.getCempCodemp());
        usuario.setCoDep(datos.getCoDep());
        usuario.setInAD(datos.getInAD());

        Mensaje msg = new Mensaje();

        // 3. Validar acceso al aplicativo SGD (COD_APLICA = 9).
        usuarioDao.getRptaAplicativo(msg, usuario, COD_APLICA);
        if (!RPTA_OK.equals(msg.getCoRespuesta())) {
            return "redirect:/login.do?ssoError=sinAcceso";
        }

        // 4. Cargar parametros globales del empleado (nombres, DNI, local...).
        try {
            usuarioDao.getParametrosGlobales(msg, usuario);
        } catch (Exception e) {
            return "redirect:/login.do?ssoError=parametros";
        }

        // 5. Cargar permisos internos (menu / usuarioAccesos).
        usuarioService.obtienePermisos(msg, usuario, COD_APLICA);

        // 6. Cargar la configuracion del usuario (UsuarioConfigBean).
        UsuarioConfigBean config =
                usuarioConfigService.getConfig(msg, usuario.getCempCodemp(), usuario.getCoDep());
        if (config == null) {
            return "redirect:/login.do?ssoError=sinConfig";
        }
        config.setCoUsuario(usuario.getCoUsuario());
        config.setInEsAdmin(usuario.getInAdmin());
        usuario.setCoDep(config.getCoDep());

        // 7. Crear la HttpSession del SGD y asignar sessionId.
        HttpSession session = request.getSession(true);
        usuario.setSessionId(session.getId());

        // 8-9. Guardar los atributos que el SessionFilter y el panel esperan.
        session.setAttribute("usuario", usuario);
        session.setAttribute("usuarioConfig", config);

        // 10. Registrar el acceso (igual que el login tradicional, exito = "1").
        usuarioService.registraLogAcceso(usuario, "1");

        // 11. Entrar al panel via FORWARD (no redirect), igual que el
        // LoginController real (forward:/{version}/mainpanel.do). El forward es
        // interno: la URL del navegador se queda en la ruta de entrada (un solo
        // segmento bajo /sgd/, base /sgd/), por lo que los recursos relativos del
        // panel resuelven en /sgd/resources-X/... y cargan los estilos. Un
        // redirect cambiaria la URL y romperia esas rutas (CSS 404).
        return "forward:/" + VERSION + "/mainpanel.do";
    }

    // ------------------------------------------------------------------
    // Validacion del JWT HS256 (a mano, compatible con Java 6).
    // ------------------------------------------------------------------

    /**
     * Valida un JWT compacto HS256 y devuelve el DNI del claim "dni". Lanza
     * SsoTokenException con un codigo corto para redirigir a /login.do?ssoError=.
     *
     * Comprobaciones: estructura (3 partes), alg=HS256, firma HMAC-SHA256 con el
     * secreto compartido (comparacion en tiempo constante), exp (con skew), iss,
     * aud, y jti no reutilizado (marcandolo como usado).
     */
    private String validarTokenYObtenerDni(String token) throws SsoTokenException {
        if (token == null || token.length() == 0) {
            throw new SsoTokenException("falta");
        }
        String[] parts = token.split("\\.");
        if (parts.length != 3) {
            throw new SsoTokenException("token");
        }
        String headerB64 = parts[0];
        String payloadB64 = parts[1];
        String signatureB64 = parts[2];

        // 1. Verificar la firma sobre "header.payload" ANTES de confiar en nada.
        byte[] expectedSig = hmacSha256(bytesUtf8(headerB64 + "." + payloadB64), SHARED_SECRET);
        byte[] actualSig = Base64.decodeBase64(signatureB64);
        if (!constantTimeEquals(expectedSig, actualSig)) {
            throw new SsoTokenException("firma");
        }

        // 2. A partir de aqui el contenido es integro: parsear header y payload.
        JsonNode header;
        JsonNode payload;
        try {
            header = JSON.readTree(Base64.decodeBase64(headerB64));
            payload = JSON.readTree(Base64.decodeBase64(payloadB64));
        } catch (Exception e) {
            throw new SsoTokenException("token");
        }

        // 3. Algoritmo esperado (evita 'alg: none' u otros).
        String alg = text(header, "alg");
        if (!"HS256".equals(alg)) {
            throw new SsoTokenException("alg");
        }

        // 4. Emisor y audiencia.
        if (!EXPECTED_ISS.equals(text(payload, "iss"))) {
            throw new SsoTokenException("iss");
        }
        if (!EXPECTED_AUD.equals(text(payload, "aud"))) {
            throw new SsoTokenException("aud");
        }

        // 5. Expiracion (con tolerancia de reloj).
        long now = System.currentTimeMillis() / 1000L;
        long exp = longValue(payload, "exp", -1L);
        if (exp < 0) {
            throw new SsoTokenException("exp");
        }
        if (now > exp + CLOCK_SKEW_SECONDS) {
            throw new SsoTokenException("expirado");
        }

        // 6. Anti-replay: el jti debe ser unico (un solo uso).
        String jti = text(payload, "jti");
        if (jti == null || jti.length() == 0) {
            throw new SsoTokenException("jti");
        }
        purgarJtisExpirados(now);
        // putIfAbsent devuelve != null si ya existia -> token reutilizado.
        if (USED_JTIS.putIfAbsent(jti, exp + CLOCK_SKEW_SECONDS) != null) {
            throw new SsoTokenException("replay");
        }

        // 7. DNI de la identidad.
        String dni = text(payload, "dni");
        if (dni == null || dni.trim().length() == 0) {
            throw new SsoTokenException("sinDni");
        }
        return dni.trim();
    }

    /** Elimina del set anti-replay los jti cuyo token ya expiro (limpieza O(n)). */
    private void purgarJtisExpirados(long now) {
        Iterator<Map.Entry<String, Long>> it = USED_JTIS.entrySet().iterator();
        while (it.hasNext()) {
            Map.Entry<String, Long> e = it.next();
            if (e.getValue() == null || now > e.getValue().longValue()) {
                it.remove();
            }
        }
    }

    private static byte[] hmacSha256(byte[] data, String secret) {
        try {
            Mac mac = Mac.getInstance("HmacSHA256");
            mac.init(new SecretKeySpec(bytesUtf8(secret), "HmacSHA256"));
            return mac.doFinal(data);
        } catch (Exception e) {
            // Sin HMAC no se puede validar: tratar como firma invalida.
            return new byte[0];
        }
    }

    /** Comparacion en tiempo constante para no filtrar info por timing. */
    private static boolean constantTimeEquals(byte[] a, byte[] b) {
        if (a == null || b == null || a.length == 0 || b.length == 0) {
            return false;
        }
        if (a.length != b.length) {
            return false;
        }
        int diff = 0;
        for (int i = 0; i < a.length; i++) {
            diff |= a[i] ^ b[i];
        }
        return diff == 0;
    }

    private static byte[] bytesUtf8(String s) {
        try {
            return s.getBytes("UTF-8");
        } catch (UnsupportedEncodingException e) {
            return s.getBytes();
        }
    }

    private static String text(JsonNode node, String field) {
        JsonNode v = node.get(field);
        return (v == null || v.isNull()) ? null : v.asText();
    }

    private static long longValue(JsonNode node, String field, long def) {
        JsonNode v = node.get(field);
        return (v == null || v.isNull()) ? def : v.asLong(def);
    }

    /**
     * Lee el secreto compartido de la variable de entorno o la propiedad de
     * sistema SGD_SSO_SHARED_SECRET. Devuelve null si no esta configurado.
     */
    private static String resolveSecret() {
        String s = System.getenv("SGD_SSO_SHARED_SECRET");
        if (s == null || s.length() == 0) {
            s = System.getProperty("SGD_SSO_SHARED_SECRET");
        }
        return (s == null || s.length() == 0) ? null : s;
    }

    /** Excepcion interna con codigo corto para el parametro ssoError. */
    private static final class SsoTokenException extends Exception {
        private static final long serialVersionUID = 1L;
        private final String code;
        SsoTokenException(String code) { this.code = code; }
        String getCode() { return code; }
    }

    /**
     * Consulta aislada de ES_USUARIO en IDOSGD.SEG_USUARIOS1. Preferible a
     * reutilizar getRptaIdentificacion() (que exige contrasena o AD). Se
     * considera activo cuando ES_USUARIO = "A" (verificado en la BD de pruebas).
     */
    private boolean esUsuarioActivo(String coUsuario) {
        Connection cn = null;
        PreparedStatement ps = null;
        ResultSet rs = null;
        try {
            cn = dataSource1.getConnection();
            ps = cn.prepareStatement(
                    "SELECT ES_USUARIO FROM IDOSGD.SEG_USUARIOS1 WHERE COD_USER = ?");
            ps.setString(1, coUsuario);
            rs = ps.executeQuery();
            if (rs.next()) {
                String estado = rs.getString("ES_USUARIO");
                return estado != null && ES_USUARIO_ACTIVO.equals(estado.trim());
            }
            return false;
        } catch (Exception e) {
            // Ante error de consulta, denegar por seguridad (no asumir activo).
            return false;
        } finally {
            if (rs != null) { try { rs.close(); } catch (Exception ignore) {} }
            if (ps != null) { try { ps.close(); } catch (Exception ignore) {} }
            if (cn != null) { try { cn.close(); } catch (Exception ignore) {} }
        }
    }
}
