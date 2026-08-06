<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FarmField;
use App\Models\FieldTransaction;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'saleItem' => ['nullable', 'string', 'max:255'],
            'categoryOther' => ['nullable', 'string', 'max:255'],
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
            'sale_item' => $validated['saleItem'] ?? null,
            'category_other' => $validated['categoryOther'] ?? null,
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
            'saleItem' => ['nullable', 'string', 'max:255'],
            'categoryOther' => ['nullable', 'string', 'max:255'],
        ]);

        $update = [];
        if (isset($validated['type'])) {
            $update['type'] = $validated['type'];
        }
        if (isset($validated['category'])) {
            $update['category'] = $validated['category'];
        }
        if (isset($validated['amount'])) {
            $update['amount'] = $validated['amount'];
        }
        if (array_key_exists('quantity', $validated)) {
            $update['quantity'] = $validated['quantity'];
        }
        if (array_key_exists('unit', $validated)) {
            $update['unit'] = $validated['unit'];
        }
        if (isset($validated['occurredOn'])) {
            $update['occurred_on'] = $validated['occurredOn'];
        }
        if (array_key_exists('note', $validated)) {
            $update['note'] = $validated['note'];
        }
        if (array_key_exists('saleItem', $validated)) {
            $update['sale_item'] = $validated['saleItem'];
        }
        if (array_key_exists('categoryOther', $validated)) {
            $update['category_other'] = $validated['categoryOther'];
        }

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
        /** @var User $user */
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
        $format = strtolower((string) $request->query('format', 'pdf'));

        if ($format !== 'pdf') {
            return response()->json([
                'message' => 'Only PDF export is supported. Use format=pdf.',
            ], 422);
        }

        $field->load('user');

        $transactions = FieldTransaction::where('user_id', $request->user()->id)
            ->where('farm_field_id', $field->id)
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        $economics = json_decode($this->fieldEconomics($request, $fieldId)->getContent(), true);

        return $this->exportPdf($field, $transactions, $economics, (int) $request->user()->id, $request);
    }

    /**
     * @param  Collection<int, FieldTransaction>  $transactions
     * @param  array<string, mixed>  $economics
     */
    private function exportPdf(FarmField $field, $transactions, array $economics, int $userId, Request $request): JsonResponse
    {
        $filename = sprintf('field-%d-economics-%s.pdf', $field->id, now()->format('Ymd'));

        if (! class_exists(Pdf::class)) {
            $html = $this->buildExportHtml($field, $transactions, $economics);

            return response()->json([
                'filename' => str_replace('.pdf', '.html', $filename),
                'mimeType' => 'text/html;charset=utf-8',
                'content' => base64_encode($html),
                'encoding' => 'base64',
                'note' => 'Dompdf not installed; returned HTML instead of PDF.',
            ]);
        }

        $binary = null;
        $lastError = null;

        // Attempt 1: branded HTML. Attempt 2: ultra-simple HTML if DomPDF chokes.
        foreach ([false, true] as $minimal) {
            try {
                $html = $minimal
                    ? $this->buildMinimalExportHtml($field, $transactions, $economics)
                    : $this->buildExportHtml($field, $transactions, $economics);

                $binary = Pdf::loadHTML($html)
                    ->setPaper('a4', 'portrait')
                    ->setOption('defaultFont', 'Helvetica')
                    ->setOption('isRemoteEnabled', false)
                    ->output();
                break;
            } catch (\Throwable $e) {
                $lastError = $e;
                report($e);
            }
        }

        if ($binary === null) {
            return response()->json([
                'message' => 'Could not generate PDF report. Please try again.',
                'error' => config('app.debug') && $lastError ? $lastError->getMessage() : null,
            ], 500);
        }

        $payload = [
            'filename' => $filename,
            'mimeType' => 'application/pdf',
            'content' => base64_encode($binary),
            'encoding' => 'base64',
        ];

        // Optional signed URL — never fail the export if this part breaks (route cache / APP_URL).
        try {
            $storedName = sprintf('field-%d-%s-%s.pdf', $field->id, now()->format('YmdHis'), Str::random(12));
            $storagePath = "exports/{$userId}/{$storedName}";
            Storage::disk('local')->put($storagePath, $binary);

            $rootUrl = rtrim($request->getSchemeAndHttpHost(), '/');
            $previousRoot = config('app.url');
            URL::forceRootUrl($rootUrl);
            try {
                $payload['downloadUrl'] = URL::temporarySignedRoute(
                    'economics.export.download',
                    now()->addMinutes(30),
                    ['userId' => $userId, 'file' => $storedName],
                );
            } finally {
                URL::forceRootUrl($previousRoot ?: $rootUrl);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json($payload);
    }

    /**
     * DomPDF-safe minimal report (no logo, core fonts only).
     *
     * @param  Collection<int, FieldTransaction>  $transactions
     * @param  array<string, mixed>  $economics
     */
    private function buildMinimalExportHtml(FarmField $field, $transactions, array $economics): string
    {
        $user = $field->user;
        $farmName = $this->e((string) ($user?->farm_name ?: 'My Farm'));
        $farmLocation = $this->e((string) ($user?->farm_location ?: ''));
        $name = $this->e($field->name);
        $crop = $this->e($field->crop);
        $expense = $this->e(number_format((float) ($economics['totals']['expense'] ?? 0), 2));
        $income = $this->e(number_format((float) ($economics['totals']['income'] ?? 0), 2));
        $net = $this->e(number_format((float) ($economics['totals']['netProfit'] ?? 0), 2));
        $logoHtml = $this->pdfLogoHtml();

        $rows = '';
        foreach ($transactions as $t) {
            $rows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $this->e($t->occurred_on?->toDateString() ?? ''),
                $this->e($t->type),
                $this->e((string) $t->category),
                $this->e(number_format((float) $t->amount, 2)),
            );
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="4">No transactions yet.</td></tr>';
        }

        return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
body{font-family:Helvetica,Arial,sans-serif;font-size:12px;color:#111;margin:0;padding:0;}
.header{border-bottom:3px solid #1b4332;padding-bottom:12px;margin-bottom:14px;}
.logo{height:42px;width:auto;vertical-align:middle;margin-right:10px;}
.brand{display:inline-block;vertical-align:middle;}
.brand-name{font-size:20px;font-weight:bold;color:#1b4332;}
.brand-tag{font-size:10px;color:#52796f;}
h1{font-size:16px;color:#1b4332;margin:0 0 8px 0;}
table{width:100%;border-collapse:collapse;margin-top:12px;}
th,td{border:1px solid #ccc;padding:6px;text-align:left;}
th{background:#1b4332;color:#fff;}
</style></head><body>
<div class="header">
  {$logoHtml}
  <div class="brand">
    <div class="brand-name">AgroAide</div>
    <div class="brand-tag">Field Economics Report</div>
  </div>
  <div style="margin-top:8px;font-size:11px;color:#374151;">
    <strong>{$farmName}</strong>{$farmLocation}<br>Field: {$name} · Crop: {$crop}
  </div>
</div>
<h1>{$name}</h1>
<p>Expense: {$expense} · Income: {$income} · Net: {$net}</p>
<table><thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Amount</th></tr></thead>
<tbody>{$rows}</tbody></table>
</body></html>
HTML;
    }

    public function downloadExport(Request $request, int $userId, string $file): StreamedResponse
    {
        if (! preg_match('/^field-\d+-\d{14}-[A-Za-z0-9]+\.pdf$/', $file)) {
            abort(404);
        }

        $storagePath = "exports/{$userId}/{$file}";
        if (! Storage::disk('local')->exists($storagePath)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $storagePath,
            $file,
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Prefer the tiny pre-baked PDF logo (works without GD in Docker).
     * Falls back to live resize when GD is available, otherwise skips the image.
     */
    private function pdfLogoHtml(): string
    {
        $compactPath = public_path('images/agroaideLogo-pdf.png');
        if (is_file($compactPath) && filesize($compactPath) > 0 && filesize($compactPath) < 200_000) {
            $png = (string) file_get_contents($compactPath);

            return '<img class="logo" src="data:image/png;base64,'.base64_encode($png).'" alt="AgroAide" />';
        }

        $logoPath = public_path('images/agroaideLogo.png');
        if (! is_file($logoPath) || ! function_exists('imagecreatefrompng')) {
            return '';
        }

        try {
            $source = @imagecreatefrompng($logoPath);
            if ($source === false) {
                return '';
            }

            $srcW = imagesx($source);
            $srcH = imagesy($source);
            $maxW = 140;
            $dstW = min($maxW, max(1, $srcW));
            $dstH = (int) max(1, round($srcH * ($dstW / $srcW)));

            $resized = imagecreatetruecolor($dstW, $dstH);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefilledrectangle($resized, 0, 0, $dstW, $dstH, $transparent);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

            ob_start();
            imagepng($resized, null, 6);
            $png = ob_get_clean();

            imagedestroy($source);
            imagedestroy($resized);

            if ($png === false || $png === '') {
                return '';
            }

            return '<img class="logo" src="data:image/png;base64,'.base64_encode($png).'" alt="AgroAide" />';
        } catch (\Throwable $e) {
            report($e);

            return '';
        }
    }

    /**
     * @param  Collection<int, FieldTransaction>  $transactions
     * @param  array<string, mixed>  $economics
     */
    private function buildExportHtml(FarmField $field, $transactions, array $economics): string
    {
        $user = $field->user;
        $farmName = $this->e((string) ($user?->farm_name ?: 'My Farm'));
        $farmLocation = $this->e((string) ($user?->farm_location ?: 'Unknown location'));
        $name = $this->e($field->name);
        $crop = $this->e($field->crop);
        $area = $this->e(number_format((float) ($economics['areaM2'] ?? $field->area_m2), 2));
        $expense = $this->e(number_format((float) ($economics['totals']['expense'] ?? 0), 2));
        $income = $this->e(number_format((float) ($economics['totals']['income'] ?? 0), 2));
        $net = $this->e(number_format((float) ($economics['totals']['netProfit'] ?? 0), 2));
        $exportedAt = $this->e(now()->format('M j, Y g:i A'));

        $logoHtml = $this->pdfLogoHtml();

        $rows = '';
        foreach ($transactions as $t) {
            $categoryLabel = $t->category;
            if ($t->category === 'OTHER' && ! empty($t->category_other)) {
                $categoryLabel = 'OTHER ('.$t->category_other.')';
            }

            $detailParts = [];
            if (! empty($t->sale_item)) {
                $detailParts[] = 'Sold: '.$t->sale_item;
            }
            if (! empty($t->note)) {
                $detailParts[] = $t->note;
            }
            $detail = implode(' — ', $detailParts);

            $rows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td class="num">%s</td><td class="num">%s</td><td>%s</td><td>%s</td></tr>',
                $this->e($t->occurred_on?->toDateString() ?? ''),
                $this->e($t->type),
                $this->e($categoryLabel),
                $this->e(number_format((float) $t->amount, 2)),
                $this->e($t->quantity !== null ? (string) $t->quantity : '—'),
                $this->e((string) ($t->unit ?? '—')),
                $this->e($detail !== '' ? $detail : '—'),
            );
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="7" style="text-align:center;color:#6b7280;padding:16px;">No transactions recorded yet.</td></tr>';
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Field Economics — {$name}</title>
<style>
  @page { margin: 28px; }
  body {
    font-family: Helvetica, Arial, sans-serif;
    font-size: 11px;
    color: #1f2937;
    margin: 0;
    padding: 0;
  }
  .header {
    display: table;
    width: 100%;
    border-bottom: 3px solid #2d6a4f;
    padding-bottom: 14px;
    margin-bottom: 18px;
  }
  .header-left, .header-right {
    display: table-cell;
    vertical-align: middle;
  }
  .header-right { text-align: right; }
  .logo {
    height: 48px;
    width: auto;
    vertical-align: middle;
    margin-right: 10px;
  }
  .brand {
    display: inline-block;
    vertical-align: middle;
  }
  .brand-name {
    font-size: 22px;
    font-weight: bold;
    color: #1b4332;
    letter-spacing: 0.5px;
  }
  .brand-tag {
    font-size: 10px;
    color: #52796f;
    margin-top: 2px;
  }
  .farm-meta {
    font-size: 11px;
    color: #374151;
    line-height: 1.5;
  }
  .farm-meta strong { color: #1b4332; }
  h1 {
    font-size: 16px;
    color: #1b4332;
    margin: 0 0 6px 0;
  }
  .subtitle {
    color: #6b7280;
    margin: 0 0 16px 0;
    font-size: 11px;
  }
  .cards {
    width: 100%;
    border-collapse: separate;
    border-spacing: 8px 0;
    margin: 0 0 20px 0;
  }
  .card {
    width: 33%;
    background: #f0fdf4;
    border: 1px solid #b7e4c7;
    border-radius: 6px;
    padding: 12px 14px;
    text-align: center;
  }
  .card.income { background: #eff6ff; border-color: #bfdbfe; }
  .card.net { background: #fefce8; border-color: #fde68a; }
  .card-label {
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #6b7280;
    margin-bottom: 4px;
  }
  .card-value {
    font-size: 16px;
    font-weight: bold;
    color: #1b4332;
  }
  .info-grid {
    width: 100%;
    margin-bottom: 18px;
    border-collapse: collapse;
  }
  .info-grid td {
    padding: 6px 10px;
    border: 1px solid #e5e7eb;
    width: 50%;
  }
  .info-label {
    font-size: 9px;
    text-transform: uppercase;
    color: #6b7280;
    letter-spacing: 0.4px;
  }
  .info-value {
    font-size: 12px;
    font-weight: bold;
    color: #111827;
    margin-top: 2px;
  }
  table.tx {
    width: 100%;
    border-collapse: collapse;
    margin-top: 4px;
  }
  table.tx th {
    background: #1b4332;
    color: #ffffff;
    padding: 8px 6px;
    text-align: left;
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
  }
  table.tx td {
    border-bottom: 1px solid #e5e7eb;
    padding: 7px 6px;
    font-size: 10px;
  }
  table.tx tr:nth-child(even) td { background: #f9fafb; }
  .num { text-align: right; }
  .footer {
    margin-top: 22px;
    padding-top: 10px;
    border-top: 1px solid #e5e7eb;
    font-size: 9px;
    color: #9ca3af;
    text-align: center;
  }
</style>
</head>
<body>
  <div class="header">
    <div class="header-left">
      {$logoHtml}
      <div class="brand">
        <div class="brand-name">AgroAide</div>
        <div class="brand-tag">Field Economics Report</div>
      </div>
    </div>
    <div class="header-right">
      <div class="farm-meta">
        <strong>{$farmName}</strong><br>
        {$farmLocation}<br>
        Exported {$exportedAt}
      </div>
    </div>
  </div>

  <h1>{$name}</h1>
  <p class="subtitle">Crop: {$crop} &nbsp;·&nbsp; Area: {$area} m²</p>

  <table class="info-grid">
    <tr>
      <td>
        <div class="info-label">Field</div>
        <div class="info-value">{$name}</div>
      </td>
      <td>
        <div class="info-label">Crop</div>
        <div class="info-value">{$crop}</div>
      </td>
    </tr>
    <tr>
      <td>
        <div class="info-label">Area</div>
        <div class="info-value">{$area} m²</div>
      </td>
      <td>
        <div class="info-label">Farm</div>
        <div class="info-value">{$farmName}</div>
      </td>
    </tr>
  </table>

  <table class="cards">
    <tr>
      <td class="card">
        <div class="card-label">Total Expense</div>
        <div class="card-value">{$expense}</div>
      </td>
      <td class="card income">
        <div class="card-label">Total Income</div>
        <div class="card-value">{$income}</div>
      </td>
      <td class="card net">
        <div class="card-label">Net Profit</div>
        <div class="card-value">{$net}</div>
      </td>
    </tr>
  </table>

  <table class="tx">
    <thead>
      <tr>
        <th>Date</th>
        <th>Type</th>
        <th>Category</th>
        <th class="num">Amount</th>
        <th class="num">Qty</th>
        <th>Unit</th>
        <th>Details</th>
      </tr>
    </thead>
    <tbody>{$rows}</tbody>
  </table>

  <div class="footer">Generated by AgroAide · {$farmName} · {$exportedAt}</div>
</body>
</html>
HTML;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
            'categoryOther' => $t->category_other,
            'saleItem' => $t->sale_item,
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
