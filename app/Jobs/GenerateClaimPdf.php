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
use Illuminate\Support\Facades\Mail;

class GenerateClaimPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $claim;

    public function __construct(Claim $claim)
    {
        $this->claim = $claim;
    }

    public function handle(MarkSignService $markSign)
    {

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.claim', ['claim' => $this->claim]);
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

            Mail::to($this->claim->email)
                ->send(new DocumentSigningRequestMail($this->claim));

        } catch (\Exception $e) {
            Log::error("MarkSign Integration Failed: " . $e->getMessage());
            $this->fail($e);
        }
    }
}
