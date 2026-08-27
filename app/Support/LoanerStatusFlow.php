<?php

namespace App\Support;

/**
 * 貸出案件（order_type=loaner）のメインステータスフロー定義。
 *
 * 「次へ」:
 *   確保済み(0) / 案件未登録(20)
 *     → 見積済み(100)
 *     → 受注(150) / 貸出機出荷準備(200) / 貸出機出荷準備＿差戻(201)
 *     → 貸出機出荷準備完了＿起伝依頼(300)
 *     →（300以上393未満は次へ disable。返却(393)は手動など）
 *     → 返却(393) → 受け入れ確認中(396)（次へ disable）
 *     →（完了前、予約確認(399)は手動など）→ 完了(400)
 *
 * 「次へ」disable: status が 300以上393未満、または 396。
 * labor は status=返却(393) のときのみ編集可。
 * status=300 遷移時は出荷予定日ダイアログで shippingOut_requiredDate を設定する。
 * アクティブな loaner リストは status >= 0 かつ status < 400。
 */
class LoanerStatusFlow
{
    public const STOCK = 0; // 確保済み

    public const UNREGISTERED = 20; // 案件未登録

    public const QUOTE_DONE = 100; // 見積済み

    public const ORDERED = 150; // 受注

    public const SHIP_PREP = 200; // 貸出機出荷準備

    /** 貸出機出荷準備＿差戻（次へで 300 へ） */
    public const SHIP_PREP_REMAND = 201;

    /** 貸出機出荷準備完了＿起伝依頼（出荷予定日設定） */
    public const SHIP_PREP_COMPLETE = 300;

    /** @deprecated 旧フロー。次へでは使わない */
    public const SHIP_REQUEST = 350;

    /** 貸出中 */
    public const LENDING_OUT = 388;

    /** 返却（labor 設定可能） */
    public const RETURNED = 393;

    /** 受け入れ確認中（次へ disable） */
    public const ACCEPTANCE = 396;

    /** 完了前、予約確認 */
    public const PRE_COMPLETE = 399;

    /** 完了 */
    public const COMPLETE = 400;

    /** @deprecated 旧「貸出中」。完了と同値だった名残 */
    public const LENDING = 400;

    /** @deprecated 旧フロー末尾 */
    public const CHECK = 650;

    /**
     * メインフロー上の代表ステップ（表示・互換用）。
     *
     * @var list<int>
     */
    public const STEPS = [
        self::STOCK,
        self::QUOTE_DONE,
        self::ORDERED,
        self::SHIP_PREP_COMPLETE,
        self::RETURNED,
        self::ACCEPTANCE,
        self::PRE_COMPLETE,
        self::COMPLETE,
    ];

    /** アクティブ loaner リストの上限（未満） */
    public const ACTIVE_LIST_STATUS_MAX = self::COMPLETE;

    public static function isAcceptanceStatus(mixed $statusId): bool
    {
        return (int) $statusId === self::ACCEPTANCE;
    }

    public static function isReturnedStatus(mixed $statusId): bool
    {
        return (int) $statusId === self::RETURNED;
    }

    public static function isLaborEditableStatus(mixed $statusId): bool
    {
        return self::isReturnedStatus($statusId);
    }

    public static function isShipPrepCompleteStatus(mixed $statusId): bool
    {
        return (int) $statusId === self::SHIP_PREP_COMPLETE;
    }

    public static function isCheckStatus(mixed $statusId): bool
    {
        return (int) $statusId === self::CHECK;
    }

    public static function isStockStatus(mixed $statusId): bool
    {
        return (int) $statusId === self::STOCK;
    }

    public static function isLendingStatus(mixed $statusId): bool
    {
        return (int) $statusId === self::LENDING;
    }

    public static function isActiveListStatus(mixed $statusId): bool
    {
        $status = (int) $statusId;

        return $status >= self::STOCK && $status < self::ACTIVE_LIST_STATUS_MAX;
    }

    /**
     * 「次へ」ボタンを disable する status か。
     * 300以上393未満、または 396。
     */
    public static function isNextButtonDisabled(mixed $statusId): bool
    {
        $status = (int) $statusId;

        return ($status >= self::SHIP_PREP_COMPLETE && $status < self::RETURNED)
            || $status === self::ACCEPTANCE;
    }

    /**
     * アクティブリスト外（完了 400）へ初めて到達したか。
     */
    public static function crossedToInactiveList(mixed $previousStatusId, mixed $nextStatusId): bool
    {
        return self::isActiveListStatus($previousStatusId)
            && !self::isActiveListStatus($nextStatusId);
    }

    public static function nextStatusId(mixed $currentStatusId): ?int
    {
        $current = (int) $currentStatusId;

        return match ($current) {
            self::STOCK, self::UNREGISTERED => self::QUOTE_DONE,
            self::QUOTE_DONE => self::ORDERED,
            self::ORDERED, self::SHIP_PREP, self::SHIP_PREP_REMAND => self::SHIP_PREP_COMPLETE,
            self::RETURNED => self::ACCEPTANCE,
            self::PRE_COMPLETE => self::COMPLETE,
            default => null,
        };
    }

    /**
     * @return array{
     *     steps:list<int>,
     *     checkStatusId:int,
     *     completeStatusId:int,
     *     stockStatusId:int,
     *     unregisteredStatusId:int,
     *     lendingStatusId:int,
     *     laborEditableStatusId:int,
     *     returnedStatusId:int,
     *     acceptanceStatusId:int,
     *     preCompleteStatusId:int,
     *     shipPrepStatusId:int,
     *     shipPrepCompleteStatusId:int,
     *     shipPrepRemandStatusId:int,
     *     shipRequestStatusId:int,
     *     activeListStatusMax:int,
     *     nextDisabledExactStatusIds:list<int>
     * }
     */
    public static function meta(): array
    {
        return [
            'steps' => self::STEPS,
            'checkStatusId' => self::CHECK,
            'completeStatusId' => self::COMPLETE,
            'stockStatusId' => self::STOCK,
            'unregisteredStatusId' => self::UNREGISTERED,
            'lendingStatusId' => self::LENDING,
            'laborEditableStatusId' => self::RETURNED,
            'returnedStatusId' => self::RETURNED,
            'acceptanceStatusId' => self::ACCEPTANCE,
            'preCompleteStatusId' => self::PRE_COMPLETE,
            'shipPrepStatusId' => self::SHIP_PREP,
            'shipPrepCompleteStatusId' => self::SHIP_PREP_COMPLETE,
            'shipPrepRemandStatusId' => self::SHIP_PREP_REMAND,
            'shipRequestStatusId' => self::SHIP_REQUEST,
            'activeListStatusMax' => self::ACTIVE_LIST_STATUS_MAX,
            'nextDisabledExactStatusIds' => [self::ACCEPTANCE],
        ];
    }
}
