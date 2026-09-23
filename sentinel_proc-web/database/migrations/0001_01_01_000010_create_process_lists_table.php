<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded so this migration is idempotent against a database that
        // monitoring_agent/schema.sql already bootstrapped.
        if (! Schema::hasTable('process_lists')) {
            Schema::create('process_lists', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('type', 20)->index();          // 'whitelist' or 'blacklist'
                $table->string('match_by', 20);               // 'name', 'hash', 'path'
                $table->string('value', 512);                 // the actual value to match
                $table->string('process_name', 255)->nullable(); // human label
                $table->text('reason')->nullable();           // why it was added
                $table->unsignedBigInteger('added_by')->nullable(); // user id
                $table->timestamps();

                $table->unique(['type', 'match_by', 'value']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('process_lists');
    }
};
