<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ServiceMaster;
use App\Models\ReturnCode;
use App\Models\Dealer;
use App\Models\Status;
use App\Models\StatusLoaner;
use App\Models\Labor;
use App\Models\User;
use App\Models\AttachedFile;
use App\Models\AttachedNote;
use App\Models\AttachedPart;


class ServiceRecord extends Model
{
    // 接続するテーブル名
    protected $table = 'servicerecord';

    // 主キーの指定
    protected $primaryKey = 'orderID';

    // 既存データベースで自動挿入されない場合はfalse
    public $timestamps = false;

    /** loaner / waiting_list は専用フロー以外で null・service 等へ落とさない */
    public const PROTECTED_ORDER_TYPES = ['loaner', 'waiting_list'];

    /** 作成時に確定する order_type 値 */
    public const CREATABLE_ORDER_TYPES = ['service', 'loaner', 'waiting_list'];

    protected static function booted(): void
    {
        static::creating(function (ServiceRecord $record) {
            $orderType = self::normalizeOrderType($record->order_type);
            // 新規 service 案件は必ず明示的に service（null/空/不明値は service）
            if (! in_array($orderType, ['loaner', 'waiting_list'], true)) {
                $orderType = 'service';
                $record->order_type = 'service';
            }
            // 作成時のみ設定。クライアント入力は無視し、以後 update でも変えない
            $record->original_order_type = $orderType;
        });

        static::updating(function (ServiceRecord $record) {
            // original_order_type は作成後に絶対変更しない
            if ($record->isDirty('original_order_type')) {
                $record->original_order_type = $record->getOriginal('original_order_type');
            }

            $original = self::normalizeOrderType($record->getOriginal('order_type'));
            $next = self::normalizeOrderType($record->order_type);

            if ($record->isDirty('order_type')) {
                // 既存が loaner / waiting_list のとき、許可遷移は同グループ内のみ
                if (in_array($original, self::PROTECTED_ORDER_TYPES, true)
                    && ! in_array($next, self::PROTECTED_ORDER_TYPES, true)
                ) {
                    $record->order_type = $record->getOriginal('order_type');
                    $next = $original;
                }
            }

            // loaner 案件から外れる／個体を差し替えるときは旧個体を在庫へ戻す
            if ($original === 'loaner') {
                $previousLoanerId = $record->getOriginal('loanerID');
                $leavingLoaner = $next !== 'loaner';
                $changingUnit = $record->isDirty('loanerID')
                    && (string) ($previousLoanerId ?? '') !== (string) ($record->loanerID ?? '');

                if ($leavingLoaner || $changingUnit) {
                    LoanerMaster::releaseCurrentStatusIfUnlinked(
                        $previousLoanerId,
                        $record->getKey(),
                    );
                }
            }
        });

        static::saved(function (ServiceRecord $record) {
            LoanerMaster::syncCurrentStatusFromLoanerRecord($record);
        });
    }

    public static function normalizeOrderType(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $normalized = strtolower(trim((string) $value));

        return $normalized === '' ? null : $normalized;
    }

    // 一括保存・更新を許可するカラム一覧（画像より全件抽出）
    protected $fillable = [
        'receiptNumber',
        'receivedDate',
        'RMA',
        'serviceID',
        'productName',
        'productType',
        'SN',
        'status',
        'returnCode',
        'preData',
        'postData',
        'a2la',
        'dealer',
        'discountRate',
        'dealer_depart',
        'contactPerson',
        'email',
        'phone',
        'fax',
        'zipcode',
        'address1',
        'address2',
        'deliverToEndUser',
        'endUser',
        'endUser_depart',
        'endUser_contactPerson',
        'endUser_email',
        'endUser_phone',
        'endUser_fax',
        'endUser_zipcode',
        'endUser_address1',
        'endUser_address2',
        'deliveryDestination_company',
        'deliveryDestination_depart',
        'deliveryDestination_contactPerson',
        'deliveryDestination_email',
        'deliveryDestination_phone',
        'deliveryDestination_zipcode',
        'deliveryDestination_address1',
        'deliveryDestination_address2',
        'laborID',
        'quoteDate',
        'quoteNum',
        'poNum',
        'orderDate',
        'orderNum',
        'work_completion_date',
        'tat',
        'invNum',
        'price',
        'rmaNumOverSea',
        'shippedDate',
        'shipTo',
        'sentOut',
        'incident',
        'symptoms',
        'sm_workorder',
        'sm_quote',
        'coNum',
        'mapics_inv',
        'mapics47',
        'onEdit',
        'task',
        'discount_service',
        'remand',
        'shippingOut_requiredDate',
        'loaner_no_charge',
        'parentID',
        'order_type',
        'lastEditPerson',
        'lastEditDate',
        'entityID',
        'loanerID',
        'promotion_ready_at',
        'promotion_source_orderID',
    ];

    protected $casts = [
        'promotion_ready_at' => 'datetime',
        'work_completion_date' => 'date',
    ];


    // **********************************************************************************************************************
    //   リレーションの設定
    // **********************************************************************************************************************
    
    /**
     * 業務キー serviceID で紐づく（版をまたぐ。価格版の解決は getServiceAtOrderedDate() を使う）。
     */
    public function serviceMaster()
    {
        return $this->belongsTo(ServiceMaster::class, 'serviceID', 'serviceID');
    }

    /**
     * 受注日に合致する価格版（未設定なら最新版）。
     */
    public function getServiceAtOrderedDate()
    {
        return app(\App\Services\MasterPriceVersionResolver::class)
            ->serviceMaster($this->serviceID, $this->orderDate, $this->productName);
    }
    // returnCode
    public function returnCodeMaster()
    {
        return $this->belongsTo(ReturnCode::class, 'returnCode', 'id');
        // 'returnCode' = カラム名（FK）
        // 'id' = 参照先（省略可）
    }

    public function statusMaster() // 👈 名前を「statusMaster」に変更
    {
        // 第2引数はご自身のテーブルのカラム名（小文字の 'status'）
        // 第3引数は相手のマスターテーブルの主キー名（'id'）
        return $this->belongsTo(Status::class, 'status', 'processID_new')
            ->select(['processID_new', 'status']);
    }

    public function statusMasterLoaner()
    {
        return $this->belongsTo(StatusLoaner::class, 'status', 'processID_new')
            ->select(StatusLoaner::selectColumnsForDisplay());
    }

    /**
     * order_type に応じた status マスタ（waiting_list は status なし）
     */
    public function getResolvedStatusMasterAttribute()
    {
        if ($this->order_type === 'waiting_list') {
            return null;
        }

        if (in_array($this->order_type, ['loaner'], true)) {
            return $this->statusMasterLoaner;
        }

        return $this->statusMaster;
    }

    
    public function userMaster() 
    {

        return $this->belongsTo(User::class, 'user', 'userID');
    }

    public function laborMaster() 
    {

        return $this->belongsTo(Labor::class, 'laborID', 'laborID');
    }

    public function attachedNoteMaster() 
    {

        return $this->belongsTo(AttachedNote::class, 'orderID', 'associatedID');
    }

    public function attachedFileMaster() 
    {

        return $this->belongsTo(AttachedFile::class, 'orderID', 'associatedID');
    }

    public function attachedPartMaster() 
    {

        return $this->belongsTo(AttachedPart::class, 'orderID', 'associatedID');
    }

}
