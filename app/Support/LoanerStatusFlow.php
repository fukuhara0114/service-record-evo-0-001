<?php

namespace App\Support;

/**
 * 貸出案件（order_type=loaner）のメインステータスフロー定義。
 *
 * 在庫(0:確保済み) → 見積済み → 受注 → 起伝依頼(300) → 発送依頼(350) → 貸出中(400) → 返却 → 受け入れ確認中 → チェック
 * アクティブな loaner リストは status >= 0 かつ status < 400。
 * status が 400 に到達した時点で機材を在庫(currentStatus=0)に戻し、waiting_list を確認する。
 * 案件 status を再び 0 に戻してリストへ残す循環は行わない。
 * labor は「受け入れ確認中」のみ設定可能。
 * status=300 遷移時は出荷予定日ダイアログで shippingOut_requiredDate を設定する。
 */
class LoanerStatusFlow
{
    public const STOCK = 0; // 確保済み（新規登録時の初期 status）

    public const QUOTE_DONE = 100;

    public const ORDERED = 150;

    /** 貸出機出荷準備＿差戻（起伝差戻。次へで 300 へ復帰） */
    public const SHIP_PREP_REMAND = 201;

    /** 貸出機出荷準備完了＿起伝依頼（出荷予定日設定） */
    public const SHIP_PREP_COMPLETE = 300;

    public const SHIP_REQUEST = 350;

    /** 貸出中（アクティブリストから外れ、機材を在庫復帰） */
    public const LENDING = 400;

    public const RETURNED = 450;

    /** 受け入れ確認中（labor 設定可能） */
    public const ACCEPTANCE = 500;

    public const CHECK = 650;

    /**
     * メインフロー順（末尾で在庫へは戻さない）。
     *
     * @var list<int>
     */
    public const STEPS = [
        self::STOCK,
        self::QUOTE_DONE,
        self::ORDERED,
        self::SHIP_PREP_COMPLETE,
        self::SHIP_REQUEST,
        self::LENDING,
        self::RETURNED,
        self::ACCEPTANCE,
        self::CHECK,
    ];

    /** アクティブ loaner リストの上限（未満） */
    public const ACTIVE_LIST_STATUS_MAX = self::LENDING;

    public static function isAcceptanceStatus(mixed $statusId): bool
    {
        return (int) $statusId === self::ACCEPTANCE;
    }

    public static function isLaborEditableStatus(mixed $statusId): bool
    {
        return self::isAcceptanceStatus($statusId);
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
     * アクティブリスト外（貸出中以降）へ初めて到達したか。
     */
    public static function crossedToInactiveList(mixed $previousStatusId, mixed $nextStatusId): bool
    {
        return self::isActiveListStatus($previousStatusId)
            && !self::isActiveListStatus($nextStatusId);
    }

    public static function nextStatusId(mixed $currentStatusId): ?int
    {
        $current = (int) $currentStatusId;

        // 差戻(201) はサイドステータス。次へで起伝依頼(300)へ戻す
        if ($current === self::SHIP_PREP_REMAND) {
            return self::SHIP_PREP_COMPLETE;
        }

        $steps = self::STEPS;
        $index = array_search($current, $steps, true);
        if ($index === false) {
            return null;
        }

        if ($index >= count($steps) - 1) {
            return null;
        }

        return $steps[$index + 1];
    }

    /**
     * @return array{
     *     steps:list<int>,
     *     checkStatusId:int,
     *     stockStatusId:int,
     *     lendingStatusId:int,
     *     laborEditableStatusId:int,
     *     acceptanceStatusId:int,
     *     shipPrepCompleteStatusId:int,
     *     shipPrepRemandStatusId:int,
     *     shipRequestStatusId:int,
     *     activeListStatusMax:int,
     *     adminNextBlockedStatusIds:list<int>
     * }
     */
    public static function meta(): array
    {
        return [
            'steps' => self::STEPS,
            'checkStatusId' => self::CHECK,
            'stockStatusId' => self::STOCK,
            'lendingStatusId' => self::LENDING,
            'laborEditableStatusId' => self::ACCEPTANCE,
            'acceptanceStatusId' => self::ACCEPTANCE,
            'shipPrepCompleteStatusId' => self::SHIP_PREP_COMPLETE,
            'shipPrepRemandStatusId' => self::SHIP_PREP_REMAND,
            'shipRequestStatusId' => self::SHIP_REQUEST,
            'activeListStatusMax' => self::ACTIVE_LIST_STATUS_MAX,
            // admin の「次へ」: 350 への遷移不可、かつ 350 にいるときも進めない（手動 dropdown は可）
            'adminNextBlockedStatusIds' => [self::SHIP_REQUEST],
        ];
    }
}
