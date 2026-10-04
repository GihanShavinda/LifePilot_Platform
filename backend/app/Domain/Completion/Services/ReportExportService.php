<?php

namespace App\Domain\Completion\Services;

use App\Domain\Users\Models\User;

class ReportExportService
{
    public function __construct(
        private readonly EvaluationService $evaluation,
        private readonly SimplePdfWriter $pdf,
    ) {}

    public function json(User $user): string
    {
        return json_encode($this->evaluation->summary($user), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    public function csv(User $user): string
    {
        $summary = $this->evaluation->summary($user);
        $rows = [['variant', 'metric_key', 'label', 'value', 'unit', 'status', 'sample_size', 'explanation']];

        foreach ($summary['comparison'] as $variant) {
            foreach ($variant['metrics'] as $metric) {
                $rows[] = [
                    $variant['variant'],
                    $metric['metric_key'],
                    $metric['label'],
                    $metric['value'] ?? '',
                    $metric['unit'] ?? '',
                    $metric['status'],
                    $metric['sample_size'],
                    $metric['explanation'],
                ];
            }
        }

        if (count($rows) === 1) {
            $rows[] = ['', '', 'No persisted evaluation run yet', '', '', 'not_measured', 0, 'Run the P12 evaluation first.'];
        }

        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);
        return $csv;
    }

    public function pdf(User $user): string
    {
        $summary = $this->evaluation->summary($user);
        $lines = [];
        $lines[] = 'Generated: '.now()->toIso8601String();
        $lines[] = 'Benchmark scores are shown only when ground truth or valid operational samples exist.';
        $lines[] = '';

        foreach ($summary['comparison'] as $variant) {
            $lines[] = strtoupper($variant['label']);
            $lines[] = 'Capabilities: '.implode(', ', $variant['capabilities']);
            foreach ($variant['metrics'] as $metric) {
                $value = $metric['value'] === null ? 'not measured' : $metric['value'].($metric['unit'] ?? '');
                $lines[] = '- '.$metric['label'].': '.$value.' ['.$metric['status'].'] n='.$metric['sample_size'];
            }
            $lines[] = '';
        }

        $lines[] = 'SECURITY REVIEW';
        foreach ($summary['security_review'] as $check) {
            $lines[] = '- '.$check['title'].': '.$check['status'];
        }

        return $this->pdf->render('LifePilot AI - P12 Final Evaluation Report', $lines);
    }
}
