<?php

namespace App\Services;

use App\Models\LoanerMaster;
use App\Models\PartMaster;
use App\Models\ServiceMaster;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * servicemaster / partmaster / loanermaster の価格版を解決する。
 *
 * - 受注日あり: validDateMin <= 受注日 <= validDateMax（なければ最新）
 * - 受注日未定: 最新版（validDateMin が最新）
 */
class MasterPriceVersionResolver
{
    public const PAID_LOANER_RETURN_CODES = [1, 2, 7, 13];

    public function normalizeDate(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function firstAsOf(Builder $query, mixed $asOfDate): ?Model
    {
        $asOf = $this->normalizeDate($asOfDate);

        if ($asOf !== null) {
            $matched = (clone $query)
                ->where(function (Builder $builder) use ($asOf) {
                    $builder
                        ->where(function (Builder $range) use ($asOf) {
                            $range->where('validDateMin', '<=', $asOf)
                                ->where('validDateMax', '>=', $asOf);
                        })
                        ->orWhere(function (Builder $legacy) {
                            // 期間未設定の既存行は常に候補にする
                            $legacy->whereNull('validDateMin')
                                ->whereNull('validDateMax');
                        });
                })
                ->orderByDesc('validDateMin')
                ->orderByDesc('id')
                ->first();

            if ($matched) {
                return $matched;
            }
        }

        return (clone $query)
            ->orderByDesc('validDateMin')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * 一覧表示用: 業務キーごとに最新版だけ残す。
     *
     * @return Collection<int, Model>
     */
    public function latestByKey(Builder $query, string $businessKey): Collection
    {
        return (clone $query)
            ->reorder()
            ->orderByDesc('validDateMin')
            ->orderByDesc('id')
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

    public function serviceMaster(mixed $serviceId, mixed $asOfDate = null): ?ServiceMaster
    {
        if ($serviceId === null || $serviceId === '') {
            return null;
        }

        /** @var ServiceMaster|null $master */
        $master = $this->firstAsOf(
            ServiceMaster::query()->where('serviceID', $serviceId),
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

        return LoanerMaster::query()
            ->where(function (Builder $query) use ($loanerId) {
                $query->where('loanerID', $loanerId)
                    ->orWhere('id', $loanerId);
            })
            ->orderByDesc('validDateMin')
            ->get(['loanerID', 'price', 'validDateMin', 'validDateMax'])
            ->map(fn (LoanerMaster $row) => [
                'loanerID' => $row->loanerID,
                'price' => (float) ($row->price ?? 0),
                'validDateMin' => $this->normalizeDate($row->validDateMin),
                'validDateMax' => $this->normalizeDate($row->validDateMax),
            ])
            ->values()
            ->all();
    }
}
