<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $exports = ReportExport::query()
            ->where('created_by', $request->user()->getKey())
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => collect($exports->items())->map(static fn (ReportExport $export): array => [
                'id' => $export->id,
                'report' => $export->report,
                'status' => $export->status,
                'file_name' => $export->file_name,
                'created_at' => $export->created_at,
            ]),
            'meta' => [
                'current_page' => $exports->currentPage(),
                'last_page' => $exports->lastPage(),
                'total' => $exports->total(),
            ],
        ]);
    }

    public function download(Request $request, string $id): StreamedResponse
    {
        $export = ReportExport::query()
            ->where('created_by', $request->user()->getKey())
            ->whereKey($id)
            ->where('status', 'ready')
            ->firstOrFail();

        abort_unless(is_string($export->file_path) && Storage::disk('local')->exists($export->file_path), 404);

        return Storage::disk('local')->download($export->file_path, $export->file_name);
    }
}
