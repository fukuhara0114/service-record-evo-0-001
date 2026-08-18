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
            ->serviceMaster($this->serviceID, $this->orderDate);
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
