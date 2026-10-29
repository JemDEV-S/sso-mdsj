#!/usr/bin/env bash
# Compila SsoBridgeController con JDK 8 y bytecode Java 6, compatible con el SGD.
# Produce dist/sgd-sso-bridge-java.jar. NO recompila ningun JAR original del SGD.
#
# Requiere: JDK 8 (javac/jar). Ajusta JDK8_HOME, SGD_LIB y SERVLET_API segun tu equipo.
set -e

JDK8_HOME="${JDK8_HOME:-/c/Program Files/Eclipse Adoptium/jdk-8.0.504.1-hotspot}"
SGD_LIB="${SGD_LIB:-D:/APP-SGD/sgd/WEB-INF/lib}"          # carpeta WEB-INF/lib del SGD
SERVLET_API="${SERVLET_API:-/tmp/compile-libs/servlet-api.jar}"  # solo para compilar; NO se empaqueta

JAVAC="$JDK8_HOME/bin/javac.exe"
JAR="$JDK8_HOME/bin/jar.exe"
# rt.jar = API base; jce.jar = javax.crypto (HMAC para validar el JWT HS256).
RT="$JDK8_HOME/jre/lib/rt.jar"
JCE="$JDK8_HOME/jre/lib/jce.jar"

HERE="$(cd "$(dirname "$0")" && pwd)"
SRC="$HERE/src/pe/gob/onpe/tramitedoc/web/controller/SsoBridgeController.java"
OUT="$HERE/build"
DIST="$HERE/dist"

# Classpath (formato Windows, separador ';'): todos los jars del SGD + servlet-api.
SGD_LIB_WIN="$(cygpath -w "$SGD_LIB" 2>/dev/null || echo "$SGD_LIB")"
SERVLET_WIN="$(cygpath -w "$SERVLET_API" 2>/dev/null || echo "$SERVLET_API")"
CP="$SGD_LIB_WIN\\*;$SERVLET_WIN"

# Bootclasspath (formato Windows, separador ';'): rt.jar + jce.jar.
RT_WIN="$(cygpath -w "$RT" 2>/dev/null || echo "$RT")"
JCE_WIN="$(cygpath -w "$JCE" 2>/dev/null || echo "$JCE")"
BOOTCP="$RT_WIN;$JCE_WIN"

rm -rf "$OUT" "$DIST"
mkdir -p "$OUT" "$DIST"

echo "Compilando (JDK8, -source/-target 1.6)..."
"$JAVAC" -source 1.6 -target 1.6 -bootclasspath "$BOOTCP" -encoding UTF-8 -cp "$CP" -d "$OUT" "$SRC"

echo "Empaquetando dist/sgd-sso-bridge-java.jar..."
"$JAR" cf "$DIST/sgd-sso-bridge-java.jar" -C "$OUT" .

echo "OK -> $DIST/sgd-sso-bridge-java.jar"
