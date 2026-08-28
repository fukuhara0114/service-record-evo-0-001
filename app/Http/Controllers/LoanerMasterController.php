<?php

namespace App\Http\Controllers;

use App\Models\LoanerMaster;
use App\Models\ServiceRecord;
use App\Models\StatusLoaner;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $allowedSorts = $columns;
        if ($scope === 'lending') {
            $allowedSorts[] = 'lending_parent_status';
        }
        if (!in_array($sort, $allowedSorts, true)) {
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

        if ($sort === 'lending_parent_status') {
            $this->applyLendingParentStatusSort($query, $table, $direction);
        } elseif ($sort === 'item') {
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
            ->withQueryString();

        $parentById = $scope === 'lending'
            ? $this->loadParentsByAssociatedId($masters->getCollection())
            : collect();

        $masters->setCollection(
            $masters->getCollection()->map(
                fn (LoanerMaster $row) => $this->serializeRow(
                    $row,
                    $columns,
                    $statusColumn,
                    $statusLabels,
                    $parentById,
                    $scope === 'lending',
                )
            )
        );

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
            // 画面表記は statusmaster_loaner.status_new のみ
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
        $allowed = ['all', 'stock', 'non_stock', 'reserved', 'lending', 'returning', 'other'];

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

        match ($scope) {
            'stock' => $query->whereRaw("{$statusExpr} = 0"),
            'non_stock' => $query->whereRaw("{$statusExpr} != 0"),
            'reserved' => $query->whereRaw("{$statusExpr} >= 20 AND {$statusExpr} < 388"),
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
     * 貸出中の「親案件状況」表示文字列順に近いソート。
     * グループ: — → 作業中 → 出荷完了後xx日経過（xx は日数）
     */
    private function applyLendingParentStatusSort($query, string $table, string $direction): void
    {
        $dir = $direction === 'desc' ? 'DESC' : 'ASC';
        $today = Carbon::now('Asia/Tokyo')->toDateString();
        $statusExpr = "(SELECT status FROM servicerecord WHERE orderID = CAST(`{$table}`.associatedID AS SIGNED) LIMIT 1)";
        $shipExpr = "(SELECT shippingOut_requiredDate FROM servicerecord WHERE orderID = CAST(`{$table}`.associatedID AS SIGNED) LIMIT 1)";

        // 表示文字列の並び: — / 作業中 / 出荷完了後…
        $groupExpr = "CASE
            WHEN {$statusExpr} IS NULL OR {$statusExpr} = '' THEN 0
            WHEN CAST({$statusExpr} AS SIGNED) < 400 THEN 1
            WHEN {$shipExpr} IS NULL OR {$shipExpr} = '' THEN 0
            ELSE 2
        END";

        $daysExpr = "CASE
            WHEN CAST({$statusExpr} AS SIGNED) >= 400
                AND {$shipExpr} IS NOT NULL
                AND {$shipExpr} != ''
                AND DATE({$shipExpr}) <= '{$today}'
            THEN DATEDIFF('{$today}', DATE({$shipExpr}))
            ELSE NULL
        END";

        $query
            ->orderByRaw("{$groupExpr} {$dir}")
            ->orderByRaw("{$daysExpr} {$dir}");
    }

    /**
     * associatedID（親 service の orderID）から親案件の status / shippingOut を一括取得。
     *
     * @param  Collection<int, LoanerMaster>  $rows
     * @return Collection<int, ServiceRecord>
     */
    private function loadParentsByAssociatedId(Collection $rows): Collection
    {
        $ids = $rows
            ->pluck('associatedID')
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return ServiceRecord::query()
            ->whereIn('orderID', $ids)
            ->get(['orderID', 'status', 'shippingOut_requiredDate'])
            ->keyBy(fn (ServiceRecord $parent) => (int) $parent->orderID);
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<string, string>  $statusLabels
     * @param  Collection<int, ServiceRecord>|null  $parentById
     * @return array<string, mixed>
     */
    private function serializeRow(
        LoanerMaster $row,
        array $columns,
        string $statusColumn,
        array $statusLabels,
        ?Collection $parentById = null,
        bool $includeParentInfo = false,
    ): array {
        $out = [];
        $parents = $parentById ?? collect();

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

        if ($includeParentInfo) {
            $associatedId = (int) ($row->associatedID ?? 0);
            $parent = $associatedId > 0 ? $parents->get($associatedId) : null;
            $shippingOut = null;
            $shippingRaw = $parent?->shippingOut_requiredDate;
            if ($shippingRaw instanceof \DateTimeInterface) {
                $shippingOut = $shippingRaw->format('Y-m-d');
            } elseif ($shippingRaw !== null && $shippingRaw !== '') {
                $raw = substr((string) $shippingRaw, 0, 10);
                $shippingOut = preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) ? $raw : null;
            }

            $out['parentStatus'] = $parent?->status;
            $out['parentShippingOut'] = $shippingOut;
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
