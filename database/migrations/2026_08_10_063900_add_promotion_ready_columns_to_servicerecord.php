<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Large existing tables must not be altered inside a transaction.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasTable('servicerecord')) {
            return;
        }

        Schema::table('servicerecord', function (Blueprint $table) {
            if (!Schema::hasColumn('servicerecord', 'promotion_ready_at')) {
                $table->dateTime('promotion_ready_at')->nullable()->after('loanerID');
            }
            if (!Schema::hasColumn('servicerecord', 'promotion_source_orderID')) {
                $table->unsignedInteger('promotion_source_orderID')->nullable()->after('promotion_ready_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('servicerecord')) {
            return;
        }

        Schema::table('servicerecord', function (Blueprint $table) {
            if (Schema::hasColumn('servicerecord', 'promotion_source_orderID')) {
                $table->dropColumn('promotion_source_orderID');
            }
            if (Schema::hasColumn('servicerecord', 'promotion_ready_at')) {
                $table->dropColumn('promotion_ready_at');
            }
        });
    }
};
