<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The alerts table was originally created by schema.sql (Python side)
 * with a different column set than Laravel's Alert model expects.
 * This migration drops it and rebuilds it with the correct schema,
 * and also adds missing columns to monitoring_snapshots.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Fix monitoring_snapshots ──────────────────────────────────
        Schema::table('monitoring_snapshots', function (Blueprint $table) {
            if (! Schema::hasColumn('monitoring_snapshots', 'snapshot_timestamp')) {
                $table->timestamp('snapshot_timestamp')->nullable()->after('snapshot');
            }
            if (! Schema::hasColumn('monitoring_snapshots', 'process_count')) {
                $table->integer('process_count')->nullable()->after('snapshot_timestamp');
            }
            if (! Schema::hasColumn('monitoring_snapshots', 'status')) {
                $table->string('status', 20)->default('normal')->after('process_count');
            }
        });

        // ── Rebuild alerts with correct Laravel schema ────────────────
        // Drop the old Python-schema alerts table (no FK deps on it yet).
        Schema::dropIfExists('alerts');

        Schema::create('alerts', function (Blueprint $table) {
            $table->bigIncrements('id');
            // monitoring_snapshots.id is int(11) signed — no FK, just store the reference
            $table->integer('monitoring_snapshot_id')->index();
            // processes.id is bigint unsigned
            $table->unsignedBigInteger('process_id')->nullable()->index();
            $table->string('alert_type', 100)->index();
            $table->string('severity', 20);
            $table->text('message');
            $table->json('details')->nullable();
            $table->boolean('acknowledged')->default(false)->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');

        Schema::table('monitoring_snapshots', function (Blueprint $table) {
            foreach (['status', 'process_count', 'snapshot_timestamp'] as $col) {
                if (Schema::hasColumn('monitoring_snapshots', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
