<?php

namespace App\Http\Controllers;

use App\Enums\ActionType;
use App\Models\ActionLog;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    public function exportFinancial(Request $request): StreamedResponse
    {
        try {
            $from = $request->query('from')
                ? Carbon::parse($request->query('from'))->startOfDay()
                : now()->subDays(30)->startOfDay();

            $to = $request->query('to')
                ? Carbon::parse($request->query('to'))->endOfDay()
                : now()->endOfDay();
        } catch (\Throwable) {
            abort(422, 'Неверный формат даты для параметров from или to.');
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        if ($from->diffInDays($to) > 366) {
            abort(422, 'Максимальный период экспорта — 366 дней.');
        }

        $siteId = $request->filled('site_id') ? (int) $request->query('site_id') : null;
        $rows = $this->analyticsService->getFinancialExportRows($from, $to, $siteId);

        $actor = auth()->user() ?? $request->attributes->get('user');
        ActionLog::create([
            'actor_id' => $actor?->id,
            'site_id' => $siteId,
            'action_type' => ActionType::EXPORT,
            'field_name' => 'financial_export',
            'old_value' => null,
            'new_value' => sprintf(
                'Экспорт финансов: %s — %s (строк: %d%s)',
                $from->toDateString(),
                $to->toDateString(),
                count($rows),
                $siteId ? ", сайт ID: {$siteId}" : ''
            ),
            'created_at' => now(),
        ]);

        $filename = sprintf('financial_%s_%s.csv', $from->format('Y-m-d'), $to->format('Y-m-d'));

        return new StreamedResponse(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Дата',
                'Сайт',
                'Выручка',
                'Себестоимость',
                'Выплаты курьерам',
                'Непредвиденные расходы',
                'Чистая прибыль',
            ], ';');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $this->sanitizeCsvCell($row['date']),
                    $this->sanitizeCsvCell($row['site_name']),
                    $this->sanitizeCsvCell($row['revenue']),
                    $this->sanitizeCsvCell($row['purchase']),
                    $this->sanitizeCsvCell($row['courier']),
                    $this->sanitizeCsvCell($row['unforeseen']),
                    $this->sanitizeCsvCell($row['net_profit']),
                ], ';');
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function sanitizeCsvCell(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
