<?php

namespace App\Services;

use App\Models\LoanerMaster;
use App\Models\PartMaster;
use App\Models\ServiceMaster;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * servicemaster / partmaster / loanermaster の価格版を解決する。
 *
 * MySQL 5.7 / 8 共通:
 * - 受注日あり（2001年以降）: validDateMin〜Max を Y-m-d 文字列で比較
 * - 期間未設定（NULL / 0000-00-00）は常に候補
 * - 受注日未定 / 2000年以前 / 該当なし: 最新版（validDateMin が最新）
 * - ウィンドウ関数・DATE('0000-00-00') は使わない（MySQL 8 error 1525）
 */
class MasterPriceVersionResolver
{
    public const PAID_LOANER_RETURN_CODES = [1, 2, 7, 13];

    public function normalizeDate(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        if (is_string($date)) {
            $text = trim($date);
            if ($text === '' || str_starts_with($text, '0000-00-00')) {
                return null;
            }
            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $text, $match) === 1) {
                if ((int) substr($match[1], 0, 4) < 1) {
                    return null;
                }

                return $match[1];
            }
        }

        if ($date instanceof DateTimeInterface) {
            // date キャストはアプリTZの暦日。UTC ISO へ変換しない（5.7/8・TZ差で日付がずれないようにする）
            $year = (int) $date->format('Y');
            if ($year < 1) {
                return null;
            }

            return $date->format('Y-m-d');
        }

        try {
            $parsed = Carbon::parse($date);
            if ((int) $parsed->year < 1) {
                return null;
            }

            return $parsed->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 価格版の起点日。未設定・2000年以前は最新版を使うため null。
     */
    public function normalizePriceAsOfDate(mixed $date): ?string
    {
        $normalized = $this->normalizeDate($date);
        if ($normalized === null) {
            return null;
        }

        if ((int) substr($normalized, 0, 4) < 2001) {
            return null;
        }

        return $normalized;
    }

    public function isLoanerOwnOrderDateUsable(mixed $date): bool
    {
        $ymd = $this->normalizeDate($date);
        if ($ymd === null) {
            return false;
        }

        $year = (int) substr($ymd, 0, 4);

        return $year > 2000 && $year < 2099;
    }

    /**
     * loaner の価格版 as-of。
     * 基点は常に loaner 案件自身の受注日（親 service の受注日は使わない）。
     * $serviceOrderDate は互換のため残すが参照しない。
     * 発送予定日・出荷日は使わない。
     */
    public function resolveLoanerPriceAsOf(mixed $loanerOrderDate, mixed $serviceOrderDate = null): ?string
    {
        return $this->normalizePriceAsOfDate($loanerOrderDate);
    }

    public function firstValidAsOf(mixed ...$dates): ?string
    {
        foreach ($dates as $date) {
            $normalized = $this->normalizePriceAsOfDate($date);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    public function firstAsOf(Builder $query, mixed $asOfDate): ?Model
    {
        $asOf = $this->normalizePriceAsOfDate($asOfDate);

        if ($asOf !== null) {
            $matched = $this->applyLatestVersionOrder(
                $this->applyAsOfRange((clone $query)->reorder(), $asOf)
            )->first();

            if ($matched) {
                return $matched;
            }
        }

        return $this->applyLatestVersionOrder((clone $query)->reorder())->first();
    }

    /**
     * 一覧表示用: 業務キーごとに最新版だけ残す。
     *
     * @return Collection<int, Model>
     */
    public function latestByKey(Builder $query, string $businessKey): Collection
    {
        return $this->applyLatestVersionOrder((clone $query)->reorder())
            ->get()
            ->groupBy(function (Model $row) use ($businessKey) {
                $value = $row->getAttribute($businessKey);

                return $value === null || $value === ''
                    ? 'null:'.$row->getKey()
                    : (string) $value;
            })
            ->map(fn (Collection $group) => $group->first())
            ->values();
    }

    public function serviceMaster(mixed $serviceId, mixed $asOfDate = null, mixed $productName = null): ?ServiceMaster
    {
        if ($serviceId !== null && $serviceId !== '' && (int) $serviceId !== 0) {
            /** @var ServiceMaster|null $master */
            $master = $this->firstAsOf(
                ServiceMaster::query()->where('serviceID', $serviceId),
                $asOfDate
            );
            if ($master) {
                return $master;
            }
        }

        $name = trim((string) ($productName ?? ''));
        if ($name === '') {
            return null;
        }

        // 5.7 は PAD SPACE（末尾空白を無視）、8 の utf8mb4_0900 は NO PAD。
        // TRIM 同士ならどちらでも同じ行に当たる。
        /** @var ServiceMaster|null $master */
        $master = $this->firstAsOf(
            ServiceMaster::query()->whereRaw('TRIM(productName) = ?', [$name]),
            $asOfDate
        );

        return $master;
    }

    public function partMaster(mixed $partId, mixed $asOfDate = null): ?PartMaster
    {
        if ($partId === null || $partId === '') {
            return null;
        }

        /** @var PartMaster|null $master */
        $master = $this->firstAsOf(
            PartMaster::query()->where('partID', $partId),
            $asOfDate
        );

        return $master;
    }

    public function loanerMaster(mixed $loanerId, mixed $asOfDate = null): ?LoanerMaster
    {
        if ($loanerId === null || $loanerId === '') {
            return null;
        }

        /** @var LoanerMaster|null $master */
        $master = $this->firstAsOf(
            LoanerMaster::query()->where(function (Builder $query) use ($loanerId) {
                $query->where('loanerID', $loanerId)
                    ->orWhere('id', $loanerId);
            }),
            $asOfDate
        );

        return $master;
    }

    /**
     * loaner 課金価格: returnCode が有償コードのときだけマスタ価格、それ以外は 0。
     */
    public function loanerChargePrice(mixed $returnCode, mixed $loanerId, mixed $asOfDate = null): float
    {
        if (! in_array((int) $returnCode, self::PAID_LOANER_RETURN_CODES, true)) {
            return 0.0;
        }

        $master = $this->loanerMaster($loanerId, $asOfDate);

        return (float) ($master->price ?? 0);
    }

    /**
     * フロント表示用に、同一キーの価格版一覧を返す。
     */
    public function loanerPriceVersions(mixed $loanerId): array
    {
        if ($loanerId === null || $loanerId === '') {
            return [];
        }

        return $this->applyLatestVersionOrder(
            LoanerMaster::query()->where(function (Builder $query) use ($loanerId) {
                $query->where('loanerID', $loanerId)
                    ->orWhere('id', $loanerId);
            })->reorder()
        )
            ->get(['id', 'loanerID', 'price', 'validDateMin', 'validDateMax'])
            ->map(fn (LoanerMaster $row) => [
                'id' => $row->id,
                'loanerID' => $row->loanerID,
                'price' => (float) ($row->price ?? 0),
                'validDateMin' => $this->normalizeDate($row->validDateMin),
                'validDateMax' => $this->normalizeDate($row->validDateMax),
            ])
            ->values()
            ->all();
    }

    /**
     * 期間内、または未設定の境界は無制限（NULL / 0000-00-00）。
     * フロントの inDateRange と同じ: min のみ / max のみでも受注日で版が切り替わる。
     * DATE リテラル '0000-00-00' や DATE() は MySQL 8 (error 1525) で落ちるため、CHAR の先頭10桁で比較する。
     */
    private function applyAsOfRange(Builder $query, string $asOf): Builder
    {
        $emptyMin = $this->sqlEmptyDate('validDateMin');
        $emptyMax = $this->sqlEmptyDate('validDateMax');
        $minYmd = $this->sqlDateYmd('validDateMin');
        $maxYmd = $this->sqlDateYmd('validDateMax');

        return $query->where(function (Builder $builder) use ($asOf, $emptyMin, $emptyMax, $minYmd, $maxYmd) {
            $builder
                ->where(function (Builder $minOk) use ($asOf, $emptyMin, $minYmd) {
                    $minOk->whereRaw($emptyMin)
                        ->orWhereRaw("{$minYmd} <= ?", [$asOf]);
                })
                ->where(function (Builder $maxOk) use ($asOf, $emptyMax, $maxYmd) {
                    $maxOk->whereRaw($emptyMax)
                        ->orWhereRaw("{$maxYmd} >= ?", [$asOf]);
                });
        });
    }

    /**
     * 最新版順。0000-00-00 / NULL は「空」として末尾へ。
     */
    private function applyLatestVersionOrder(Builder $query): Builder
    {
        $emptyMin = $this->sqlEmptyDate('validDateMin');
        $minYmd = $this->sqlDateYmd('validDateMin');

        return $query
            ->orderByRaw("CASE WHEN {$emptyMin} THEN 0 ELSE 1 END DESC")
            ->orderByRaw("{$minYmd} DESC")
            ->orderByDesc('id');
    }

    private function sqlDateYmd(string $column): string
    {
        return "LEFT(CAST({$column} AS CHAR), 10)";
    }

    private function sqlEmptyDate(string $column): string
    {
        $ymd = $this->sqlDateYmd($column);

        return "({$column} IS NULL OR {$ymd} = '' OR {$ymd} = '0000-00-00')";
    }
}
