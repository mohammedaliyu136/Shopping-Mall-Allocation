<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** SRS 6.17 - reporting and analytics. */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(): View
    {
        return view('admin.reports.index', ['stats' => $this->reports->dashboard()]);
    }

    /** FR-160 - allocation reports. */
    public function allocations(Request $request): View
    {
        [$from, $to] = $this->period($request);

        return view('admin.reports.allocations', [
            'report' => $this->reports->allocationReport(),
            'history' => $this->reports->allocationHistory($from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** FR-161 - financial reports. */
    public function financial(Request $request): View
    {
        [$from, $to] = $this->period($request);

        return view('admin.reports.financial', [
            'report' => $this->reports->financialReport($from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** FR-162 - owner reports. */
    public function owners(): View
    {
        return view('admin.reports.owners', [
            'report' => $this->reports->ownerReport(),
            'outstanding' => $this->reports->ownersWithOutstanding(),
        ]);
    }

    /** FR-163 - support reports. */
    public function support(): View
    {
        return view('admin.reports.support', ['report' => $this->reports->supportReport()]);
    }

    /** Export any report as CSV for offline analysis. */
    public function export(Request $request, string $report): StreamedResponse
    {
        [$from, $to] = $this->period($request);

        $rows = match ($report) {
            'allocations' => $this->allocationRows($from, $to),
            'financial' => $this->flatten($this->reports->financialReport($from, $to)),
            'owners' => $this->outstandingRows(),
            'support' => $this->flatten($this->reports->supportReport()),
            default => abort(404, 'Unknown report.'),
        };

        $filename = sprintf('%s-report-%s.csv', $report, Carbon::now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'wb');

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array<int, array<int, string>> */
    private function allocationRows(?Carbon $from, ?Carbon $to): array
    {
        $rows = [['Reference', 'Owner', 'Shop', 'Block', 'Allocation Date', 'Status']];

        foreach ($this->reports->allocationHistory($from, $to) as $allocation) {
            $rows[] = [
                $allocation->reference_number,
                $allocation->owner?->full_name ?? '',
                $allocation->shop?->shop_number ?? '',
                $allocation->shop?->block?->name ?? '',
                $allocation->allocation_date?->toDateString() ?? '',
                $allocation->status,
            ];
        }

        return $rows;
    }

    /** @return array<int, array<int, string>> */
    private function outstandingRows(): array
    {
        $rows = [['Owner Code', 'Owner', 'Phone', 'Email', 'Outstanding']];

        foreach ($this->reports->ownersWithOutstanding() as $owner) {
            $rows[] = [
                $owner->owner_code,
                $owner->full_name,
                (string) $owner->phone,
                (string) $owner->email,
                number_format((float) $owner->outstanding, 2, '.', ''),
            ];
        }

        return $rows;
    }

    /**
     * Turns a report array into two-column CSV rows, expanding nested breakdowns.
     *
     * @param  array<string, mixed>  $report
     * @return array<int, array<int, string>>
     */
    private function flatten(array $report): array
    {
        $rows = [['Measure', 'Value']];

        foreach ($report as $key => $value) {
            $label = ucwords(str_replace('_', ' ', $key));

            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    if (is_scalar($subValue) || $subValue === null) {
                        $rows[] = [$label.' - '.ucwords(str_replace('_', ' ', (string) $subKey)), (string) $subValue];
                    }
                }

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $rows[] = [$label, (string) $value];
            }
        }

        return $rows;
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function period(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->string('from')->toString()) : null;
        $to = $request->filled('to') ? Carbon::parse($request->string('to')->toString()) : null;

        return [$from, $to];
    }
}
