<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('processes')) {
            Schema::create('processes', function (Blueprint $table) {
                $table->bigIncrements('id');
                // monitoring_snapshots.id is int(11) — match with unsignedInteger
                $table->unsignedInteger('monitoring_snapshot_id')->index();
                $table->foreign('monitoring_snapshot_id')
                      ->references('id')->on('monitoring_snapshots')
                      ->onDelete('cascade');
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
                $table->bigIncrements('id');
                // monitoring_snapshots.id is int(11) — match with unsignedInteger
                $table->unsignedInteger('monitoring_snapshot_id')->index();
                $table->foreign('monitoring_snapshot_id')
                      ->references('id')->on('monitoring_snapshots')
                      ->onDelete('cascade');
                // processes.id is bigint — use unsignedBigInteger
                $table->unsignedBigInteger('process_id')->nullable()->index();
                $table->foreign('process_id')
                      ->references('id')->on('processes')
                      ->onDelete('cascade');
                $table->string('alert_type', 100)->index();
                $table->string('severity', 20);
                $table->text('message');
                $table->json('details')->nullable();
                $table->boolean('acknowledged')->default(false)->index();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('processes');
    }
};
