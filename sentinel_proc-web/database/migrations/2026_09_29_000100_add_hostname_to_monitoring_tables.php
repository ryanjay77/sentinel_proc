<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Feature 1: which laptop produced each scan. Nullable + indexed on all
// three monitoring tables; existing rows stay untouched (hostname = NULL).
return new class extends Migration
{
    private const TABLES = ['monitoring_snapshots', 'processes', 'alerts'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'hostname')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('hostname')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'hostname')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['hostname']);
                $table->dropColumn('hostname');
            });
        }
    }
};
