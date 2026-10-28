<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FailedJobController extends Controller
{
    public function index(): JsonResponse
    {
        $jobs = DB::table('failed_jobs')
            ->select(['uuid', 'connection', 'queue', 'failed_at', 'exception'])
            ->latest('failed_at')
            ->paginate(20);

        return response()->json([
            'data' => collect($jobs->items())->map(static fn (object $job): array => [
                'uuid' => $job->uuid,
                'connection' => $job->connection,
                'queue' => $job->queue,
                'failed_at' => $job->failed_at,
                'summary' => Str::limit(Str::before($job->exception, "\n"), 240),
            ]),
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'total' => $jobs->total(),
            ],
        ]);
    }

    public function retry(string $uuid): JsonResponse
    {
        if (! DB::table('failed_jobs')->where('uuid', $uuid)->exists()) {
            throw new NotFoundHttpException;
        }

        Artisan::call('queue:retry', [
            'id' => [$uuid],
            '--no-interaction' => true,
        ]);

        return response()->json(['message' => 'The failed job was returned to its original queue.']);
    }
}
