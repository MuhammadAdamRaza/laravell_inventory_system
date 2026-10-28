<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExporter
{
    /**
     * Stream query rows without materializing the complete result set in memory.
     *
     * @param  list<string>  $headers
     * @param  callable(object): array<int, mixed>  $mapRow
     */
    public function download(
        Builder $query,
        array $headers,
        callable $mapRow,
        string $filename,
    ): StreamedResponse {
        return response()->streamDownload(function () use ($query, $headers, $mapRow): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fputcsv($output, $headers, ',', '"', '');

            foreach ($query->cursor() as $row) {
                $cells = array_map(
                    fn (mixed $value): string => $this->escapeFormula($value),
                    $mapRow($row),
                );

                fputcsv($output, $cells, ',', '"', '');
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Write a report to private storage without loading all rows into memory.
     *
     * @param  list<string>  $headers
     * @param  callable(object): array<int, mixed>  $mapRow
     */
    public function store(Builder $query, array $headers, callable $mapRow, string $path): void
    {
        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');

        if ($stream === false) {
            throw new \RuntimeException('Unable to open a temporary export stream.');
        }

        try {
            fputcsv($stream, $headers, ',', '"', '');

            foreach ($query->cursor() as $row) {
                fputcsv($stream, array_map(
                    fn (mixed $value): string => $this->escapeFormula($value),
                    $mapRow($row),
                ), ',', '"', '');
            }

            rewind($stream);

            if (! Storage::disk('local')->writeStream($path, $stream)) {
                throw new \RuntimeException('Unable to store the generated report export.');
            }
        } finally {
            fclose($stream);
        }
    }

    private function escapeFormula(mixed $value): string
    {
        $cell = (string) ($value ?? '');

        return preg_match('/^[=+\\-@]/', $cell) === 1
            ? "'".$cell
            : $cell;
    }
}
