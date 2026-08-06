<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Large existing tables must not be altered inside a transaction.
     */
    public $withinTransaction = false;

    /**
     * @var array<string, array{column: string, index: string}>
     */
    private array $indexes = [
        'attachedfiles' => [
            'column' => 'associatedID',
            'index' => 'idx_attachedfiles_associated_id',
        ],
        'attachednotes' => [
            'column' => 'associatedID',
            'index' => 'idx_attachednotes_associated_id',
        ],
        'attachedparts' => [
            'column' => 'associatedID',
            'index' => 'idx_attachedparts_associated_id',
        ],
        'attachedloaners' => [
            'column' => 'associatedID',
            'index' => 'idx_attachedloaners_associated_id',
        ],
        'servicerecord' => [
            'column' => 'parentID',
            'index' => 'idx_servicerecord_parent_id',
        ],
        'captured_image' => [
            'column' => 'associatedID',
            'index' => 'idx_captured_image_associated_id',
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $definition) {
            $column = $definition['column'];

            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }

            // An existing compound index is sufficient when the lookup column is first.
            if ($this->hasLeadingColumnIndex($table, $column)) {
                continue;
            }

            $this->addIndex($table, $column, $definition['index']);
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->indexes, true) as $table => $definition) {
            if (!Schema::hasTable($table) || !$this->hasNamedIndex($table, $definition['index'])) {
                continue;
            }

            $this->dropIndex($table, $definition['index']);
        }
    }

    private function hasLeadingColumnIndex(string $table, string $column): bool
    {
        return DB::table('information_schema.statistics')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $table)
            ->where('seq_in_index', 1)
            ->where('column_name', $column)
            ->exists();
    }

    private function hasNamedIndex(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function addIndex(string $table, string $column, string $index): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement(sprintf(
                'ALTER TABLE `%s` ADD INDEX `%s` (`%s`), ALGORITHM=INPLACE, LOCK=NONE',
                $table,
                $index,
                $column,
            ));

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $index) {
            $blueprint->index($column, $index);
        });
    }

    private function dropIndex(string $table, string $index): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement(sprintf(
                'ALTER TABLE `%s` DROP INDEX `%s`, ALGORITHM=INPLACE, LOCK=NONE',
                $table,
                $index,
            ));

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index) {
            $blueprint->dropIndex($index);
        });
    }
};
