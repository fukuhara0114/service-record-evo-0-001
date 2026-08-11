<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * 貸出メインフロー用 status（既存と衝突しない processID_new）。
     *
     * 0:在庫有り → 100:見積済み → 150:受注 → 350:発送依頼(既存) → 400:貸出中
     * → 450:返却 → 650:チェック → 0:在庫有り
     */
    private array $rows = [
        ['processID_new' => 100, 'status' => '見積済み'],
        ['processID_new' => 150, 'status' => '受注'],
        ['processID_new' => 450, 'status' => '返却'],
        ['processID_new' => 650, 'status' => 'チェック'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('statusmaster_loaner')) {
            return;
        }

        $columns = Schema::getColumnListing('statusmaster_loaner');
        $hasProcessId = in_array('processID', $columns, true);
        $hasStatusNew = in_array('status_new', $columns, true);

        foreach ($this->rows as $row) {
            $exists = DB::table('statusmaster_loaner')
                ->where('processID_new', $row['processID_new'])
                ->exists();
            if ($exists) {
                continue;
            }

            $payload = [
                'processID_new' => $row['processID_new'],
                'status' => $row['status'],
            ];
            if ($hasProcessId) {
                // 旧 processID との衝突を避ける
                $payload['processID'] = 9000 + (int) $row['processID_new'];
            }
            if ($hasStatusNew) {
                $payload['status_new'] = $row['status'];
            }

            DB::table('statusmaster_loaner')->insert($payload);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('statusmaster_loaner')) {
            return;
        }

        DB::table('statusmaster_loaner')
            ->whereIn('processID_new', collect($this->rows)->pluck('processID_new'))
            ->delete();
    }
};
