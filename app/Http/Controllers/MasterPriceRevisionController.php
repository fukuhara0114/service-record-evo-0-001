<?php

namespace App\Http\Controllers;

use App\Services\MasterPriceRevisionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

class MasterPriceRevisionController extends Controller
{
    public function __construct(
        private readonly MasterPriceRevisionService $revisionService,
    ) {}

    public function index()
    {
        $snapshots = $this->revisionService->currentSnapshots();

        return Inertia::render('MasterPriceRevision', [
            'services' => $snapshots['services'],
            'parts' => $snapshots['parts'],
            'loaners' => $snapshots['loaners'],
            'meta' => $snapshots['meta'],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'effectiveDate' => 'required|date',
            'services' => 'nullable|array',
            'services.*.serviceID' => 'nullable',
            'services.*.productName' => 'nullable|string|max:100',
            'services.*.priceC_0' => 'nullable|numeric',
            'services.*.priceR_0' => 'nullable|numeric',
            'services.*.priceR_onSite' => 'nullable|numeric',
            'services.*.price_a2la' => 'nullable|numeric',
            'parts' => 'nullable|array',
            'parts.*.partID' => 'nullable',
            'parts.*.partName' => 'nullable|string|max:128',
            'parts.*.price_discounted' => 'nullable|numeric',
            'parts.*.price_market' => 'nullable|numeric',
            'parts.*.price_discounted_1' => 'nullable|numeric',
            'loaners' => 'nullable|array',
            'loaners.*.loanerID' => 'nullable',
            'loaners.*.productName' => 'nullable|string|max:100',
            'loaners.*.item' => 'nullable|string|max:100',
            'loaners.*.SN' => 'nullable|string|max:50',
            'loaners.*.manageNum' => 'nullable|string|max:11',
            'loaners.*.price' => 'nullable|numeric',
        ]);

        try {
            $result = $this->revisionService->revise($validated);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => '価格改定の保存に失敗しました。',
                'detail' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        $snapshots = $this->revisionService->currentSnapshots();
        $created = ($result['counts']['servicesCreated'] ?? 0)
            + ($result['counts']['partsCreated'] ?? 0)
            + ($result['counts']['loanersCreated'] ?? 0);

        $message = sprintf(
            '価格改定を保存しました（改定日: %s / service %d件・part %d件・loaner %d件）。',
            $result['effectiveDate'],
            $result['counts']['services'],
            $result['counts']['parts'],
            $result['counts']['loaners'],
        );
        if ($created > 0) {
            $message .= sprintf(
                ' 新規追加: service %d / part %d / loaner %d。',
                $result['counts']['servicesCreated'] ?? 0,
                $result['counts']['partsCreated'] ?? 0,
                $result['counts']['loanersCreated'] ?? 0,
            );
        }

        return response()->json([
            'message' => $message,
            'result' => $result,
            'services' => $snapshots['services'],
            'parts' => $snapshots['parts'],
            'loaners' => $snapshots['loaners'],
        ]);
    }
}
