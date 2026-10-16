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
        Schema::table('ticket_incidents', function (Blueprint $table) {
            $table->enum('resolution', [
                'provider_issue',
                'no_issue',
            ])->nullable()->after('resolved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_incidents', function (Blueprint $table) {
            $table->dropColumn('resolution');
        });
    }
};
