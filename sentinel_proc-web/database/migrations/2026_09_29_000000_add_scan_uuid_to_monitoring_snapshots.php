<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('monitoring_snapshots', 'scan_uuid')) {
            Schema::table('monitoring_snapshots', function (Blueprint $table) {
                // Lets the API re-POST a snapshot after a network failure without
                // storing it twice. Nullable + unique: MySQL allows many NULLs,
                // so locally-written snapshots are unaffected.
                $table->uuid('scan_uuid')->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('monitoring_snapshots', 'scan_uuid')) {
            Schema::table('monitoring_snapshots', function (Blueprint $table) {
                $table->dropUnique(['scan_uuid']);
                $table->dropColumn('scan_uuid');
            });
        }
    }
};
