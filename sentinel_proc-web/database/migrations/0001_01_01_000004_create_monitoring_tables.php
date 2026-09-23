<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Each table is guarded so this migration is idempotent against a
        // database that monitoring_agent/schema.sql already bootstrapped.
        if (! Schema::hasTable('monitoring_snapshots')) {
            Schema::create('monitoring_snapshots', function (Blueprint $table) {
                $table->id();
                $table->timestamp('snapshot_timestamp')->index();
                $table->integer('process_count')->nullable();
                $table->decimal('cpu_usage', 5, 2)->nullable();
                $table->decimal('memory_usage', 8, 2)->nullable();
                $table->decimal('disk_usage', 8, 2)->nullable();
                $table->json('processes')->nullable();
                $table->json('alerts')->nullable();
                $table->json('risk_score')->nullable();
                $table->string('status')->default('normal')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('processes')) {
            Schema::create('processes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('monitoring_snapshot_id')->constrained('monitoring_snapshots')->onDelete('cascade');
                $table->integer('pid')->index();
                $table->string('name', 255);
                $table->string('path', 512)->nullable();
                $table->decimal('cpu_percent', 5, 2)->nullable();
                $table->decimal('memory_mb', 10, 2)->nullable();
                $table->string('status', 50)->nullable();
                $table->string('hash', 64)->nullable()->index();
                $table->integer('first_seen')->nullable();
                $table->string('risk_level', 20)->default('low');
                $table->json('virus_total_data')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('alerts')) {
            Schema::create('alerts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('monitoring_snapshot_id')->constrained('monitoring_snapshots')->onDelete('cascade');
                $table->foreignId('process_id')->nullable()->constrained('processes')->onDelete('cascade');
                $table->string('alert_type', 100)->index();
                $table->string('severity', 20);
                $table->text('message');
                $table->json('details')->nullable();
                $table->boolean('acknowledged')->default(false)->index();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('processes');
        Schema::dropIfExists('monitoring_snapshots');
    }
};
