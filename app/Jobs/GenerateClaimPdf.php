<?php

namespace App\Jobs;

use App\Models\Claim;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\MarkSignService;
use App\Mail\DocumentSigningRequestMail;
use App\Services\MicrosoftGraphMailService;

class GenerateClaimPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public Claim $claim) {}

    public function handle(MarkSignService $markSign): void
    {
        $this->claim->refresh();

        $pdf = Pdf::loadView('pdf.claim', ['claim' => $this->claim]);
        $tempPath = 'temp/claim_' . $this->claim->id . '.pdf';
        Storage::put($tempPath, $pdf->output());

        try {
            $uuid = $markSign->uploadDocument($tempPath);
            $signingUrl = $markSign->generateSigningLink($uuid, $this->claim);

            $this->claim->update([
                'marksign_uuid' => $uuid,
                'signing_url' => $signingUrl,
                'status' => 'awaiting_signature'
            ]);

            app(MicrosoftGraphMailService::class)->send(
                new DocumentSigningRequestMail($this->claim),
                $this->claim->email,
            );

        } catch (\Exception $e) {
            Log::error('MarkSign Integration Failed: '.$e->getMessage(), [
                'claim_id' => $this->claim->id,
            ]);

            $this->claim->update(['status' => 'error']);

            throw $e;
        }
    }
}
