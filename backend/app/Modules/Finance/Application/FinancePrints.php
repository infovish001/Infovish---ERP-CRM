<?php

namespace App\Modules\Finance\Application;

use App\Shared\MasterData\Mutation;
use App\Shared\Tenancy\TenantContext;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class FinancePrints
{
    public function issue(string $company, string $document, string $kind, ?string $reason, ?string $key): string
    {
        Validator::make(['kind' => $kind, 'reason' => $reason, 'key' => $key], ['kind' => 'required|in:original,duplicate,copy', 'reason' => 'nullable|string|max:500', 'key' => 'required'])->validate();
        if ($kind === 'copy') {
            Validator::make(['reason' => $reason], ['reason' => 'required'])->validate();
        }

        return app(Mutation::class)->run('Finance', $company, 'finance.print:'.$document, ['kind' => $kind, 'reason' => $reason], $key, function () use ($company, $document, $kind, $reason) {
            $r = app(FinanceAccess::class)->record('finance_documents', $company, $document, true);
            abort_unless($r->state === 'posted' && $r->kind === 'invoice', 409, 'Issue only a posted customer invoice.');
            $original = DB::connection('tenant')->table('finance_prints')->where('document_id', $document)->where('kind', 'original')->exists();
            if ($kind === 'original') {
                abort_if($original, 409, 'Original issuance already recorded; request a marked copy.');
            }if ($kind === 'copy') {
                abort_unless($original, 409, 'Record original issuance before a replacement copy.');
            }
            $bytes = $this->build($r, $kind);
            $id = (string) Str::uuid();
            DB::connection('tenant')->table('finance_prints')->insert(['id' => $id, 'company_id' => $company, 'document_id' => $document, 'kind' => $kind, 'pdf_base64' => base64_encode($bytes), 'sha256' => hash('sha256', $bytes), 'reason' => $reason, 'actor_id' => app(TenantContext::class)->actorId(), 'created_at' => now()]);
            app(FinanceLedger::class)->event($company, 'document.printed', $document);

            return $id;
        });
    }

    /** @return array{bytes:string,filename:string} */
    public function render(string $company, string $printId): array
    {
        $print = app(FinanceAccess::class)->record('finance_prints', $company, $printId);
        $r = app(FinanceAccess::class)->record('finance_documents', $company, $print->document_id);
        $bytes = base64_decode($print->pdf_base64, true);
        if ($bytes === false || ! hash_equals($print->sha256, hash('sha256', $bytes))) {
            throw new \RuntimeException('Retained invoice document failed integrity verification.');
        }

        return ['bytes' => $bytes, 'filename' => $r->number.'-'.$print->kind.'.pdf'];
    }

    private function build(\stdClass $r, string $kind): string
    {
        $r->snapshot = json_decode($r->snapshot, true, flags: JSON_THROW_ON_ERROR);
        $print = (object) ['kind' => $kind];
        $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'chroot' => base_path('vendor/dompdf'), 'defaultFont' => 'DejaVu Sans']);
        $options->setAllowedProtocols(['file://']);
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4');
        $pdf->loadHtml(view('finance.invoice', ['r' => $r, 'print' => $print])->render());
        $pdf->render();
        $pdf->getCanvas()->page_text(34, 815, $r->number.' - Page {PAGE_NUM} of {PAGE_COUNT}', $pdf->getFontMetrics()->getFont('helvetica'), 8, [0.35, 0.4, 0.5]);

        return $pdf->output();
    }
}
