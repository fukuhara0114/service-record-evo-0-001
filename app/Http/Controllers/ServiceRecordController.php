<?php

namespace App\Http\Controllers;

use App\Models\ServiceRecord;
use App\Models\ServiceMaster;
use App\Models\Dealer;
use App\Models\PartMaster;
use App\Models\AttachedNote;
use App\Models\AttachedFile;
use App\Models\AttachedPart;
use App\Models\AttachedLoaner;
use App\Services\EmlReplyDraftService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use ZBateson\MailMimeParser\Message;

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

        $records = ServiceRecord::with(['returnCodeMaster', 'laborMaster', 'statusMaster', 'statusMasterLoaner'])
            ->where('status', '>=', 0)
            ->where('status', '<', 399)
            ->orderBy('receivedDate', 'asc')
            ->get();

        $records->each(function (ServiceRecord $record) {
            if ($record->order_type === 'loaner') {
                $record->unsetRelation('statusMaster');
            } elseif ($record->order_type === 'waiting_list') {
                $record->unsetRelation('statusMaster');
                $record->unsetRelation('statusMasterLoaner');
            } else {
                $record->unsetRelation('statusMasterLoaner');
            }
        });


        $statuses = \App\Models\Status::orderBy('processID')->get();
        $statusesLoaner = \App\Models\StatusLoaner::orderBy('processID')->get();
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
                    'statusesLoaner' => $statusesLoaner,
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

        $record = ServiceRecord::with(['statusMaster', 'statusMasterLoaner', 'laborMaster'])
                    ->where('orderID', $orderID)
                    ->first(); // 1件だけ取得

        $loaner_case = ServiceRecord::where('parentID', $orderID)->first();

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
        $statusesLoaner = \App\Models\StatusLoaner::orderBy('processID')->get();
        $returnCodes = \App\Models\ReturnCode::all(); 
        $labors = \App\Models\Labor::all();
        $dealers = \App\Models\Dealer::orderBy('dealerName')->get();
        $parts = \App\Models\PartMaster::all(); 
        $services = \App\Models\ServiceMaster::all();

        return Inertia::render('ServiceRecords.detail', [
                    'initialRecord' => $record,
                    'statuses'       => $statuses,
                    'statusesLoaner' => $statusesLoaner,
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
        $record = ServiceRecord::with(['returnCodeMaster', 'laborMaster', 'statusMaster', 'statusMasterLoaner'])
            ->where('orderID', $orderID)
            ->first();

        if (!$record) {
            return response()->json(['message' => '指定された案件は存在しません。'], 404);
        }

        if ($record->order_type === 'loaner') {
            $record->unsetRelation('statusMaster');
        } elseif ($record->order_type === 'waiting_list') {
            $record->unsetRelation('statusMaster');
            $record->unsetRelation('statusMasterLoaner');
        } else {
            $record->unsetRelation('statusMasterLoaner');
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

    public function searchExisting(Request $request)
    {
        $tokens = collect([
            $request->input('productName'),
            $request->input('SN'),
            $request->input('dealer'),
            $request->input('contactPerson'),
        ])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values();

        if ($tokens->isEmpty()) {
            return response()->json([
                'records' => [],
            ]);
        }

        $forLoanerParent = $request->input('for') === 'loaner_parent';
        $orderTypeFilter = $request->input('order_type'); // service | loaner

        $with = ['returnCodeMaster', 'laborMaster', 'statusMaster'];
        if ($orderTypeFilter === 'loaner' || $orderTypeFilter === 'waiting_list') {
            $with = ['returnCodeMaster', 'laborMaster', 'statusMasterLoaner'];
        }

        $query = ServiceRecord::with($with);

        if ($forLoanerParent || $orderTypeFilter === 'service') {
            // service 案件（レガシーの order_type 未設定も含む）
            $query->where(function ($q) {
                $q->where('order_type', 'service')
                    ->orWhereNull('order_type')
                    ->orWhere('order_type', '');
            });
            if (!$forLoanerParent) {
                $query->where('status', '<', 399)
                    ->where('status', '>', -1);
            }
        } elseif ($orderTypeFilter === 'loaner') {
            $query->where('order_type', 'loaner');
        } elseif ($orderTypeFilter === 'waiting_list') {
            $query->where('order_type', 'waiting_list');
        } else {
            $query->where('status', '<', 399)
                ->where('status', '>', -1);
        }

        $records = $query
            ->where(function ($outerQuery) use ($tokens) {
                foreach ($tokens as $token) {
                    $outerQuery->where(function ($tokenQuery) use ($token) {
                        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $token) . '%';
                        $tokenQuery
                            ->where('productName', 'like', $like)
                            ->orWhere('SN', 'like', $like)
                            ->orWhere('dealer', 'like', $like)
                            ->orWhere('contactPerson', 'like', $like)
                            ->orWhere('orderID', 'like', $like);
                    });
                }
            })
            ->orderBy('receivedDate', 'desc')
            ->limit(100)
            ->get();

        $records->each(function (ServiceRecord $record) {
            if ($record->order_type === 'loaner') {
                $record->unsetRelation('statusMaster');
            } elseif ($record->order_type === 'waiting_list') {
                $record->unsetRelation('statusMaster');
                $record->unsetRelation('statusMasterLoaner');
            } else {
                $record->unsetRelation('statusMasterLoaner');
            }
        });

        return response()->json([
            'records' => $records,
        ]);
    }

    public function linkToExisting(Request $request)
    {
        $validated = $request->validate([
            'orderID' => 'required|integer',
            'sourceFileId' => 'required|integer',
            'additionalFileIds' => 'nullable|array',
            'additionalFileIds.*' => 'integer',
            'receivedDate' => 'nullable|date',
            'status' => 'nullable|integer',
        ]);

        $record = ServiceRecord::where('orderID', $validated['orderID'])->first();
        if (!$record) {
            return response()->json([
                'message' => '指定された案件は存在しません。',
            ], 404);
        }

        $fileIds = collect([$validated['sourceFileId']])
            ->merge($validated['additionalFileIds'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $files = AttachedFile::query()
            ->whereIn('id', $fileIds)
            ->where('associatedID', -1)
            ->get();

        if ($files->count() !== $fileIds->count()) {
            return response()->json([
                'message' => '未登録ファイルの状態が変わったため、画面を再読み込みしてやり直してください。',
            ], 422);
        }

        $updateData = [];
        if (array_key_exists('receivedDate', $validated)) {
            $updateData['receivedDate'] = $validated['receivedDate'];
        }
        if (array_key_exists('status', $validated) && $validated['status'] !== null) {
            $updateData['status'] = $validated['status'];
        }

        if ($updateData !== []) {
            $record->update($updateData);
        }

        AttachedFile::query()
            ->whereIn('id', $fileIds)
            ->where('associatedID', -1)
            ->update(['associatedID' => $record->orderID]);

        return response()->json([
            'message' => '選択した案件にファイルを紐付けました。',
            'record' => $record->fresh(['returnCodeMaster', 'laborMaster', 'statusMaster']),
        ]);
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

    public function emlPreview($fileId)
    {
        $file = AttachedFile::findOrFail($fileId);

        if (!$this->isEmlFile($file)) {
            return response()->json([
                'message' => 'EML ファイルではありません。',
            ], 422);
        }

        try {
            $binary = $this->decodeAttachedFileBinary($file);
            $message = Message::from($binary, false);

            $attachments = [];
            foreach ($message->getAllAttachmentParts() as $index => $part) {
                $stream = $part->getBinaryContentStream();
                $content = $stream ? $stream->getContents() : '';
                $attachments[] = [
                    'index' => $index,
                    'filename' => $part->getFilename() ?: ('attachment-' . ($index + 1)),
                    'contentType' => $part->getContentType('application/octet-stream'),
                    'size' => strlen($content),
                ];
            }

            $bodyHtml = $message->getHtmlContent();
            $bodyText = $message->getTextContent();

            return response()->json([
                'subject' => $message->getHeaderValue('Subject') ?: '(件名なし)',
                'from' => $message->getHeaderValue('From') ?: '',
                'to' => $message->getHeaderValue('To') ?: '',
                'cc' => $message->getHeaderValue('Cc') ?: '',
                'date' => $message->getHeaderValue('Date') ?: '',
                'bodyText' => $bodyText ?: '',
                'bodyHtml' => $bodyHtml ? $this->sanitizeEmailHtml($bodyHtml) : '',
                'attachments' => $attachments,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'EML の解析に失敗しました: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function emlAttachment($fileId, $index)
    {
        $file = AttachedFile::findOrFail($fileId);

        if (!$this->isEmlFile($file)) {
            abort(422, 'EML ファイルではありません。');
        }

        $binary = $this->decodeAttachedFileBinary($file);
        $message = Message::from($binary, false);
        $parts = $message->getAllAttachmentParts();
        $part = $parts[(int) $index] ?? null;

        if (!$part) {
            abort(404, '添付ファイルが見つかりません。');
        }

        $stream = $part->getBinaryContentStream();
        $content = $stream ? $stream->getContents() : '';
        $filename = $part->getFilename() ?: ('attachment-' . ((int) $index + 1));
        $mimeType = $part->getContentType('application/octet-stream');

        return response($content, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . addslashes($filename) . '"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function emlReplyDraft(Request $request, $fileId, EmlReplyDraftService $draftService)
    {
        $validated = $request->validate([
            'templateType' => 'required|string|in:receipt,quote,work_change',
            'orderID' => 'nullable|integer',
        ]);

        $file = AttachedFile::findOrFail($fileId);
        if (!$this->isEmlFile($file)) {
            return response()->json([
                'message' => 'EML ファイルではありません。',
            ], 422);
        }

        $orderID = $validated['orderID'] ?? $file->associatedID;
        $record = ServiceRecord::query()->where('orderID', $orderID)->first();
        if (!$record) {
            return response()->json([
                'message' => '案件が見つかりません。',
            ], 404);
        }

        try {
            $binary = $this->decodeAttachedFileBinary($file);
            $draft = $draftService->buildDraftEml($binary, $record, $validated['templateType']);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'メールドラフトの作成に失敗しました: ' . $e->getMessage(),
            ], 422);
        }

        return response($draft['content'], 200, [
            'Content-Type' => 'message/rfc822',
            'Content-Disposition' => 'attachment; filename="' . addslashes($draft['filename']) . '"',
            'Cache-Control' => 'no-store, private',
            'X-Draft-Subject' => rawurlencode($draft['subject']),
            'X-Draft-To' => rawurlencode($draft['to']),
            'X-Draft-Template' => $draft['templateType'],
        ]);
    }

    private function isEmlFile(AttachedFile $file): bool
    {
        $name = strtolower((string) ($file->documentName ?? ''));
        $type = strtolower((string) ($file->fileType ?? ''));
        $docType = strtolower((string) ($file->documentType ?? ''));

        return str_ends_with($name, '.eml')
            || str_contains($type, 'message/rfc822')
            || $type === 'application/eml'
            || $type === 'message/rfc822'
            || ($docType === 'メール' && str_ends_with($name, '.eml'));
    }

    private function decodeAttachedFileBinary(AttachedFile $file): string
    {
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

        return $binary;
    }

    private function sanitizeEmailHtml(string $html): string
    {
        $html = preg_replace('#<(script|iframe|object|embed|link|meta)[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<(script|iframe|object|embed|link|meta)[^>]*/?>#is', '', $html) ?? $html;
        $html = preg_replace('/\son\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2/i', '$1="#"', $html) ?? $html;

        return $html;
    }

    public function intakeList()
    {
        return Inertia::render('ServiceRecordIntakeList', [
            'unregisteredFiles' => $this->fetchUnregisteredFiles(),
        ]);
    }

    public function createWithoutFile()
    {
        return $this->renderCreateFromFilePage(null);
    }

    public function createFromFile($fileId)
    {
        $sourceFile = AttachedFile::query()
            ->select(['id', 'associatedID', 'documentType', 'documentName', 'fileType', 'sortNum'])
            ->where('associatedID', -1)
            ->findOrFail($fileId);

        return $this->renderCreateFromFilePage($sourceFile);
    }

    private function renderCreateFromFilePage($sourceFile)
    {
        $statuses = \App\Models\Status::orderBy('processID')->get();
        $returnCodes = \App\Models\ReturnCode::all();
        $labors = \App\Models\Labor::all();
        $dealers = Dealer::orderBy('dealerName')->get();
        $services = ServiceMaster::query()
            ->select(['serviceID', 'productName', 'entityID'])
            ->where('productName', 'NOT LIKE', '*%')
            ->orderBy('productName')
            ->get();

        return Inertia::render('ServiceRecordCreateFromFile', [
            'sourceFile' => $sourceFile,
            'unregisteredFiles' => $this->fetchUnregisteredFiles(),
            'statuses' => $statuses,
            'returnCodes' => $returnCodes,
            'labors' => $labors,
            'dealersMaster' => $dealers,
            'servicesMaster' => $services,
        ]);
    }

    private function fetchAttachmentData($orderID): ?array
    {
        if (!ServiceRecord::where('orderID', $orderID)->exists()) {
            return null;
        }

        try {
            $linkedLoaners = ServiceRecord::query()
                ->with(['statusMasterLoaner'])
                ->where('parentID', $orderID)
                ->whereIn('order_type', ['loaner', 'waiting_list'])
                ->orderBy('orderID')
                ->get()
                ->map(function (ServiceRecord $loaner) {
                    $attached = AttachedLoaner::query()
                        ->where('associatedID', $loaner->orderID)
                        ->orderByDesc('id')
                        ->first();

                    return [
                        'orderID' => $loaner->orderID,
                        'order_type' => $loaner->order_type,
                        'status' => $loaner->status,
                        'status_label' => $loaner->order_type === 'waiting_list'
                            ? null
                            : ($loaner->statusMasterLoaner?->status),
                        'productName' => $loaner->productName,
                        'SN' => $loaner->SN,
                        'loanerID' => $loaner->loanerID,
                        'dealer' => $loaner->dealer,
                        'dealer_depart' => $loaner->dealer_depart,
                        'contactPerson' => $loaner->contactPerson,
                        'parentID' => $loaner->parentID,
                        'attachedLoanerId' => $attached?->id,
                        'plannedSentDate' => optional($attached?->plannedSentDate ?? $attached?->sentDate)->format('Y-m-d'),
                        'plannedReturnedDate' => optional($attached?->plannedReturnedDate ?? $attached?->returnedDate)->format('Y-m-d'),
                    ];
                })
                ->values();

            return [
                'notes' => AttachedNote::where('associatedID', $orderID)->get(),
                'files' => AttachedFile::where('associatedID', $orderID)
                    ->select(['id', 'associatedID', 'documentType', 'documentName', 'fileType', 'sortNum'])
                    ->orderByRaw('CASE WHEN sortNum IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('sortNum')
                    ->orderBy('id')
                    ->get(),
                'parts' => AttachedPart::where('associatedID', $orderID)
                    ->with('partMaster')
                    ->orderBy('id')
                    ->get(),
                'loaner' => $linkedLoaners->first(),
                'loaners' => $linkedLoaners,
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'notes' => [],
                'files' => [],
                'parts' => [],
                'loaner' => null,
                'loaners' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    private function fetchUnregisteredFiles()
    {
        return AttachedFile::query()
            ->select(['id', 'associatedID', 'documentType', 'documentName', 'fileType', 'sortNum'])
            ->where('associatedID', -1)
            ->orderBy('id')
            ->get();
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
            'personal' => false,
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
        $originalName = strtolower((string) $uploaded->getClientOriginalName());
        if (str_ends_with($originalName, '.eml') && !str_contains(strtolower($fileType), 'rfc822')) {
            $fileType = 'message/rfc822';
        }
        if (str_ends_with($originalName, '.msg') && $fileType === 'application/octet-stream') {
            $fileType = 'application/vnd.ms-outlook';
        }

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

    public function updateFile(Request $request, $id)
    {
        $file = AttachedFile::findOrFail($id);

        $validated = $request->validate([
            'sortNum' => 'nullable|integer',
            'documentName' => 'nullable|string|max:255',
            'documentType' => 'nullable|string|max:255',
        ]);

        $updates = [];
        if (array_key_exists('sortNum', $validated)) {
            $updates['sortNum'] = $validated['sortNum'];
        }
        if (array_key_exists('documentName', $validated)) {
            $updates['documentName'] = $validated['documentName'];
        }
        if (array_key_exists('documentType', $validated)) {
            $updates['documentType'] = $validated['documentType'];
        }

        if ($updates === []) {
            return response()->json([
                'message' => '更新する項目がありません。',
            ], 422);
        }

        $file->update($updates);

        return response()->json([
            'message' => 'ファイル情報を更新しました。',
            'file' => $file->fresh()->only(['id', 'associatedID', 'documentType', 'documentName', 'fileType', 'sortNum']),
        ]);
    }

    public function updateFileContent(Request $request, $id)
    {
        $file = AttachedFile::findOrFail($id);

        $validated = $request->validate([
            'content' => 'required|string',
            'fileType' => 'nullable|string|max:255',
        ]);

        $raw = $validated['content'];
        if (str_starts_with($raw, 'data:')) {
            $parts = explode(',', $raw, 2);
            $raw = $parts[1] ?? '';
        }

        if ($raw === '' || base64_decode($raw, true) === false) {
            return response()->json([
                'message' => 'PDFデータの形式が不正です。',
            ], 422);
        }

        $file->update([
            'content' => $raw,
            'fileType' => $validated['fileType'] ?? ($file->fileType ?: 'application/pdf'),
        ]);

        return response()->json([
            'message' => 'ファイルを上書き保存しました。',
            'file' => $file->only(['id', 'associatedID', 'documentType', 'documentName', 'fileType', 'sortNum']),
        ]);
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

    public function storeFromIntake(Request $request)
    {
        $validated = $request->validate([
            'sourceFileId' => 'nullable|integer',
            'additionalFileIds' => 'nullable|array',
            'additionalFileIds.*' => 'integer',
            'receivedDate' => 'nullable|date',
            'status' => 'nullable|integer',
            'serviceID' => 'required|integer',
            'SN' => 'nullable|string|max:255',
            'returnCode' => 'nullable|integer',
            'dealer' => 'nullable|string|max:255',
            'dealer_depart' => 'nullable|string|max:255',
            'contactPerson' => 'nullable|string|max:255',
            'email' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'zipcode' => 'nullable|string|max:20',
            'address1' => 'nullable|string|max:255',
            'address2' => 'nullable|string|max:255',
            'endUser' => 'nullable|string|max:255',
            'endUser_depart' => 'nullable|string|max:255',
            'endUser_contactPerson' => 'nullable|string|max:255',
            'endUser_phone' => 'nullable|string|max:255',
            'endUser_email' => 'nullable|string|max:255',
            'endUser_zipcode' => 'nullable|string|max:20',
            'endUser_address1' => 'nullable|string|max:255',
            'endUser_address2' => 'nullable|string|max:255',
            'deliveryDestination_company' => 'nullable|string|max:255',
            'deliveryDestination_depart' => 'nullable|string|max:255',
            'deliveryDestination_contactPerson' => 'nullable|string|max:255',
            'deliveryDestination_phone' => 'nullable|string|max:255',
            'deliveryDestination_zipcode' => 'nullable|string|max:20',
            'deliveryDestination_address1' => 'nullable|string|max:255',
            'deliveryDestination_address2' => 'nullable|string|max:255',
            'loanerOrderIds' => 'nullable|array',
            'loanerOrderIds.*' => 'integer',
        ]);

        $fileIds = collect()
            ->when(!empty($validated['sourceFileId']), fn ($c) => $c->push((int) $validated['sourceFileId']))
            ->merge($validated['additionalFileIds'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($fileIds->isNotEmpty()) {
            $files = AttachedFile::query()
                ->whereIn('id', $fileIds)
                ->where('associatedID', -1)
                ->get();

            if ($files->count() !== $fileIds->count()) {
                return response()->json([
                    'message' => '未登録ファイルの状態が変わったため、画面を再読み込みしてやり直してください。',
                ], 422);
            }
        }

        $service = ServiceMaster::query()
            ->select(['serviceID', 'productName', 'entityID'])
            ->where('serviceID', $validated['serviceID'])
            ->first();

        if (!$service) {
            return response()->json([
                'message' => '指定された serviceID が見つかりません。',
            ], 404);
        }

        $loanerOrderIds = collect($validated['loanerOrderIds'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($loanerOrderIds->isNotEmpty()) {
            if (
                blank($validated['SN'] ?? null)
                || blank($validated['dealer'] ?? null)
                || blank($validated['contactPerson'] ?? null)
            ) {
                return response()->json([
                    'message' => 'loaner 紐づけで新規案件を作成するには SN / dealer / contactPerson が必要です。',
                ], 422);
            }

            $loaners = ServiceRecord::query()
                ->whereIn('orderID', $loanerOrderIds)
                ->whereIn('order_type', ['loaner', 'waiting_list'])
                ->get();

            if ($loaners->count() !== $loanerOrderIds->count()) {
                return response()->json([
                    'message' => '紐づけ対象の貸出案件が見つからないか、loaner/waiting_list ではありません。',
                ], 422);
            }

            $alreadyLinked = $loaners->first(fn (ServiceRecord $row) => !empty($row->parentID));
            if ($alreadyLinked) {
                return response()->json([
                    'message' => "貸出案件 orderID {$alreadyLinked->orderID} は既に他案件へ紐づいています。",
                ], 422);
            }
        }

        $user = $request->user();

        $record = \Illuminate\Support\Facades\DB::transaction(function () use (
            $validated,
            $service,
            $user,
            $fileIds,
            $loanerOrderIds,
        ) {
            $record = ServiceRecord::create([
                'receivedDate' => $validated['receivedDate'] ?? null,
                'status' => $validated['status'] ?? null,
                'serviceID' => $service->serviceID,
                'productName' => $service->productName,
                'entityID' => $service->entityID,
                'SN' => $validated['SN'] ?? null,
                'returnCode' => $validated['returnCode'] ?? null,
                'dealer' => $validated['dealer'] ?? null,
                'dealer_depart' => $validated['dealer_depart'] ?? null,
                'contactPerson' => $validated['contactPerson'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'zipcode' => $validated['zipcode'] ?? null,
                'address1' => $validated['address1'] ?? null,
                'address2' => $validated['address2'] ?? null,
                'endUser' => $validated['endUser'] ?? null,
                'endUser_depart' => $validated['endUser_depart'] ?? null,
                'endUser_contactPerson' => $validated['endUser_contactPerson'] ?? null,
                'endUser_phone' => $validated['endUser_phone'] ?? null,
                'endUser_email' => $validated['endUser_email'] ?? null,
                'endUser_zipcode' => $validated['endUser_zipcode'] ?? null,
                'endUser_address1' => $validated['endUser_address1'] ?? null,
                'endUser_address2' => $validated['endUser_address2'] ?? null,
                'deliveryDestination_company' => $validated['deliveryDestination_company'] ?? null,
                'deliveryDestination_depart' => $validated['deliveryDestination_depart'] ?? null,
                'deliveryDestination_contactPerson' => $validated['deliveryDestination_contactPerson'] ?? null,
                'deliveryDestination_phone' => $validated['deliveryDestination_phone'] ?? null,
                'deliveryDestination_zipcode' => $validated['deliveryDestination_zipcode'] ?? null,
                'deliveryDestination_address1' => $validated['deliveryDestination_address1'] ?? null,
                'deliveryDestination_address2' => $validated['deliveryDestination_address2'] ?? null,
                'order_type' => 'service',
                'lastEditPerson' => $user?->kanji_name,
                'lastEditDate' => now(),
            ]);

            if ($fileIds->isNotEmpty()) {
                AttachedFile::query()
                    ->whereIn('id', $fileIds)
                    ->where('associatedID', -1)
                    ->update(['associatedID' => $record->orderID]);
            }

            if ($loanerOrderIds->isNotEmpty()) {
                ServiceRecord::query()
                    ->whereIn('orderID', $loanerOrderIds)
                    ->whereIn('order_type', ['loaner', 'waiting_list'])
                    ->where(function ($q) {
                        $q->whereNull('parentID')->orWhere('parentID', 0);
                    })
                    ->update([
                        'parentID' => $record->orderID,
                        'lastEditPerson' => $user?->kanji_name,
                        'lastEditDate' => now(),
                    ]);
            }

            return $record;
        });

        return response()->json([
            'message' => '新規案件を登録しました。',
            'record' => $record->fresh(['returnCodeMaster', 'laborMaster', 'statusMaster']),
            'linkedLoanerIds' => $loanerOrderIds->values(),
        ], 201);
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
            $freshRelations = ['returnCodeMaster', 'laborMaster'];
            if ($record->order_type === 'loaner') {
                $freshRelations[] = 'statusMasterLoaner';
            } else {
                $freshRelations[] = 'statusMaster';
            }

            return response()->json([
                'message' => '更新しました。',
                'record' => $record->fresh($freshRelations),
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
