<?php

namespace App\Http\Controllers;

use App\Models\ServiceRecord;
use App\Models\ServiceMaster;
use App\Models\Dealer;
use App\Models\PartMaster;
use App\Models\AttachedNote;
use App\Models\AttachedFile;
use App\Models\AttachedPart;
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
        $statuses = \App\Models\Status::orderBy('processID')->get(); 
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
                    'email',
                    'phone',
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
    public function administrator(Request $request){
        // 添付データだけ欲しい Inertia 部分リロード（一覧2000件は再取得しない）
        if ($request->header('X-Inertia') && $request->header('X-Inertia-Partial-Data') === 'attachmentData') {
            return Inertia::render('ServiceRecordList', [
                'attachmentData' => $request->filled('loadOrderID')
                    ? $this->fetchAttachmentData($request->input('loadOrderID'))
                    : null,
            ]);
        }

        // $records = ServiceRecord::
        //     select([
        //         'orderID',
        //         'serviceID',
        //         'status',
        //         'RMA',
        //         'receivedDate',
        //         'productName',
        //         'SN',
        //         'returnCode',
        //         'laborID',
        //         'dealer',
        //         'dealer_depart',
        //         'contactPerson',
        //         'email',
        //         'phone',
        //         'endUser',
        //         'endUser_depart',
        //         'endUser_contactPerson',
        //         'endUser_address1',
        //         'endUser_address2',
        //         'endUser_email',
        //         'endUser_phone',
        //     ])
        //     ->with(['returnCodeMaster', 'laborMaster','statusMaster'])
        //     ->where('status', '<', 399)
        //     ->where('status', '>', -1)
        //     ->orderBy('receivedDate', 'asc')
        //     ->get();
        $records = ServiceRecord::with(['returnCodeMaster', 'laborMaster', 'statusMaster'])
            ->where('status', '<', 399)
            ->where('status', '>', -1)
            ->orderBy('receivedDate', 'asc')
            ->get();


        $statuses = \App\Models\Status::orderBy('processID')->get(); 
        $returnCodes = \App\Models\ReturnCode::all(); 
        $labors = \App\Models\Labor::all();
        $dealers = Dealer::orderBy('dealerName')->get();
        $services = ServiceMaster::query()
            ->select(['serviceID', 'productName', 'entityID'])
            ->where('productName', 'NOT LIKE', '*%') // *から始まらない条件を追加
            ->orderBy('productName')
            ->get();
        $partsMaster = PartMaster::query()
            ->select(['partID', 'partName', 'description', 'price_discounted', 'type'])
            ->orderBy('partName')
            ->get();

        $attachmentData = null;
        if ($request->filled('loadOrderID')) {
            $attachmentData = $this->fetchAttachmentData($request->input('loadOrderID'));
        }
        
        return Inertia::render('ServiceRecordList', [
                    'initialRecords' => $records,
                    'statuses'       => $statuses,
                    'returnCodes'    => $returnCodes,
                    'labors'         => $labors,
                    'dealersMaster'  => $dealers,
                    'servicesMaster' => $services,
                    'partsMaster'    => $partsMaster,
                    'mode'           => 'admin',
                    'attachmentData' => $attachmentData,
                ]);
    }

    public function detail($orderID) {

        $record = ServiceRecord::with(['statusMaster', 'laborMaster', 'statusMaster'])
                    ->where('orderID', $orderID)
                    ->first(); // 1件だけ取得

        $loaner_case = ServiceRecord::where('parent_id', $orderID)->first();

        $notes = AttachedNote::where('associatedID', $orderID)->get();
        $files = AttachedFile::where('associatedID', $orderID)->get();
        $parts = AttachedPart::where('associatedID', $orderID)->get();  
        $currentMaster = $record->getServiceAtOrderedDate();

    // return Inertia::render('ServiceRecords/Show', [
    //     'serviceRecord' => $serviceRecord, // 案件の基本情報
    //     'currentMaster' => $currentMaster, // 当時の価格が含まれたマスタ情報
        
    //     // 2. 詳細画面でマスタ自体を変更（プルダウン等で選択）できるようにマスタ一覧を渡す
    //     // ※ 重複を防ぐため、最新のユニークなマスタ（またはグループ化されたもの）を取得
    //     'masterOptions' => ServiceMaster::groupBy('serviceID')->get(['serviceID', 'serviceName']),
    // ]);       

        // 2. 万が一、不正なIDが直接URLに打ち込まれてデータが見つからなかった場合は404エラー画面を出す
        if (!$record) {
            abort(404, '指定された作業内容は存在しません。');
        }

        $statuses = \App\Models\Status::orderBy('processID')->get(); 
        $returnCodes = \App\Models\ReturnCode::all(); 
        $labors = \App\Models\Labor::all();
        $dealers = \App\Models\Dealer::orderBy('dealerName')->get();
        $parts = \App\Models\PartMaster::all(); 
        $services = \App\Models\ServiceMaster::all();

        return Inertia::render('ServiceRecords.detail', [
                    'initialRecord' => $record,
                    'statuses'       => $statuses,
                    'returnCodes'    => $returnCodes,
                    'labors'         => $labors,
                    'notes'         => $notes,
                    'files'         => $files,
                    'parts'         => $parts,
                    'servicesMaster' => $services,    
                    'dealersMaster' => $dealers,
                    'partsMaster' => $parts,
                    'mode'           => 'admin'
                ]);
    }   

    public function record($orderID)
    {
        $record = ServiceRecord::with(['returnCodeMaster', 'laborMaster', 'statusMaster'])
            ->where('orderID', $orderID)
            ->first();

        if (!$record) {
            return response()->json(['message' => '指定された案件は存在しません。'], 404);
        }

        return response()->json($record);
    }

    public function attachments($orderID)
    {
        $data = $this->fetchAttachmentData($orderID);

        if ($data === null) {
            return response()->json(['message' => '指定された案件は存在しません。'], 404);
        }

        if (isset($data['error'])) {
            return response()->json([
                'message' => '添付データの取得中にサーバーエラーが発生しました。',
                'detail' => config('app.debug') ? $data['error'] : null,
            ], 500);
        }

        return response()->json($data);
    }

    public function fileContent($fileId)
    {
        $file = AttachedFile::findOrFail($fileId);

        $raw = $file->content ?? '';
        if ($raw === '') {
            abort(404, 'ファイル内容がありません。');
        }

        if (str_starts_with($raw, 'data:')) {
            $parts = explode(',', $raw, 2);
            $raw = $parts[1] ?? $raw;
        }

        $binary = base64_decode($raw, true);
        if ($binary === false) {
            $binary = $raw;
        }

        $mimeType = $file->fileType ?: 'application/octet-stream';
        $filename = $file->documentName ?: ('file-' . $file->id);

        return response($binary, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function fetchAttachmentData($orderID): ?array
    {
        if (!ServiceRecord::where('orderID', $orderID)->exists()) {
            return null;
        }

        try {
            return [
                'notes' => AttachedNote::where('associatedID', $orderID)->get(),
                'files' => AttachedFile::where('associatedID', $orderID)
                    ->select(['id', 'associatedID', 'documentType', 'documentName', 'fileType', 'sortNum'])
                    ->orderBy('sortNum')
                    ->orderBy('id')
                    ->get(),
                'parts' => AttachedPart::where('associatedID', $orderID)
                    ->with('partMaster')
                    ->orderBy('id')
                    ->get(),
                'loaner' => ServiceRecord::where('parent_id', $orderID)->first(),
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'notes' => [],
                'files' => [],
                'parts' => [],
                'loaner' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function storeNote(Request $request)
    {
        $validated = $request->validate([
            'associatedID' => 'required|integer',
            'note' => 'required|string',
            'important' => 'nullable|boolean',
        ]);

        if (!ServiceRecord::where('orderID', $validated['associatedID'])->exists()) {
            return response()->json(['message' => '案件が見つかりません。'], 404);
        }

        $user = $request->user();
        $whoWrote = $user?->kanji_name ?: 'unknown';

        $note = AttachedNote::create([
            'associatedID' => $validated['associatedID'],
            'note' => $validated['note'],
            'whoWrote' => $whoWrote,
            'whenWrote' => now(),
            'important' => $request->boolean('important'),
        ]);

        return response()->json([
            'message' => 'Note を登録しました。',
            'note' => $note,
        ], 201);
    }

    public function updateNote(Request $request, $id)
    {
        $note = AttachedNote::findOrFail($id);

        if ($response = $this->assertNoteOwner($request, $note)) {
            return $response;
        }

        $validated = $request->validate([
            'note' => 'required|string',
            'important' => 'nullable|boolean',
        ]);

        $note->update([
            'note' => $validated['note'],
            'important' => $request->boolean('important'),
        ]);

        return response()->json([
            'message' => 'Note を更新しました。',
            'note' => $note->fresh(),
        ]);
    }

    public function destroyNote(Request $request, $id)
    {
        $note = AttachedNote::findOrFail($id);

        if ($response = $this->assertNoteOwner($request, $note)) {
            return $response;
        }

        $note->delete();

        return response()->json([
            'message' => 'Note を削除しました。',
        ]);
    }

    private function assertNoteOwner(Request $request, AttachedNote $note)
    {
        $user = $request->user();

        if (!$user || $note->whoWrote !== $user->kanji_name) {
            return response()->json([
                'message' => '自分が書いた Note のみ編集・削除できます。',
            ], 403);
        }

        return null;
    }

    public function storeFile(Request $request)
    {
        $validated = $request->validate([
            'associatedID' => 'required|integer',
            'file' => 'required|file|max:10240',
            'documentType' => 'required|string|max:255',
            'documentName' => 'nullable|string|max:255',
            'sortNum' => 'nullable|integer',
        ]);

        if (!ServiceRecord::where('orderID', $validated['associatedID'])->exists()) {
            return response()->json(['message' => '案件が見つかりません。'], 404);
        }

        $uploaded = $request->file('file');

        if (!$uploaded->isValid()) {
            return response()->json([
                'message' => 'ファイルのアップロードに失敗しました。',
            ], 422);
        }

        $content = base64_encode($uploaded->get());
        $fileType = $uploaded->getMimeType() ?: 'application/octet-stream';
        $documentName = $validated['documentName'] ?? $uploaded->getClientOriginalName();

        $file = AttachedFile::create([
            'associatedID' => $validated['associatedID'],
            'content' => $content,
            'documentType' => $validated['documentType'],
            'documentName' => $documentName,
            'fileType' => $fileType,
            'sortNum' => $validated['sortNum'] ?? null,
        ]);

        return response()->json([
            'message' => 'ファイルを登録しました。',
            'file' => $file->only(['id', 'associatedID', 'documentType', 'documentName', 'fileType', 'sortNum']),
        ], 201);
    }

    public function destroyFile(Request $request, $id)
    {
        $file = AttachedFile::findOrFail($id);
        $mode = $request->input('mode', 'delete');

        if ($mode === 'unlink') {
            $orderID = $request->input('orderID');
            if (!$orderID || (int) $file->associatedID !== (int) $orderID) {
                return response()->json([
                    'message' => 'この案件に関連付けられたファイルではありません。',
                ], 422);
            }

            $file->update(['associatedID' => -1]);

            return response()->json([
                'message' => 'ファイルの関連付けを削除しました。',
            ]);
        }

        $file->delete();

        return response()->json([
            'message' => 'ファイルをデータベースから削除しました。',
        ]);
    }

    public function storePart(Request $request)
    {
        $validated = $request->validate([
            'associatedID' => 'required|integer',
            'partID' => 'required|integer',
        ]);

        if (!ServiceRecord::where('orderID', $validated['associatedID'])->exists()) {
            return response()->json(['message' => '案件が見つかりません。'], 404);
        }

        if (!PartMaster::where('partID', $validated['partID'])->exists()) {
            return response()->json(['message' => '指定された部品が見つかりません。'], 404);
        }

        if (AttachedPart::where('associatedID', $validated['associatedID'])
            ->where('partID', $validated['partID'])
            ->exists()) {
            return response()->json(['message' => 'この部品は既に追加されています。'], 422);
        }

        $part = AttachedPart::create([
            'associatedID' => $validated['associatedID'],
            'partID' => $validated['partID'],
        ]);

        $part->load('partMaster');

        return response()->json([
            'message' => '部品を追加しました。',
            'part' => $part,
        ], 201);
    }

    public function destroyPart(Request $request, $id)
    {
        $part = AttachedPart::findOrFail($id);
        $part->delete();

        return response()->json([
            'message' => '部品を削除しました。',
        ]);
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

        if ($request->expectsJson()) {
            return response()->json([
                'message' => '更新しました。',
                'record' => $record->fresh(['returnCodeMaster', 'laborMaster', 'statusMaster']),
            ]);
        }

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
