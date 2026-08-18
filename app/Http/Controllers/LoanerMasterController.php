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

        $masters = LoanerMaster::query()
            ->orderBy('loanerID')
            ->orderByDesc('id')
            ->paginate(100)
            ->withQueryString()
            ->through(fn (LoanerMaster $row) => $this->serializeRow($row, $columns, $statusColumn, $statusLabels));

        return Inertia::render('LoanerMasterList', [
            'columns' => $columns,
            'masters' => $masters,
            'statusColumn' => $statusColumn,
        ]);
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
            $label = StatusLoaner::resolveLabel($row);
            if ($label === null || $label === '') {
                continue;
            }

            $map[(string) $row->processID_new] = $label;
            if ($row->processID !== null && $row->processID !== '') {
                $map[(string) $row->processID] = $label;
            }
        }

        return $map;
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
                $out[$column] = $this->formatStatusCell($value, $statusLabels);
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
