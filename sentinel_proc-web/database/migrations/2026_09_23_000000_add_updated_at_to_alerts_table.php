<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The alerts table was rebuilt by 0001_01_01_000008 with only created_at,
 * but the Alert model keeps Eloquent's default timestamps. Every
 * Alert::create() / Alert::update() therefore emitted updated_at and failed
 * with "Unknown column 'alerts.updated_at'", which broke API snapshot ingest
 * and the alert acknowledge action.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('alerts') && ! Schema::hasColumn('alerts', 'updated_at')) {
            Schema::table('alerts', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('alerts') && Schema::hasColumn('alerts', 'updated_at')) {
            Schema::table('alerts', function (Blueprint $table) {
                $table->dropColumn('updated_at');
            });
        }
    }
};
