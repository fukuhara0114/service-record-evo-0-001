<?php

namespace App\Http\Controllers;

use App\Models\ServiceRecord;
use Illuminate\Http\Request;

use Inertia\Inertia;

class ServiceRecordController extends Controller
{
    // 1. 一覧表示（全カラム対応のため、テーブルが横長になりすぎないよう主要項目＋全データを確認できる詳細リンクを設置）
    public function index()
    {

    // 全件取得（件数が多い場合は ServiceRecord::paginate(20) などがおすすめ）
        // $records = ServiceRecord::paginate(250);  
        $records = ServiceRecord::
                with(['returnCodeMaster', 'laborMaster','statusMaster'])
                ->orderBy('receivedDate', 'asc')->paginate(2000);

        // return view('servicerecord', compact('records'));
        $statuses = \App\Models\Status::all(); 
        $returnCodes = \App\Models\ReturnCode::all(); 
        $labors = \App\Models\Labor::all();
                
        return view('servicerecords.servicerecord')
            ->with('records', $records)
            ->with('statuses', $statuses)
            ->with('returnCodes', $returnCodes)
            ->with('labors', $labors)
            ->with('mode', 'whole_data');

    }

    public function index_q()
    {
        // 9000件のデータをリレーションと一緒に一括取得
         $records = ServiceRecord::select([
                    'orderID',
                    'status',
                    'RMA',
                    'receivedDate', 
                    'productName', 
                    'SN', 
                    'returnCode',
                    'laborID',
                    'dealer',
                    'dealer_depart',
                    'contactPerson',
                    'endUser', 
                    'endUser_depart', 
                    'endUser_contactPerson', 
                    'endUser_address1', 
                    'endUser_address2', 
                    'endUser_email', 
                    'endUser_phone',
                ])
                ->
        with(['returnCodeMaster', 'laborMaster','statusMaster'])
        ->orderBy('receivedDate', 'asc')
        ->get();

        $statuses = \App\Models\Status::all(); 
        $returnCodes = \App\Models\ReturnCode::all(); 
        $labors = \App\Models\Labor::all();
        
        return view('servicerecords.servicerecord_q')
            ->with('records', $records)
            ->with('statuses', $statuses)
            ->with('returnCodes', $returnCodes)
            ->with('labors', $labors)
            ->with('mode', 'whole');
    }



    // admin用表示　→　view: servicerecord
    public function administrator(){

        $records = ServiceRecord::select([
                    'orderID',
                    'status',
                    'RMA',
                    'receivedDate', 
                    'productName', 
                    'SN', 
                    'returnCode',
                    'laborID',
                    'dealer',
                    'dealer_depart',
                    'contactPerson',
                    'endUser', 
                    'endUser_depart', 
                    'endUser_contactPerson', 
                    'endUser_address1', 
                    'endUser_address2', 
                    'endUser_email', 
                    'endUser_phone',
                ])
                ->
        with(['returnCodeMaster', 'laborMaster','statusMaster'])
        ->where('status', '<', 399)
        ->where('status', '>', -1)
        ->orderBy('receivedDate', 'asc')
        ->get();

        $statuses = \App\Models\Status::all(); 
        $returnCodes = \App\Models\ReturnCode::all(); 
        $labors = \App\Models\Labor::all();
        
        // return view('servicerecords.servicerecord_q')
        //     ->with('records', $records)
        //     ->with('statuses', $statuses)
        //     ->with('returnCodes', $returnCodes)
        //     ->with('labors', $labors)
        //     ->with('mode', 'admin');
        return Inertia::render('ServiceRecordList', [
                    'initialRecords' => $records,     // 💡 Vue側へ渡すデータの箱（プロパティ）を定義
                    'statuses'       => $statuses,
                    'returnCodes'    => $returnCodes,
                    'labors'         => $labors,
                    'mode'           => 'admin'
                ]);
    }

    public function detail($orderID) {
        // 1. 🚀 送られてきた orderID と一致するデータを1件だけデータベースから取得
        // with() を使うことで、一覧ページと同じように紐づくマスターデータも一緒に一瞬で持ってきます
        $record = ServiceRecord::with(['statusMaster', 'laborMaster', 'statusMaster'])
                    ->where('orderID', $orderID)
                    ->first(); // 1件だけ取得

        // 2. 万が一、不正なIDが直接URLに打ち込まれてデータが見つからなかった場合は404エラー画面を出す
        if (!$record) {
            abort(404, '指定された作業内容は存在しません。');
        }

        // 3. 🚀 詳細画面用のView（detail.blade.php）を呼び出し、データを引き渡す
        return view('servicerecords.detail')
            ->with('record', $record);
    }


    // 2. 新規登録画面
    public function create()
    {
        return view('servicerecord.create');
    }

    // 3. 新規保存（全カラム対応）
    public function store(Request $request)
    {
        // データの存在チェック（最低限のバリデーション）のみ行い、すべて取得
        $data = $request->except('_token');
        
        ServiceRecord::create($data);

        return redirect()->route('servicerecord.index')->with('success', '新規登録しました。');
    }

    // 4. 編集画面
    public function edit($orderID)
    {
        $record = ServiceRecord::findOrFail($orderID);
        return view('servicerecord.edit', compact('record'));
    }

    // 5. 更新（全カラム対応）
    public function update(Request $request, $orderID)
    {
        $record = ServiceRecord::findOrFail($orderID);
        
        $data = $request->except(['_token', '_method']);
        $record->update($data);

        return redirect()->route('servicerecord.index')->with('success', '更新しました。');
    }

    // 6. 削除
    public function destroy($orderID)
    {
        $record = ServiceRecord::findOrFail($orderID);
        $record->delete();

        return redirect()->route('servicerecord.index')->with('success', '削除しました。');
    }
}
