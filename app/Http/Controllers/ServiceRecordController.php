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
use App\Models\StockedPartMaster;
use App\Models\AttachedStockedPart;
use App\Models\UnregisteredEmailNote;
use App\Models\CapturedImage;
use App\Services\EmlReplyDraftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
    public function administrator(Request $request)
    {
        return $this->renderServiceRecordList($request, 'admin');
    }

    // engineer用表示（自分の laborID の案件のみ）
    public function engineer(Request $request)
    {
        return $this->renderServiceRecordList($request, 'engineer');
    }

    private function renderServiceRecordList(Request $request, string $mode)
    {
        // 添付データだけ欲しい Inertia 部分リロード（一覧は再取得しない）
        if ($request->header('X-Inertia') && $request->header('X-Inertia-Partial-Data') === 'attachmentData') {
            $attachmentData = null;
            if ($request->filled('loadOrderID')) {
                $orderID = $request->input('loadOrderID');
                if ($mode === 'engineer' && !$this->engineerCanAccessOrder($orderID)) {
                    abort(403, 'この案件を表示する権限がありません。');
                }
                $attachmentData = $this->fetchAttachmentData($orderID);
            }

            return Inertia::render('ServiceRecordList', [
                'attachmentData' => $attachmentData,
                'mode' => $mode,
            ]);
        }

        $query = ServiceRecord::with(['returnCodeMaster', 'laborMaster', 'statusMaster', 'statusMasterLoaner']);

        if ($mode === 'engineer') {
            $query->where('status', '>=', 90)
                ->where('status', '<=', 170)
                ->where(function ($typeQuery) {
                    $typeQuery->whereIn('order_type', ['service', 'loaner'])
                        ->orWhereNull('order_type');
                });

            $laborID = auth()->user()?->laborID;
            if ($laborID === null || $laborID === '') {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('laborID', $laborID);
            }
        } else {
            $query->where('status', '>=', 0)
                ->where('status', '<', 399);
        }

        $records = $query->orderBy('receivedDate', 'asc')->get();

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
            ->select(['id', 'serviceID', 'productName', 'entityID', 'priceC_0', 'priceR_0', 'priceR_onSite', 'price_a2la'])
            ->where('productName', 'NOT LIKE', '*%')
            ->orderBy('productName')
            ->get();
        $partsMaster = PartMaster::query()
            ->select(['partID', 'partName', 'description', 'price_discounted', 'type'])
            ->orderBy('partName')
            ->get();
        $stockedPartsMaster = StockedPartMaster::query()
            ->select(['partID', 'partName', 'description'])
            ->orderBy('partName')
            ->get();

        $attachmentData = null;
        if ($request->filled('loadOrderID')) {
            $orderID = $request->input('loadOrderID');
            if ($mode === 'engineer' && !$this->engineerCanAccessOrder($orderID)) {
                abort(403, 'この案件を表示する権限がありません。');
            }
            $attachmentData = $this->fetchAttachmentData($orderID);
        }

        return Inertia::render('ServiceRecordList', [
            'initialRecords' => $records,
            'statuses' => $statuses,
            'statusesLoaner' => $statusesLoaner,
            'returnCodes' => $returnCodes,
            'labors' => $labors,
            'dealersMaster' => $dealers,
            'servicesMaster' => $services,
            'partsMaster' => $partsMaster,
            'stockedPartsMaster' => $stockedPartsMaster,
            'mode' => $mode,
            'attachmentData' => $attachmentData,
        ]);
    }

    private function engineerCanAccessOrder($orderID): bool
    {
        $laborID = auth()->user()?->laborID;
        if ($laborID === null || $laborID === '') {
            return false;
        }

        return ServiceRecord::query()
            ->where('orderID', $orderID)
            ->where('laborID', $laborID)
            ->exists();
    }

    private function isEngineerRequest(Request $request): bool
    {
        if ($request->header('X-List-Mode') === 'engineer' || $request->input('listMode') === 'engineer') {
            return true;
        }

        $referer = (string) $request->headers->get('referer', '');
        return str_contains($referer, '/servicerecord/engineer');
    }

    private function assertEngineerAccessIfNeeded(Request $request, $orderID): void
    {
        if (!$this->isEngineerRequest($request)) {
            return;
        }

        if (!$this->engineerCanAccessOrder($orderID)) {
            abort(403, 'この案件を表示する権限がありません。');
        }
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

    public function record(Request $request, $orderID)
    {
        $this->assertEngineerAccessIfNeeded($request, $orderID);

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

    public function attachments(Request $request, $orderID)
    {
        $this->assertEngineerAccessIfNeeded($request, $orderID);

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

    public function camera()
    {
        return Inertia::render('CameraCapture', [
            'imageMaxEdge' => (int) config('captured_image.max_edge', 1024),
            'jpegQuality' => (int) config('captured_image.jpeg_quality', 90),
        ]);
    }

    public function gallery()
    {
        return Inertia::render('ImageGallery');
    }

    public function listCapturedImages(Request $request)
    {
        $validated = $request->validate([
            'associatedID' => 'nullable|integer',
            'captured_by' => 'nullable|string|max:64',
            'period' => 'nullable|string|in:today,1d,3d,7d,1m,3m,all,custom',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 48);
        $query = CapturedImage::query()->orderByDesc('captured_at')->orderByDesc('id');

        if (array_key_exists('associatedID', $validated) && $validated['associatedID'] !== null) {
            $query->where('associatedID', (int) $validated['associatedID']);
        }

        $capturedBy = trim((string) ($validated['captured_by'] ?? ''));
        if ($capturedBy !== '') {
            $query->where('captured_by', $capturedBy);
        }

        [$dateFrom, $dateTo] = $this->resolveCapturedImageDateRange(
            $validated['period'] ?? null,
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null,
        );

        if ($dateFrom !== null) {
            $query->where('captured_at', '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $query->where('captured_at', '<=', $dateTo);
        }

        $paginator = $query->paginate($perPage);

        $data = collect($paginator->items())
            ->map(fn (CapturedImage $item) => $this->serializeCapturedImage($item))
            ->values();

        $capturedByOptions = CapturedImage::query()
            ->whereNotNull('captured_by')
            ->where('captured_by', '!=', '')
            ->distinct()
            ->orderBy('captured_by')
            ->pluck('captured_by')
            ->values();

        return response()->json([
            'data' => $data,
            'captured_by_options' => $capturedByOptions,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function uploadCameraImage(Request $request)
    {
        try {
            $validated = $request->validate([
                'file' => 'required|file|mimes:jpeg,jpg,png,webp|max:15360',
                'title' => 'nullable|string|max:255',
                'associatedID' => 'nullable|integer',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('camera upload validation failed', [
                'errors' => $e->errors(),
                'has_file' => $request->hasFile('file'),
                'content_type' => $request->header('Content-Type'),
                'gd' => extension_loaded('gd'),
            ]);

            return response()->json([
                'message' => '入力内容を確認してください。',
                'errors' => $e->errors(),
                'error' => collect($e->errors())->flatten()->implode(' '),
            ], 422);
        }

        $uploaded = $request->file('file');
        if (!$uploaded || !$uploaded->isValid()) {
            \Log::warning('camera upload file invalid', [
                'error_code' => $uploaded?->getError(),
            ]);

            return response()->json([
                'message' => 'ファイルのアップロードに失敗しました。',
                'error' => 'upload_error_code=' . ($uploaded?->getError() ?? 'null'),
            ], 422);
        }

        $binary = file_get_contents($uploaded->getRealPath());
        if ($binary === false || $binary === '') {
            return response()->json([
                'message' => '画像データの読み込みに失敗しました。',
            ], 422);
        }

        // クライアント側で既に縮小・JPEG化済み。GD がある場合のみサーバーでも再正規化する。
        try {
            if ($this->supportsCapturedImageGd()) {
                $binary = $this->normalizeCapturedImageJpeg(
                    $binary,
                    (int) config('captured_image.max_edge', 1024),
                    (int) config('captured_image.jpeg_quality', 90),
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('camera upload normalize failed, saving original binary', [
                'error' => $e->getMessage(),
            ]);
            // 再圧縮に失敗しても、クライアント圧縮済みバイナリのまま保存を続ける
        }

        $fileName = $this->makeCapturedImageFileName($request->user());
        $imageDir = $this->capturedImageDir('image');
        $thumbDir = $this->capturedImageDir('thumbnail');

        if (!is_dir($imageDir) && !mkdir($imageDir, 0775, true) && !is_dir($imageDir)) {
            return response()->json(['message' => '画像保存先ディレクトリを作成できませんでした。'], 500);
        }
        if (!is_dir($thumbDir) && !mkdir($thumbDir, 0775, true) && !is_dir($thumbDir)) {
            return response()->json(['message' => 'サムネイル保存先ディレクトリを作成できませんでした。'], 500);
        }

        $imagePath = $imageDir . DIRECTORY_SEPARATOR . $fileName;
        $thumbPath = $thumbDir . DIRECTORY_SEPARATOR . $fileName;

        if (file_put_contents($imagePath, $binary) === false) {
            return response()->json(['message' => '画像ファイルの保存に失敗しました。'], 500);
        }

        try {
            if ($this->supportsCapturedImageGd()) {
                $this->createCapturedImageThumbnail(
                    $binary,
                    $thumbPath,
                    (int) config('captured_image.thumbnail_max_edge', 320),
                );
            } else {
                // GD が無い場合は本体をサムネとしてもコピー（クライアント側で既に縮小済み）
                if (file_put_contents($thumbPath, $binary) === false) {
                    throw new \RuntimeException('サムネイルのコピーに失敗しました。');
                }
            }
        } catch (\Throwable $e) {
            @unlink($imagePath);
            return response()->json([
                'message' => 'サムネイルの作成に失敗しました。',
                'error' => $e->getMessage(),
            ], 500);
        }

        $title = trim((string) ($validated['title'] ?? ''));
        if ($title === '') {
            $title = 'camera-' . now()->format('Ymd-His');
        }

        $capturedBy = Str::limit((string) ($request->user()?->kanji_name ?: 'unknown'), 8, '');
        $associatedID = array_key_exists('associatedID', $validated)
            ? (int) $validated['associatedID']
            : -1;

        $record = CapturedImage::create([
            'title' => $title,
            'file_name' => $fileName,
            'captured_at' => now(),
            'associatedID' => $associatedID,
            'captured_by' => $capturedBy,
        ]);

        return response()->json([
            'message' => '撮影画像を保存しました。',
            'image' => [
                'id' => $record->id,
                'title' => $record->title,
                'file_name' => $record->file_name,
                'captured_at' => optional($record->captured_at)?->format('Y-m-d H:i:s'),
                'associatedID' => $record->associatedID,
                'captured_by' => $record->captured_by,
                'image_url' => route('servicerecord.camera.image', ['fileName' => $record->file_name]),
                'thumbnail_url' => route('servicerecord.camera.thumbnail', ['fileName' => $record->file_name]),
            ],
        ], 201);
    }

    public function editCapturedImage(Request $request)
    {
        try {
            $validated = $request->validate([
                'file' => 'required|file|mimes:jpeg,jpg,png,webp|max:15360',
                'source_id' => 'nullable|integer|exists:captured_image,id',
                'title' => 'nullable|string|max:255',
                'associatedID' => 'nullable|integer',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('camera edit validation failed', [
                'errors' => $e->errors(),
                'has_file' => $request->hasFile('file'),
                'content_type' => $request->header('Content-Type'),
                'gd' => extension_loaded('gd'),
            ]);

            return response()->json([
                'message' => '入力内容を確認してください。',
                'errors' => $e->errors(),
                'error' => collect($e->errors())->flatten()->implode(' '),
            ], 422);
        }

        $uploaded = $request->file('file');
        if (!$uploaded || !$uploaded->isValid()) {
            \Log::warning('camera edit file invalid', [
                'error_code' => $uploaded?->getError(),
            ]);

            return response()->json([
                'message' => 'ファイルのアップロードに失敗しました。',
                'error' => 'upload_error_code=' . ($uploaded?->getError() ?? 'null'),
            ], 422);
        }

        $binary = file_get_contents($uploaded->getRealPath());
        if ($binary === false || $binary === '') {
            return response()->json([
                'message' => '画像データの読み込みに失敗しました。',
            ], 422);
        }

        try {
            if ($this->supportsCapturedImageGd()) {
                $binary = $this->normalizeCapturedImageJpeg(
                    $binary,
                    (int) config('captured_image.max_edge', 1024),
                    (int) config('captured_image.jpeg_quality', 90),
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('camera edit normalize failed, saving original binary', [
                'error' => $e->getMessage(),
            ]);
        }

        $fileName = $this->makeCapturedImageFileName($request->user(), '_edit');
        $imageDir = $this->capturedImageDir('image');
        $thumbDir = $this->capturedImageDir('thumbnail');

        if (!is_dir($imageDir) && !mkdir($imageDir, 0775, true) && !is_dir($imageDir)) {
            return response()->json(['message' => '画像保存先ディレクトリを作成できませんでした。'], 500);
        }
        if (!is_dir($thumbDir) && !mkdir($thumbDir, 0775, true) && !is_dir($thumbDir)) {
            return response()->json(['message' => 'サムネイル保存先ディレクトリを作成できませんでした。'], 500);
        }

        $imagePath = $imageDir . DIRECTORY_SEPARATOR . $fileName;
        $thumbPath = $thumbDir . DIRECTORY_SEPARATOR . $fileName;

        if (file_put_contents($imagePath, $binary) === false) {
            return response()->json(['message' => '画像ファイルの保存に失敗しました。'], 500);
        }

        try {
            if ($this->supportsCapturedImageGd()) {
                $this->createCapturedImageThumbnail(
                    $binary,
                    $thumbPath,
                    (int) config('captured_image.thumbnail_max_edge', 320),
                );
            } else {
                if (file_put_contents($thumbPath, $binary) === false) {
                    throw new \RuntimeException('サムネイルのコピーに失敗しました。');
                }
            }
        } catch (\Throwable $e) {
            @unlink($imagePath);
            return response()->json([
                'message' => 'サムネイルの作成に失敗しました。',
                'error' => $e->getMessage(),
            ], 500);
        }

        $source = null;
        if (!empty($validated['source_id'])) {
            $source = CapturedImage::find((int) $validated['source_id']);
        }

        $title = trim((string) ($validated['title'] ?? ''));
        if ($title === '') {
            if ($source) {
                $sourceTitle = trim((string) $source->title);
                $title = ($sourceTitle !== '' ? $sourceTitle : 'camera-' . now()->format('Ymd-His')) . ' (編集)';
            } else {
                $title = 'camera-' . now()->format('Ymd-His') . ' (編集)';
            }
        }

        if ($source) {
            $associatedID = (int) $source->associatedID;
            $capturedBy = (string) $source->captured_by;
        } else {
            $capturedBy = Str::limit((string) ($request->user()?->kanji_name ?: 'unknown'), 8, '');
            $associatedID = array_key_exists('associatedID', $validated)
                ? (int) $validated['associatedID']
                : -1;
        }

        $record = CapturedImage::create([
            'title' => $title,
            'file_name' => $fileName,
            'captured_at' => now(),
            'associatedID' => $associatedID,
            'captured_by' => $capturedBy,
        ]);

        return response()->json([
            'message' => '編集画像を保存しました。',
            'image' => [
                'id' => $record->id,
                'title' => $record->title,
                'file_name' => $record->file_name,
                'captured_at' => optional($record->captured_at)?->format('Y-m-d H:i:s'),
                'associatedID' => $record->associatedID,
                'captured_by' => $record->captured_by,
                'image_url' => route('servicerecord.camera.image', ['fileName' => $record->file_name]),
                'thumbnail_url' => route('servicerecord.camera.thumbnail', ['fileName' => $record->file_name]),
            ],
        ], 201);
    }

    public function associateCapturedImages(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:captured_image,id',
            'associatedID' => 'required|integer',
        ]);

        $associatedID = (int) $validated['associatedID'];
        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));

        if ($associatedID === -1) {
            $updated = CapturedImage::query()
                ->whereIn('id', $ids)
                ->update(['associatedID' => -1]);

            return response()->json([
                'message' => "{$updated} 件の撮影画像の紐づけを解除しました。",
                'updated' => $updated,
                'associatedID' => -1,
            ]);
        }

        if (!ServiceRecord::where('orderID', $associatedID)->exists()) {
            return response()->json(['message' => '指定された案件は存在しません。'], 404);
        }

        $this->assertEngineerAccessIfNeeded($request, $associatedID);

        $updated = CapturedImage::query()
            ->whereIn('id', $ids)
            ->update(['associatedID' => $associatedID]);

        return response()->json([
            'message' => "{$updated} 件の撮影画像を案件に紐づけました。",
            'updated' => $updated,
            'associatedID' => $associatedID,
        ]);
    }

    public function disassociateCapturedImages(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:captured_image,id',
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));
        $images = CapturedImage::query()->whereIn('id', $ids)->get(['id', 'associatedID']);

        foreach ($images as $image) {
            $orderID = (int) $image->associatedID;
            if ($orderID > 0) {
                $this->assertEngineerAccessIfNeeded($request, $orderID);
            }
        }

        $updated = CapturedImage::query()
            ->whereIn('id', $ids)
            ->update(['associatedID' => -1]);

        return response()->json([
            'message' => "{$updated} 件の撮影画像の紐づけを解除しました。",
            'updated' => $updated,
            'associatedID' => -1,
        ]);
    }

    public function showCapturedImage(string $fileName)
    {
        return $this->serveCapturedImageFile($fileName, 'image');
    }

    public function showCapturedThumbnail(string $fileName)
    {
        return $this->serveCapturedImageFile($fileName, 'thumbnail');
    }

    private function serializeCapturedImage(CapturedImage $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'file_name' => $item->file_name,
            'captured_at' => optional($item->captured_at)?->format('Y-m-d H:i:s'),
            'associatedID' => $item->associatedID,
            'captured_by' => $item->captured_by,
            'image_url' => route('servicerecord.camera.image', ['fileName' => $item->file_name]),
            'thumbnail_url' => route('servicerecord.camera.thumbnail', ['fileName' => $item->file_name]),
        ];
    }

    private function serveCapturedImageFile(string $fileName, string $subdir)
    {
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $fileName)) {
            abort(404);
        }

        if (!CapturedImage::where('file_name', $fileName)->exists()) {
            abort(404);
        }

        $path = $this->capturedImageDir($subdir) . DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function capturedImageRoot(): string
    {
        return (string) config('captured_image.root', storage_path('app/uploadedImage'));
    }

    private function capturedImageDir(string $subdir): string
    {
        return $this->capturedImageRoot() . DIRECTORY_SEPARATOR . $subdir;
    }

    /**
     * Resolve Asia/Tokyo calendar-day bounds for captured_at filtering.
     * Custom date_from/date_to take precedence; otherwise period (default: today).
     *
     * @return array{0: \Illuminate\Support\Carbon|null, 1: \Illuminate\Support\Carbon|null}
     */
    private function resolveCapturedImageDateRange(?string $period, ?string $dateFrom, ?string $dateTo): array
    {
        $tz = 'Asia/Tokyo';
        $now = now($tz);

        $hasCustomFrom = is_string($dateFrom) && $dateFrom !== '';
        $hasCustomTo = is_string($dateTo) && $dateTo !== '';

        if ($hasCustomFrom || $hasCustomTo) {
            $from = $hasCustomFrom
                ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $dateFrom, $tz)->startOfDay()
                : null;
            $to = $hasCustomTo
                ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $dateTo, $tz)->endOfDay()
                : null;

            if ($from !== null && $to !== null && $from->gt($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }

            return [
                $from?->copy()->utc(),
                $to?->copy()->utc(),
            ];
        }

        $period = $period ?: 'today';

        if ($period === 'all') {
            return [null, null];
        }

        $to = $now->copy()->endOfDay();

        $from = match ($period) {
            '1d' => $now->copy()->subDay()->startOfDay(),
            '3d' => $now->copy()->subDays(3)->startOfDay(),
            '7d' => $now->copy()->subDays(7)->startOfDay(),
            '1m' => $now->copy()->subMonth()->startOfDay(),
            '3m' => $now->copy()->subMonths(3)->startOfDay(),
            default => $now->copy()->startOfDay(), // today
        };

        return [$from->utc(), $to->utc()];
    }

    private function supportsCapturedImageGd(): bool
    {
        return extension_loaded('gd')
            && function_exists('imagecreatefromstring')
            && function_exists('imagejpeg')
            && function_exists('imagecreatetruecolor');
    }

    private function makeCapturedImageFileName(?\App\Models\User $user, string $nameSuffix = ''): string
    {
        $safeUser = $this->resolveCapturedImageUserName($user);
        // ファイル名の日時は日本時間で統一（例: 2026-08-05-08-24-05_hfukuhara.jpg）
        // $nameSuffix 例: '_edit' → 2026-08-05-08-24-05_hfukuhara_edit.jpg
        $base = now('Asia/Tokyo')->format('Y-m-d-H-i-s') . '_' . $safeUser;
        $fileName = $base . $nameSuffix . '.jpg';
        $suffix = 1;

        while (
            CapturedImage::where('file_name', $fileName)->exists()
            || file_exists($this->capturedImageDir('image') . DIRECTORY_SEPARATOR . $fileName)
            || file_exists($this->capturedImageDir('thumbnail') . DIRECTORY_SEPARATOR . $fileName)
        ) {
            $fileName = $base . '-' . $suffix . $nameSuffix . '.jpg';
            $suffix++;
        }

        return $fileName;
    }

    private function resolveCapturedImageUserName(?\App\Models\User $user): string
    {
        if (!$user) {
            return 'user';
        }

        $candidates = [
            (string) ($user->name ?? ''),
            $user->email ? (string) Str::before((string) $user->email, '@') : '',
            (isset($user->laborID) && (int) $user->laborID > 0) ? ('labor' . (int) $user->laborID) : '',
        ];

        foreach ($candidates as $candidate) {
            $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $candidate) ?? '';
            if ($safe !== '') {
                return $safe;
            }
        }

        return 'user';
    }

    private function normalizeCapturedImageJpeg(string $binary, int $maxEdge = 1024, int $quality = 90): string
    {
        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            throw new \RuntimeException('画像ファイルとして認識できませんでした。');
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        if ($srcW < 1 || $srcH < 1) {
            imagedestroy($source);
            throw new \RuntimeException('画像サイズが不正です。');
        }

        $scale = min(1, $maxEdge / max($srcW, $srcH));
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));

        $canvas = imagecreatetruecolor($dstW, $dstH);
        if ($canvas === false) {
            imagedestroy($source);
            throw new \RuntimeException('画像キャンバスを作成できませんでした。');
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($source);

        ob_start();
        $ok = imagejpeg($canvas, null, $quality);
        $jpeg = ob_get_clean();
        imagedestroy($canvas);

        if (!$ok || $jpeg === false || $jpeg === '') {
            throw new \RuntimeException('JPEG 圧縮に失敗しました。');
        }

        return $jpeg;
    }

    private function createCapturedImageThumbnail(string $binary, string $destination, int $maxSize = 320): void
    {
        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            throw new \RuntimeException('画像リソースを作成できませんでした。');
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        if ($srcW < 1 || $srcH < 1) {
            imagedestroy($source);
            throw new \RuntimeException('画像サイズが不正です。');
        }

        $scale = min(1, $maxSize / max($srcW, $srcH));
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));

        $thumb = imagecreatetruecolor($dstW, $dstH);
        if ($thumb === false) {
            imagedestroy($source);
            throw new \RuntimeException('サムネイルキャンバスを作成できませんでした。');
        }

        $white = imagecolorallocate($thumb, 255, 255, 255);
        imagefill($thumb, 0, 0, $white);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

        $saved = imagejpeg($thumb, $destination, 85);
        imagedestroy($source);
        imagedestroy($thumb);

        if (!$saved) {
            throw new \RuntimeException('サムネイル JPEG の書き込みに失敗しました。');
        }
    }

    public function createWithoutFile()
    {
        return $this->renderCreateFromFilePage(null);
    }

    public function uploadForIntake(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|max:10240',
            'documentType' => 'nullable|string|max:255',
            'documentName' => 'nullable|string|max:255',
            'sortNum' => 'nullable|integer',
        ]);

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

        $documentType = $validated['documentType'] ?? $this->guessDocumentType($originalName, $fileType);

        $file = AttachedFile::create([
            'associatedID' => -1,
            'content' => $content,
            'documentType' => $documentType,
            'documentName' => $documentName,
            'fileType' => $fileType,
            'sortNum' => $validated['sortNum'] ?? null,
        ]);

        return response()->json([
            'message' => 'ファイルを登録しました。',
            'file' => $file->only(['id', 'associatedID', 'documentType', 'documentName', 'fileType', 'sortNum']),
        ], 201);
    }

    public function createFromFile($fileId)
    {
        $sourceFile = AttachedFile::query()
            ->select(['id', 'associatedID', 'documentType', 'documentName', 'fileType', 'sortNum'])
            ->where('associatedID', -1)
            ->findOrFail($fileId);

        return $this->renderCreateFromFilePage($sourceFile);
    }

    private function guessDocumentType(string $originalName, string $fileType): string
    {
        $name = strtolower($originalName);
        $type = strtolower($fileType);

        if (str_ends_with($name, '.eml') || str_ends_with($name, '.msg')
            || str_contains($type, 'message') || str_contains($type, 'ms-outlook')) {
            return 'メール';
        }
        if ($type === 'application/pdf' || str_ends_with($name, '.pdf')) {
            return 'PDF';
        }
        if (str_starts_with($type, 'image/') || preg_match('/\.(png|jpe?g|gif|webp|bmp|tiff?)$/i', $name)) {
            return '画像';
        }

        return '添付ファイル';
    }

    private function renderCreateFromFilePage($sourceFile)
    {
        $statuses = \App\Models\Status::orderBy('processID')->get();
        $returnCodes = \App\Models\ReturnCode::all();
        $labors = \App\Models\Labor::all();
        $dealers = Dealer::orderBy('dealerName')->get();
        $services = ServiceMaster::query()
            ->select(['id', 'serviceID', 'productName', 'entityID'])
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

            $capturedImages = CapturedImage::query()
                ->where('associatedID', $orderID)
                ->orderByDesc('captured_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (CapturedImage $item) => $this->serializeCapturedImage($item))
                ->values();

            return [
                'notes' => AttachedNote::where('associatedID', $orderID)->get(),
                'files' => AttachedFile::where('associatedID', $orderID)
                    ->select(['id', 'associatedID', 'documentType', 'documentName', 'fileType', 'sortNum'])
                    ->orderByRaw('CASE WHEN sortNum IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('sortNum')
                    ->orderBy('id')
                    ->get(),
                'capturedImages' => $capturedImages,
                'parts' => AttachedPart::where('associatedID', $orderID)
                    ->with('partMaster')
                    ->orderBy('id')
                    ->get(),
                'stockedParts' => AttachedStockedPart::where('associatedID', $orderID)
                    ->with('stockedPartMaster')
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
                'capturedImages' => [],
                'parts' => [],
                'stockedParts' => [],
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

    private function fetchUnregisteredEmailNotes()
    {
        return UnregisteredEmailNote::query()
            ->orderByDesc('whenWrote')
            ->orderByDesc('id')
            ->get();
    }

    public function listUnregisteredEmailNotes()
    {
        return response()->json([
            'notes' => $this->fetchUnregisteredEmailNotes(),
        ]);
    }

    public function linkUnregisteredEmailNote(Request $request, $id)
    {
        $validated = $request->validate([
            'orderID' => 'required|integer',
        ]);

        $emailNote = UnregisteredEmailNote::findOrFail($id);

        if (!ServiceRecord::where('orderID', $validated['orderID'])->exists()) {
            return response()->json(['message' => '案件が見つかりません。'], 404);
        }

        $whoWrote = Str::limit((string) ($request->user()?->kanji_name ?: 'unknown'), 100, '');

        $notePayload = [
            'associatedID' => $validated['orderID'],
            'note' => $emailNote->mailLink,
            'whoWrote' => $whoWrote,
            'whenWrote' => $emailNote->whenWrote ?? now(),
            'important' => false,
        ];

        if (Schema::hasColumn('attachednotes', 'personal')) {
            $notePayload['personal'] = false;
        }

        $attachedNote = DB::transaction(function () use ($emailNote, $notePayload) {
            $created = AttachedNote::create($notePayload);
            $emailNote->delete();

            return $created;
        });

        return response()->json([
            'message' => '案件の Notes にメールリンクを紐づけました。',
            'note' => $attachedNote,
        ]);
    }

    public function destroyUnregisteredEmailNote($id)
    {
        $emailNote = UnregisteredEmailNote::findOrFail($id);
        $emailNote->delete();

        return response()->json([
            'message' => '未登録メール Note を削除しました。',
        ]);
    }

    public function storeNote(Request $request)
    {
        $validated = $request->validate([
            'associatedID' => 'required|integer',
            'note' => 'required|string',
            'important' => 'nullable|boolean',
            'personal' => 'nullable|boolean',
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
            'personal' => $request->boolean('personal'),
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

    public function storeStockedPart(Request $request)
    {
        $validated = $request->validate([
            'associatedID' => 'required|integer',
            'partID' => 'required|integer',
            'quantity' => 'required|integer|min:1',
        ]);

        if (!ServiceRecord::where('orderID', $validated['associatedID'])->exists()) {
            return response()->json(['message' => '案件が見つかりません。'], 404);
        }

        if (!StockedPartMaster::where('partID', $validated['partID'])->exists()) {
            return response()->json(['message' => '指定された在庫部品が見つかりません。'], 404);
        }

        if (AttachedStockedPart::where('associatedID', $validated['associatedID'])
            ->where('partID', $validated['partID'])
            ->exists()) {
            return response()->json(['message' => 'この在庫部品は既に追加されています。'], 422);
        }

        $this->assertEngineerAccessIfNeeded($request, $validated['associatedID']);

        $part = AttachedStockedPart::create([
            'associatedID' => $validated['associatedID'],
            'partID' => $validated['partID'],
            'quantity' => $validated['quantity'],
        ]);

        $part->load('stockedPartMaster');

        return response()->json([
            'message' => '在庫部品を追加しました。',
            'stockedPart' => $part,
        ], 201);
    }

    public function updateStockedPart(Request $request, $id)
    {
        $part = AttachedStockedPart::findOrFail($id);
        $this->assertEngineerAccessIfNeeded($request, $part->associatedID);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $part->update([
            'quantity' => $validated['quantity'],
        ]);

        $part->load('stockedPartMaster');

        return response()->json([
            'message' => '使用数を更新しました。',
            'stockedPart' => $part,
        ]);
    }

    public function destroyStockedPart(Request $request, $id)
    {
        $part = AttachedStockedPart::findOrFail($id);
        $this->assertEngineerAccessIfNeeded($request, $part->associatedID);
        $part->delete();

        return response()->json([
            'message' => '在庫部品を削除しました。',
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
        $this->assertEngineerAccessIfNeeded($request, $orderID);

        $record = ServiceRecord::findOrFail($orderID);

        $data = $request->except(['_token', '_method', 'allow_over_capacity']);

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

    /**
     * 出荷予定カレンダー画面
     */
    public function shippingCalendar()
    {
        $returnCodes = \App\Models\ReturnCode::all();
        $labors = \App\Models\Labor::all();

        return Inertia::render('ShippingCalendar', [
            'returnCodes' => $returnCodes,
            'labors' => $labors,
        ]);
    }

    /**
     * 出荷予定カレンダー用イベント（status >= 300 かつ shippingOut_requiredDate あり）
     */
    public function shippingCalendarEvents(Request $request)
    {
        $validated = $request->validate([
            'start' => 'required|date',
            'end' => 'required|date',
        ]);

        $capacity = $this->shippingDailyCapacity();

        $records = ServiceRecord::query()
            ->whereNotNull('shippingOut_requiredDate')
            ->where('shippingOut_requiredDate', '!=', '')
            ->where('status', '>=', 300)
            ->whereDate('shippingOut_requiredDate', '>=', $validated['start'])
            ->whereDate('shippingOut_requiredDate', '<', $validated['end'])
            ->orderBy('shippingOut_requiredDate')
            ->orderBy('orderID')
            ->limit(2000)
            ->get([
                'orderID',
                'SN',
                'productName',
                'dealer',
                'dealer_depart',
                'contactPerson',
                'returnCode',
                'a2la',
                'status',
                'shippingOut_requiredDate',
            ]);

        $counts = [];
        $events = $records->map(function (ServiceRecord $row) use (&$counts) {
            $date = $this->normalizeDateString($row->shippingOut_requiredDate);
            if (!$date) {
                return null;
            }

            $counts[$date] = ($counts[$date] ?? 0) + 1;

            $titleParts = array_filter([
                (string) $row->orderID,
                $row->SN,
                $row->productName,
                $row->dealer,
                $row->dealer_depart,
                $row->contactPerson,
            ], fn ($part) => $part !== null && $part !== '');

            return [
                'id' => (string) $row->orderID,
                'title' => implode(' / ', $titleParts) ?: ('Order ' . $row->orderID),
                'start' => $date,
                'allDay' => true,
                'editable' => true,
                'extendedProps' => [
                    'orderID' => $row->orderID,
                    'SN' => $row->SN,
                    'productName' => $row->productName,
                    'dealer' => $row->dealer,
                    'dealer_depart' => $row->dealer_depart,
                    'contactPerson' => $row->contactPerson,
                    'returnCode' => $row->returnCode,
                    'a2la' => $row->a2la,
                    'status' => $row->status,
                    'shippingOut_requiredDate' => $date,
                    'pending' => false,
                ],
            ];
        })->filter()->values();

        return response()->json([
            'events' => $events,
            'counts' => $counts,
            'capacity' => $capacity,
        ]);
    }

    /**
     * 1日あたりの出荷予定台数上限（目安）。config/shipping.php で変更する。
     */
    private function shippingDailyCapacity(): int
    {
        $value = (int) config('shipping.daily_capacity', 8);
        return $value > 0 ? $value : 8;
    }

    private function normalizeDateString($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        $raw = substr((string) $value, 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            try {
                return (new \DateTimeImmutable((string) $value))->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }
        return $raw;
    }

    // 6. 削除
    public function destroy($orderID)
    {
        $record = ServiceRecord::findOrFail($orderID);
        $record->delete();

        return redirect()->route('servicerecord.index')->with('success', '削除しました。');
    }
}
