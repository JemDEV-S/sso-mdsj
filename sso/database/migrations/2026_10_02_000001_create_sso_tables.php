<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_id')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('client_secret_hash');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('client_redirect_uris', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->text('redirect_uri');
        });
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained();
            $table->string('session_identifier', 64)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('authenticated_with')->default('password');
            $table->timestamp('authenticated_at');
            $table->timestamp('last_activity_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
        });
        Schema::create('auth_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code_hash', 64)->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('client_id')->constrained();
            $table->foreignUuid('user_session_id')->constrained('user_sessions');
            $table->text('redirect_uri');
            $table->string('scope');
            $table->string('nonce')->nullable();
            $table->string('code_challenge', 43)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->foreignId('client_id')->nullable()->constrained();
            $table->string('event');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'auth_codes', 'user_sessions', 'client_redirect_uris', 'clients'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
