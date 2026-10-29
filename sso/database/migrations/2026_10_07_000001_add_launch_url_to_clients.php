<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // URL donde arranca el flujo de login del sistema (NO el redirect_uri,
            // que es el callback OIDC). El portal de /home enlaza aquí. Nullable:
            // un cliente sin launch_url simplemente no aparece como tarjeta.
            $table->text('launch_url')->nullable()->after('name');
            // Texto opcional para describir el sistema en el portal.
            $table->string('description')->nullable()->after('launch_url');
        });

        // Poblar la URL de inicio del único cliente existente (el Bridge del SGD).
        DB::table('clients')->where('code', 'sgd-bridge')->update([
            'launch_url' => 'http://127.0.0.1:8095/bridge/login',
            'description' => 'Sistema de Gestión Documental',
        ]);
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['launch_url', 'description']);
        });
    }
};
