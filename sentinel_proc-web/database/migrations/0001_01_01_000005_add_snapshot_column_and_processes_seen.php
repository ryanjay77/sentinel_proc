<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitoring_snapshots', function (Blueprint $table) {
            if (! Schema::hasColumn('monitoring_snapshots', 'snapshot')) {
                $table->json('snapshot')->nullable()->after('id');
            }
        });

        if (! Schema::hasTable('processes_seen')) {
            Schema::create('processes_seen', function (Blueprint $table) {
                $table->id();
                $table->string('file_hash', 128)->unique();
                $table->string('process_name', 255)->nullable();
                $table->string('file_path', 500)->nullable();
                $table->timestamp('vt_checked_at')->nullable();
                $table->integer('vt_malicious_count')->nullable();
                $table->integer('vt_total_engines')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('processes_seen');

        Schema::table('monitoring_snapshots', function (Blueprint $table) {
            if (Schema::hasColumn('monitoring_snapshots', 'snapshot')) {
                $table->dropColumn('snapshot');
            }
        });
    }
};
