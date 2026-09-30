<?php
namespace App\Domain\Obligations\Services;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentExtraction;
use App\Domain\Documents\Models\ExtractedField;
use App\Domain\Obligations\Models\Obligation;
use Carbon\CarbonImmutable;

/** Pure suggestions: never writes to the database. */
class ObligationSuggestionService
{
    public function suggest(Document $document): array
    {
        $extraction = $document->extractions()->with('fields')->orderByDesc('version_number')->first();
        if (!$extraction) return [];
        $trusted = $extraction->fields->filter(function (ExtractedField $field) {
            $status = $field->review_status instanceof \BackedEnum ? $field->review_status->value : (string) $field->review_status;
            return in_array($status, ['accepted', 'edited'], true);
        })->groupBy('field_name');
        $field = static function (string $key) use ($trusted): mixed {
            $item = $trusted->get($key)?->first();
            return $item ? ($item->normalized_value ?? $item->value) : null;
        };
        $string = static function (mixed $value): ?string {
            if (is_string($value) || is_numeric($value)) return trim((string) $value);
            return null;
        };
        $type = strtolower($string($field('document_type')) ?? '');
        $category = $document->category?->slug;
        $dueField = match (true) {
            $type === 'bill', $category === 'bill' => 'due_date',
            $type === 'insurance', $type === 'warranty', in_array($category, ['insurance','warranty','subscription'], true) => $field('renewal_date') !== null ? 'renewal_date' : 'end_date',
            $type === 'appointment', $category === 'appointment' => 'start_date',
            default => null,
        };
        $kind = match (true) {
            $type === 'bill', $category === 'bill' => 'payment',
            in_array($type, ['insurance', 'warranty'], true), in_array($category, ['insurance','warranty','subscription'], true) => 'renewal',
            $type === 'appointment', $category === 'appointment' => 'appointment',
            default => null,
        };
        if (!$kind || !$dueField) return [];
        // The due date must itself have been accepted or edited by a human.
        $rawDate = $string($field($dueField));
        if (!$rawDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) return [];
        try { $due = CarbonImmutable::createFromFormat('!Y-m-d', $rawDate, 'UTC'); }
        catch (\Throwable) { return []; }
        if (!$due || $due->format('Y-m-d') !== $rawDate) return [];
        $amountValue = $string($field('amount'));
        $amount = $amountValue && is_numeric(str_replace(',', '', $amountValue)) ? (float) str_replace(',', '', $amountValue) : null;
        $currency = $string($field('currency'));
        $title = match ($kind) {
            'payment' => 'Pay ' . $document->title,
            'renewal' => 'Renew ' . $document->title,
            default => 'Attend ' . $document->title,
        };
        $dedupe = ObligationService::fingerprint($document->household_id, $kind, $title, $rawDate, $document->id);
        if (Obligation::where('household_id', $document->household_id)->where('dedupe_key', $dedupe)->exists()) return [];
        return [[
            'type' => $kind, 'title' => $title, 'due_at' => $rawDate . 'T09:00:00Z',
            'amount' => $amount, 'currency' => $currency, 'document_id' => $document->id,
            'document_extraction_id' => $extraction->id, 'dedupe_key' => $dedupe,
            'source_evidence' => $trusted->flatten(1)->filter(fn ($f) => in_array($f->field_name, array_filter([$dueField, 'amount', 'currency']), true))
                ->map(fn ($f) => ['field_id' => $f->id, 'field_name' => $f->field_name, 'evidence_text' => $f->evidence_text])->values()->all(),
            'reminder_days' => [7, 2, 0],
        ]];
    }
}
