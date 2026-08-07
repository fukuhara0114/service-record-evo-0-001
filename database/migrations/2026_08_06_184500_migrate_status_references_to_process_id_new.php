<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $this->assertMasterIsSafe('statusmaster');
        $this->assertMasterIsSafe('statusmaster_loaner');
        $this->assertReferencesCanBeMapped('processID');

        DB::transaction(function () {
            DB::affectingStatement(
                "UPDATE servicerecord sr
                 INNER JOIN statusmaster sm ON sm.processID = sr.status
                 SET sr.status = sm.processID_new
                 WHERE COALESCE(sr.order_type, '') NOT IN ('loaner', 'waiting_list')
                   AND sr.status <> sm.processID_new",
            );

            DB::affectingStatement(
                "UPDATE servicerecord sr
                 INNER JOIN statusmaster_loaner sm ON sm.processID = sr.status
                 SET sr.status = sm.processID_new
                 WHERE sr.order_type = 'loaner'
                   AND sr.status <> sm.processID_new",
            );

            $this->assertReferencesCanBeMapped('processID_new', false);
        }, 3);

        $this->addIndexIfMissing(
            'statusmaster',
            'processID_new',
            'uq_statusmaster_process_id_new',
            true,
        );
        $this->addIndexIfMissing(
            'statusmaster_loaner',
            'processID_new',
            'uq_statusmaster_loaner_process_id_new',
            true,
        );
        $this->addIndexIfMissing(
            'servicerecord',
            'status',
            'idx_servicerecord_status',
            false,
        );
    }

    public function down(): void
    {
        $this->assertMasterIsSafe('statusmaster');
        $this->assertMasterIsSafe('statusmaster_loaner');
        $this->assertReferencesCanBeMapped('processID_new', false);

        DB::transaction(function () {
            DB::affectingStatement(
                "UPDATE servicerecord sr
                 INNER JOIN statusmaster sm ON sm.processID_new = sr.status
                 SET sr.status = sm.processID
                 WHERE COALESCE(sr.order_type, '') NOT IN ('loaner', 'waiting_list')
                   AND sr.status <> sm.processID",
            );

            DB::affectingStatement(
                "UPDATE servicerecord sr
                 INNER JOIN statusmaster_loaner sm ON sm.processID_new = sr.status
                 SET sr.status = sm.processID
                 WHERE sr.order_type = 'loaner'
                   AND sr.status <> sm.processID",
            );

            $this->assertReferencesCanBeMapped('processID');
        }, 3);

        $this->dropIndexIfPresent('servicerecord', 'idx_servicerecord_status');
        $this->dropIndexIfPresent('statusmaster_loaner', 'uq_statusmaster_loaner_process_id_new');
        $this->dropIndexIfPresent('statusmaster', 'uq_statusmaster_process_id_new');
    }

    private function assertMasterIsSafe(string $table): void
    {
        if (
            !Schema::hasTable($table)
            || !Schema::hasColumn($table, 'processID')
            || !Schema::hasColumn($table, 'processID_new')
        ) {
            throw new RuntimeException("{$table} に必要なIDカラムがありません。");
        }

        $invalid = DB::table($table)
            ->whereNull('processID')
            ->orWhereNull('processID_new')
            ->count();
        if ($invalid > 0) {
            throw new RuntimeException("{$table} にNULLのIDが {$invalid} 件あるため移行を中止しました。");
        }

        foreach (['processID', 'processID_new'] as $column) {
            $duplicates = DB::table($table)
                ->select($column)
                ->groupBy($column)
                ->havingRaw('COUNT(*) > 1')
                ->count();

            if ($duplicates > 0) {
                throw new RuntimeException(
                    "{$table}.{$column} に重複値が {$duplicates} 組あるため移行を中止しました。",
                );
            }
        }
    }

    private function assertReferencesCanBeMapped(string $masterKey, bool $acceptNewValues = true): void
    {
        $normalUnmapped = $this->countUnmapped(
            'statusmaster',
            $masterKey,
            "COALESCE(sr.order_type, '') NOT IN ('loaner', 'waiting_list')",
            $acceptNewValues,
        );
        $loanerUnmapped = $this->countUnmapped(
            'statusmaster_loaner',
            $masterKey,
            "sr.order_type = 'loaner'",
            $acceptNewValues,
        );

        if ($normalUnmapped > 0 || $loanerUnmapped > 0) {
            throw new RuntimeException(
                "未対応statusが通常 {$normalUnmapped} 件、loaner {$loanerUnmapped} 件あるため移行を中止しました。",
            );
        }
    }

    private function countUnmapped(
        string $masterTable,
        string $masterKey,
        string $recordCondition,
        bool $acceptNewValues,
    ): int {
        $acceptedKeys = $acceptNewValues && $masterKey !== 'processID_new'
            ? ["sm.{$masterKey} = sr.status", 'sm.processID_new = sr.status']
            : ["sm.{$masterKey} = sr.status"];
        $mappingCondition = implode(' OR ', $acceptedKeys);

        $row = DB::selectOne(
            "SELECT COUNT(*) AS aggregate
             FROM servicerecord sr
             WHERE {$recordCondition}
               AND sr.status IS NOT NULL
               AND NOT EXISTS (
                   SELECT 1
                   FROM {$masterTable} sm
                   WHERE {$mappingCondition}
               )",
        );

        return (int) ($row->aggregate ?? 0);
    }

    private function addIndexIfMissing(
        string $table,
        string $column,
        string $name,
        bool $unique,
    ): void {
        $existing = DB::table('information_schema.statistics')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $table)
            ->where('seq_in_index', 1)
            ->where('column_name', $column)
            ->when($unique, fn ($query) => $query->where('non_unique', 0))
            ->exists();

        if ($existing) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $name, $unique) {
            if ($unique) {
                $blueprint->unique($column, $name);
            } else {
                $blueprint->index($column, $name);
            }
        });
    }

    private function dropIndexIfPresent(string $table, string $name): void
    {
        $exists = DB::table('information_schema.statistics')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $table)
            ->where('index_name', $name)
            ->exists();

        if (!$exists) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropIndex($name);
        });
    }
};
