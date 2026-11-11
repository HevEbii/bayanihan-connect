<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Services\ReportBuilder;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** FR06 - consolidated cross-program reports. */
class ReportController extends Controller
{
    public function index(Request $request, ReportBuilder $builder)
    {
        $programs = Program::orderBy('name')->get();
        $report = null;

        if ($request->has('from')) {
            [$from, $to, $programIds] = $this->parameters($request);
            $report = $builder->build($from, $to, $programIds);
            Audit::record('report.generated', 'Generated consolidated report for '.$from->format('M j, Y').' to '.$to->format('M j, Y'));
        }

        return view('reports.index', compact('programs', 'report'));
    }

    public function export(Request $request, ReportBuilder $builder): StreamedResponse
    {
        [$from, $to, $programIds] = $this->parameters($request);
        $report = $builder->build($from, $to, $programIds);
        Audit::record('report.exported', 'Exported consolidated report for '.$from->format('M j, Y').' to '.$to->format('M j, Y'));

        $filename = 'bayanihan-connect-report-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows the peso sign correctly
            fputcsv($out, ['Bayanihan Connect - Consolidated Program Report']);
            fputcsv($out, ['Period', $report['from']->format('Y-m-d').' to '.$report['to']->format('Y-m-d')]);
            fputcsv($out, ['Generated', now()->format('Y-m-d H:i')]);
            fputcsv($out, []);
            fputcsv($out, ['Summary']);
            foreach ([
                'Cash received (PHP)' => number_format($report['totals']['cash_received'], 2, '.', ''),
                'Donations recorded' => $report['totals']['donations'],
                'Donors' => $report['totals']['donors'],
                'Distributions logged' => $report['totals']['distributions'],
                'Beneficiaries served' => $report['totals']['beneficiaries_served'],
                'Volunteers participating' => $report['totals']['volunteers'],
                'Volunteer hours (completed)' => $report['totals']['volunteer_hours'],
            ] as $label => $value) {
                fputcsv($out, [$label, $value]);
            }
            fputcsv($out, []);
            fputcsv($out, ['By program', 'Category', 'Cash received (PHP)', 'In-kind donations', 'Distributions', 'Beneficiaries served', 'Volunteers']);
            foreach ($report['by_program'] as $row) {
                fputcsv($out, [$row['program']->name, $row['program']->categoryLabel(), number_format($row['cash'], 2, '.', ''),
                    $row['in_kind_count'], $row['distributions'], $row['beneficiaries'], $row['volunteers']]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Goods received', 'Quantity', 'Unit']);
            foreach ($report['goods_received'] as $g) {
                fputcsv($out, [$g['item'], $g['quantity'], $g['unit']]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Goods distributed', 'Quantity', 'Unit']);
            foreach ($report['goods_distributed'] as $g) {
                fputcsv($out, [$g['item'], $g['quantity'], $g['unit']]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: Carbon, 1: Carbon, 2: array<int>} */
    private function parameters(Request $request): array
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'programs' => ['nullable', 'array'],
            'programs.*' => ['integer', 'exists:programs,id'],
        ], ['to.after_or_equal' => 'The end date must be on or after the start date.']);

        return [Carbon::parse($data['from'])->startOfDay(), Carbon::parse($data['to'])->endOfDay(), array_map('intval', $data['programs'] ?? [])];
    }
}
