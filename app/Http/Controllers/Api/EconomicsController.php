<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FarmField;
use App\Models\FieldTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EconomicsController extends Controller
{
    public function listTransactions(Request $request, int $fieldId): JsonResponse
    {
        $field = $this->ownedField($request, $fieldId);

        $transactions = FieldTransaction::where('user_id', $request->user()->id)
            ->where('farm_field_id', $field->id)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (FieldTransaction $t) => $this->serializeTransaction($t));

        return response()->json(['transactions' => $transactions]);
    }

    public function createTransaction(Request $request, int $fieldId): JsonResponse
    {
        $field = $this->ownedField($request, $fieldId);

        $validated = $request->validate([
            'type' => ['required', 'in:EXPENSE,INCOME'],
            'category' => ['required', 'in:SEED,FERTILIZER,LABOR,HARVEST_SALE,OTHER'],
            'amount' => ['required', 'numeric'],
            'quantity' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string', 'max:50'],
            'occurredOn' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'clientUuid' => ['nullable', 'uuid'],
        ]);

        if (! empty($validated['clientUuid'])) {
            $existing = FieldTransaction::where('user_id', $request->user()->id)
                ->where('client_uuid', $validated['clientUuid'])
                ->first();
            if ($existing) {
                return response()->json([
                    'transaction' => $this->serializeTransaction($existing),
                    'idempotent' => true,
                ], 200);
            }
        }

        $transaction = FieldTransaction::create([
            'user_id' => $request->user()->id,
            'farm_field_id' => $field->id,
            'client_uuid' => $validated['clientUuid'] ?? null,
            'type' => $validated['type'],
            'category' => $validated['category'],
            'amount' => $validated['amount'],
            'quantity' => $validated['quantity'] ?? null,
            'unit' => $validated['unit'] ?? null,
            'occurred_on' => $validated['occurredOn'],
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json([
            'transaction' => $this->serializeTransaction($transaction),
        ], 201);
    }

    public function updateTransaction(Request $request, int $id): JsonResponse
    {
        $transaction = FieldTransaction::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'type' => ['nullable', 'in:EXPENSE,INCOME'],
            'category' => ['nullable', 'in:SEED,FERTILIZER,LABOR,HARVEST_SALE,OTHER'],
            'amount' => ['nullable', 'numeric'],
            'quantity' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string', 'max:50'],
            'occurredOn' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $update = [];
        if (isset($validated['type'])) $update['type'] = $validated['type'];
        if (isset($validated['category'])) $update['category'] = $validated['category'];
        if (isset($validated['amount'])) $update['amount'] = $validated['amount'];
        if (array_key_exists('quantity', $validated)) $update['quantity'] = $validated['quantity'];
        if (array_key_exists('unit', $validated)) $update['unit'] = $validated['unit'];
        if (isset($validated['occurredOn'])) $update['occurred_on'] = $validated['occurredOn'];
        if (array_key_exists('note', $validated)) $update['note'] = $validated['note'];

        $transaction->update($update);

        return response()->json([
            'message' => 'Transaction updated.',
            'transaction' => $this->serializeTransaction($transaction->fresh()),
        ]);
    }

    public function deleteTransaction(Request $request, int $id): JsonResponse
    {
        FieldTransaction::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail()
            ->delete();

        return response()->json(['message' => 'Transaction deleted.']);
    }

    public function fieldEconomics(Request $request, int $fieldId): JsonResponse
    {
        $field = $this->ownedField($request, $fieldId);
        $areaM2 = max((float) $field->area_m2, 0.0);

        $rows = FieldTransaction::where('user_id', $request->user()->id)
            ->where('farm_field_id', $field->id)
            ->get();

        $totalExpense = (float) $rows->where('type', 'EXPENSE')->sum('amount');
        $totalIncome = (float) $rows->where('type', 'INCOME')->sum('amount');
        $netProfit = $totalIncome - $totalExpense;

        $byCategory = [];
        foreach ($rows->groupBy('category') as $category => $group) {
            $expense = (float) $group->where('type', 'EXPENSE')->sum('amount');
            $income = (float) $group->where('type', 'INCOME')->sum('amount');
            $byCategory[] = [
                'category' => $category,
                'expense' => round($expense, 2),
                'income' => round($income, 2),
                'net' => round($income - $expense, 2),
            ];
        }

        return response()->json([
            'fieldId' => (string) $field->id,
            'crop' => $field->crop,
            'areaM2' => $areaM2,
            'totals' => [
                'expense' => round($totalExpense, 2),
                'income' => round($totalIncome, 2),
                'netProfit' => round($netProfit, 2),
            ],
            'costPerM2' => $areaM2 > 0 ? round($totalExpense / $areaM2, 6) : null,
            'netProfitPerM2' => $areaM2 > 0 ? round($netProfit / $areaM2, 6) : null,
            'byCategory' => $byCategory,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $fields = $user->farmFields()->get();
        $transactions = FieldTransaction::where('user_id', $user->id)->get();

        $byCrop = [];
        foreach ($fields->groupBy(fn (FarmField $f) => $f->crop ?: 'Unknown') as $crop => $cropFields) {
            $fieldIds = $cropFields->pluck('id');
            $cropTx = $transactions->whereIn('farm_field_id', $fieldIds);
            $expense = (float) $cropTx->where('type', 'EXPENSE')->sum('amount');
            $income = (float) $cropTx->where('type', 'INCOME')->sum('amount');
            $areaM2 = (float) $cropFields->sum('area_m2');
            $net = $income - $expense;

            $byCrop[] = [
                'crop' => $crop,
                'fieldCount' => $cropFields->count(),
                'areaM2' => round($areaM2, 2),
                'expense' => round($expense, 2),
                'income' => round($income, 2),
                'netProfit' => round($net, 2),
                'costPerM2' => $areaM2 > 0 ? round($expense / $areaM2, 6) : null,
                'netProfitPerM2' => $areaM2 > 0 ? round($net / $areaM2, 6) : null,
            ];
        }

        $totalExpense = (float) $transactions->where('type', 'EXPENSE')->sum('amount');
        $totalIncome = (float) $transactions->where('type', 'INCOME')->sum('amount');

        return response()->json([
            'totals' => [
                'expense' => round($totalExpense, 2),
                'income' => round($totalIncome, 2),
                'netProfit' => round($totalIncome - $totalExpense, 2),
            ],
            'byCrop' => $byCrop,
        ]);
    }

    public function export(Request $request, int $fieldId): JsonResponse
    {
        $field = $this->ownedField($request, $fieldId);
        $format = strtolower((string) $request->query('format', 'csv'));

        $transactions = FieldTransaction::where('user_id', $request->user()->id)
            ->where('farm_field_id', $field->id)
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        $economics = json_decode($this->fieldEconomics($request, $fieldId)->getContent(), true);

        if ($format === 'pdf') {
            return $this->exportPdf($field, $transactions, $economics);
        }

        $csv = $this->buildCsv($field, $transactions, $economics);
        $filename = sprintf('field-%d-economics-%s.csv', $field->id, now()->format('Ymd'));

        return response()->json([
            'filename' => $filename,
            'mimeType' => 'text/csv',
            'content' => $csv,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, FieldTransaction>  $transactions
     * @param  array<string, mixed>  $economics
     */
    private function buildCsv(FarmField $field, $transactions, array $economics): string
    {
        $lines = [];
        $lines[] = 'Field Economics Export';
        $lines[] = 'Field,'.$this->csvEscape($field->name);
        $lines[] = 'Crop,'.$this->csvEscape($field->crop);
        $lines[] = 'Area m2,'.($economics['areaM2'] ?? $field->area_m2);
        $lines[] = 'Total Expense,'.($economics['totals']['expense'] ?? 0);
        $lines[] = 'Total Income,'.($economics['totals']['income'] ?? 0);
        $lines[] = 'Net Profit,'.($economics['totals']['netProfit'] ?? 0);
        $lines[] = 'Cost per m2,'.($economics['costPerM2'] ?? '');
        $lines[] = 'Net Profit per m2,'.($economics['netProfitPerM2'] ?? '');
        $lines[] = '';
        $lines[] = 'Date,Type,Category,Amount,Quantity,Unit,Note';

        foreach ($transactions as $t) {
            $lines[] = implode(',', [
                $t->occurred_on?->toDateString() ?? '',
                $t->type,
                $t->category,
                number_format((float) $t->amount, 2, '.', ''),
                $t->quantity !== null ? number_format((float) $t->quantity, 4, '.', '') : '',
                $this->csvEscape((string) ($t->unit ?? '')),
                $this->csvEscape((string) ($t->note ?? '')),
            ]);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  \Illuminate\Support\Collection<int, FieldTransaction>  $transactions
     * @param  array<string, mixed>  $economics
     */
    private function exportPdf(FarmField $field, $transactions, array $economics): JsonResponse
    {
        $html = $this->buildExportHtml($field, $transactions, $economics);
        $filename = sprintf('field-%d-economics-%s.pdf', $field->id, now()->format('Ymd'));

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $binary = $pdf->output();

            return response()->json([
                'filename' => $filename,
                'mimeType' => 'application/pdf',
                'content' => base64_encode($binary),
            ]);
        }

        // Fallback: HTML download when Dompdf is unavailable
        return response()->json([
            'filename' => str_replace('.pdf', '.html', $filename),
            'mimeType' => 'text/html',
            'content' => $html,
            'note' => 'Dompdf not installed; returned HTML instead of PDF.',
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, FieldTransaction>  $transactions
     * @param  array<string, mixed>  $economics
     */
    private function buildExportHtml(FarmField $field, $transactions, array $economics): string
    {
        $rows = '';
        foreach ($transactions as $t) {
            $rows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $this->e($t->occurred_on?->toDateString() ?? ''),
                $this->e($t->type),
                $this->e($t->category),
                $this->e(number_format((float) $t->amount, 2)),
                $this->e($t->quantity !== null ? (string) $t->quantity : ''),
                $this->e((string) ($t->unit ?? '')),
                $this->e((string) ($t->note ?? '')),
            );
        }

        $name = $this->e($field->name);
        $crop = $this->e($field->crop);
        $area = $this->e((string) ($economics['areaM2'] ?? $field->area_m2));
        $expense = $this->e((string) ($economics['totals']['expense'] ?? 0));
        $income = $this->e((string) ($economics['totals']['income'] ?? 0));
        $net = $this->e((string) ($economics['totals']['netProfit'] ?? 0));

        return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Field Economics</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:12px} table{width:100%;border-collapse:collapse} th,td{border:1px solid #ccc;padding:4px;text-align:left} h1{font-size:18px}</style>
</head><body>
<h1>Field Economics — {$name}</h1>
<p>Crop: {$crop} | Area: {$area} m²</p>
<p>Expense: {$expense} | Income: {$income} | Net: {$net}</p>
<table>
<thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Amount</th><th>Qty</th><th>Unit</th><th>Note</th></tr></thead>
<tbody>{$rows}</tbody>
</table>
</body></html>
HTML;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function csvEscape(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }

    private function ownedField(Request $request, int $fieldId): FarmField
    {
        return FarmField::where('user_id', $request->user()->id)
            ->where('id', $fieldId)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTransaction(FieldTransaction $t): array
    {
        return [
            'id' => (string) $t->id,
            'farmFieldId' => (string) $t->farm_field_id,
            'clientUuid' => $t->client_uuid,
            'type' => $t->type,
            'category' => $t->category,
            'amount' => (float) $t->amount,
            'quantity' => $t->quantity !== null ? (float) $t->quantity : null,
            'unit' => $t->unit,
            'occurredOn' => $t->occurred_on?->toDateString(),
            'note' => $t->note,
            'createdAt' => $t->created_at?->toIso8601String(),
            'updatedAt' => $t->updated_at?->toIso8601String(),
        ];
    }
}
