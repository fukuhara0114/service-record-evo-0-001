<?php

namespace App\Http\Controllers;

use App\Models\LoanerMaster;
use App\Models\StatusLoaner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class LoanerMasterController extends Controller
{
    public function index(Request $request)
    {
        $table = (new LoanerMaster)->getTable();
        $columns = Schema::getColumnListing($table);
        $statusColumn = $this->resolveStatusColumn();
        $statusLabels = $this->buildStatusLabelMap();
        $sort = (string) $request->query('sort', 'item');
        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $scope = $this->normalizeScope((string) $request->query('scope', 'all'));
        $search = trim((string) $request->query('q', ''));

        if (!in_array($sort, $columns, true)) {
            $sort = 'item';
        }

        $query = LoanerMaster::query()
            ->whereIn('id', $this->latestVersionIdQuery($table))
            ->where(function ($builder) {
                $builder
                    ->whereNull('item')
                    ->orWhere(function ($inner) {
                        $inner
                            ->where('item', 'not like', '%【使用不可】%')
                            ->where('item', 'not like', '%【サービス終了】%');
                    });
            });

        $this->applyStatusScope($query, $statusColumn, $scope);
        $this->applySearch($query, $columns, $search);

        if ($sort === 'item') {
            // MySQL 5.7/8 共通: 【使用不可】【サービス終了】を除いてソート（REGEXP_REPLACE 非使用）
            $query
                ->orderByRaw(
                    "TRIM(REPLACE(REPLACE(COALESCE(item, ''), '【使用不可】', ''), '【サービス終了】', '')) {$direction}"
                )
                ->orderBy('item', $direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        $masters = $query
            ->orderBy('loanerID')
            ->orderByDesc('id')
            ->paginate(100)
            ->withQueryString()
            ->through(fn (LoanerMaster $row) => $this->serializeRow($row, $columns, $statusColumn, $statusLabels));

        return Inertia::render('LoanerMasterList', [
            'columns' => $columns,
            'masters' => $masters,
            'statusColumn' => $statusColumn,
            'statusOptions' => $this->statusOptions($statusLabels),
            'sort' => $sort,
            'direction' => $direction,
            'scope' => $scope,
            'q' => $search,
        ]);
    }

    public function updateCurrentStatus(Request $request, int $id)
    {
        $row = LoanerMaster::query()->findOrFail($id);
        if ($row->loanerID === null || $row->loanerID === '') {
            return back()->withErrors(['currentStatus' => 'loanerID が無い行は一括更新できません。']);
        }

        $validated = $request->validate([
            'currentStatus' => 'nullable',
        ]);

        LoanerMaster::unifyCurrentStatus($row->loanerID, $validated['currentStatus'] ?? null);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => '同じ loanerID の currentStatus を更新しました。',
                'loanerID' => $row->loanerID,
                'currentStatus' => $validated['currentStatus'] ?? null,
            ]);
        }

        return back();
    }

    /**
     * @return array<string, string>
     */
    private function buildStatusLabelMap(): array
    {
        $select = StatusLoaner::selectColumnsForDisplay();
        if (Schema::hasColumn('statusmaster_loaner', 'processID')) {
            $select[] = 'processID';
        }

        $map = [];
        foreach (StatusLoaner::query()->get(array_values(array_unique($select))) as $row) {
            // 画面表記は statusmaster_loaner.status_new（無ければ status）
            $label = trim((string) (StatusLoaner::resolveLabel($row) ?? ''));
            if ($label === '') {
                continue;
            }

            $map[(string) $row->processID_new] = $label;
            if ($row->processID !== null && $row->processID !== '') {
                $map[(string) $row->processID] = $label;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $statusLabels
     * @return array<int, array{id: string, label: string}>
     */
    private function statusOptions(array $statusLabels): array
    {
        $options = [];
        foreach ($statusLabels as $id => $label) {
            $options[] = [
                'id' => (string) $id,
                'label' => $label,
            ];
        }

        usort($options, fn ($a, $b) => ((int) $a['id']) <=> ((int) $b['id']));

        return array_values(array_unique($options, SORT_REGULAR));
    }

    private function resolveStatusColumn(): string
    {
        static $column = null;

        if ($column !== null) {
            return $column;
        }

        $schema = Schema::getColumnListing((new LoanerMaster)->getTable());

        if (in_array('currentStatus', $schema, true)) {
            return $column = 'currentStatus';
        }

        if (in_array('current_status', $schema, true)) {
            return $column = 'current_status';
        }

        return $column = 'currentStatus';
    }

    /**
     * loanerID ごとに最新版（validDateMin → id）の id を返すサブクエリ。
     * MySQL 5.7 互換（ROW_NUMBER ウィンドウ関数は使わない）。
     */
    private function latestVersionIdQuery(string $table)
    {
        return function ($sub) use ($table) {
            $sub->from("{$table} as lm")
                ->select('lm.id')
                ->whereRaw(
                    "lm.id = (
                        SELECT t2.id
                        FROM `{$table}` AS t2
                        WHERE IFNULL(NULLIF(t2.loanerID, ''), CONCAT('id:', t2.id))
                            = IFNULL(NULLIF(lm.loanerID, ''), CONCAT('id:', lm.id))
                        ORDER BY t2.validDateMin DESC, t2.id DESC
                        LIMIT 1
                    )"
                );
        };
    }

    private function normalizeScope(string $scope): string
    {
        $allowed = ['all', 'stock', 'non_stock', 'unregistered', 'reserved', 'lending', 'returning', 'other'];

        return in_array($scope, $allowed, true) ? $scope : 'all';
    }

    /**
     * currentStatus（statusmaster_loaner.processID_new）で絞り込み。
     */
    private function applyStatusScope($query, string $statusColumn, string $scope): void
    {
        if ($scope === 'all' || $statusColumn === '') {
            return;
        }

        $statusExpr = 'CAST('.$statusColumn.' AS SIGNED)';
        $associatedExpr = Schema::hasColumn((new LoanerMaster)->getTable(), 'associatedID')
            ? 'CAST(COALESCE(associatedID, 0) AS SIGNED)'
            : null;

        match ($scope) {
            'stock' => $query->whereRaw("{$statusExpr} = 0"),
            'non_stock' => $query->whereRaw("NOT ({$statusExpr} <=> 0)"),
            'unregistered' => $query
                ->whereRaw("{$statusExpr} > 0 AND {$statusExpr} < 388")
                ->when($associatedExpr, fn ($q) => $q->whereRaw("{$associatedExpr} = 0")),
            'reserved' => $query
                ->whereRaw("{$statusExpr} > 0 AND {$statusExpr} < 388")
                ->when($associatedExpr, fn ($q) => $q->whereRaw("{$associatedExpr} > 0")),
            'lending' => $query->whereRaw("{$statusExpr} = 388"),
            'returning' => $query->whereRaw("{$statusExpr} > 388 AND {$statusExpr} < 400"),
            'other' => $query->whereRaw("{$statusExpr} > 400"),
            default => null,
        };
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function applySearch($query, array $columns, string $search): void
    {
        if ($search === '') {
            return;
        }

        $targets = array_values(array_intersect(
            ['loanerID', 'item', 'productName', 'SN', 'manageNum', 'inventory', 'note1', 'note2'],
            $columns,
        ));

        if ($targets === []) {
            return;
        }

        $like = '%'.$search.'%';
        $query->where(function ($builder) use ($targets, $like) {
            foreach ($targets as $column) {
                $builder->orWhere($column, 'like', $like);
            }
        });
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<string, string>  $statusLabels
     * @return array<string, mixed>
     */
    private function serializeRow(
        LoanerMaster $row,
        array $columns,
        string $statusColumn,
        array $statusLabels,
    ): array {
        $out = [];

        foreach ($columns as $column) {
            $value = $row->getAttribute($column);

            if ($column === $statusColumn) {
                $out[$column] = $value;
                continue;
            }

            if ($value instanceof \DateTimeInterface) {
                $out[$column] = $value->format(
                    $column === 'lastEditDate' ? 'Y-m-d H:i:s' : 'Y-m-d',
                );
            } elseif (is_bool($value)) {
                $out[$column] = $value ? '1' : '0';
            } else {
                $out[$column] = $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $statusLabels
     */
    private function formatStatusCell(mixed $value, array $statusLabels): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $key = (string) $value;
        $label = $statusLabels[$key] ?? null;
        if ($label === null || $label === '') {
            return $value;
        }

        return $label;
    }
}
