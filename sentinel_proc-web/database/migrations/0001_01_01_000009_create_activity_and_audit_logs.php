<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Process activity log — written by the Python agent each scan
        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('monitoring_snapshot_id')->nullable()->index();
                $table->string('process_name', 255)->index();
                $table->integer('pid')->nullable();
                $table->string('event_type', 50)->index(); // detected|risk_change|first_seen|vt_flagged
                $table->string('risk_level', 20)->nullable();
                $table->integer('risk_score')->nullable();
                $table->string('path', 512)->nullable();
                $table->string('hash', 64)->nullable();
                $table->json('details')->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }

        // System audit log — written by Laravel when users perform actions
        if (! Schema::hasTable('system_audit_logs')) {
            Schema::create('system_audit_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_name', 255)->nullable(); // denormalised for history
                $table->string('action', 100)->index();       // login|logout|acknowledge_alert|etc.
                $table->string('target_type', 100)->nullable(); // Alert|User|Report|etc.
                $table->string('target_id', 50)->nullable();
                $table->string('target_label', 255)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_audit_logs');
        Schema::dropIfExists('activity_logs');
    }
};
