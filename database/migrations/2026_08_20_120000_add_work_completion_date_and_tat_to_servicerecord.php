<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('servicerecord')) {
            return;
        }

        Schema::table('servicerecord', function (Blueprint $table) {
            if (! Schema::hasColumn('servicerecord', 'work_completion_date')) {
                $table->date('work_completion_date')->nullable()->after('orderDate');
            }
            if (! Schema::hasColumn('servicerecord', 'tat')) {
                $table->integer('tat')->nullable()->after('work_completion_date');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('servicerecord')) {
            return;
        }

        Schema::table('servicerecord', function (Blueprint $table) {
            if (Schema::hasColumn('servicerecord', 'tat')) {
                $table->dropColumn('tat');
            }
            if (Schema::hasColumn('servicerecord', 'work_completion_date')) {
                $table->dropColumn('work_completion_date');
            }
        });
    }
};
