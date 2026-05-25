<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class MarkSignService
{
    protected $baseUrl;
    protected $token;

    public function __construct()
    {
        $this->token = config('services.marksign.token');
        $this->baseUrl = config('services.marksign.base_url');
    }

    public function uploadDocument($pdfPath)
    {
        if (! $this->token) {
            throw new Exception('MarkSign access token is not configured (MARKSIGN_TOKEN).');
        }

        $fullPath = Storage::disk('local')->path($pdfPath);

        if (! is_readable($fullPath)) {
            throw new Exception("PDF file not found for upload: {$fullPath}");
        }

        $response = Http::asMultipart()
            ->post("{$this->baseUrl}/v2/document/upload", [
                'access_token' => $this->token,
                'files[]' => fopen($fullPath, 'r'),
                'access' => 'private',
                'billing_type' => 'document_owner',
            ]);

        if (!$response->successful()) {
            throw new Exception('MarkSign Upload Error: ' . $response->body());
        }

        $uuid = $response->json('data.0.uuid');

        if (!$uuid) {
            throw new Exception('Nepavyko gauti dokumento UUID. Atsakymas: ' . $response->body());
        }

        return $uuid;
    }
    public function getDocumentStatus($docUuid)
    {
        $response = Http::post("{$this->baseUrl}/document/{$docUuid}/signers", [
            'access_token' => $this->token,
        ]);

        if (!$response->successful()) {
            throw new \Exception('MarkSign API Error: ' . $response->body());
        }

        return $response->json();
    }
    public function downloadSignedDocument($docUuid)
    {
        $response = Http::get("{$this->baseUrl}/document/{$docUuid}/download", [
            'access_token' => $this->token,
        ]);

        if (!$response->successful()) {
            Log::error('MarkSign Download Error: ' . $response->body());
            return null;
        }
        $filename = 'signed/claim_' . $docUuid . '_' . time() . '.pdf';
        Storage::put($filename, $response->body());

        return $filename;
    }

    public function generateSigningLink($docUuid, $claim)
    {
        $response = Http::post("{$this->baseUrl}/v2/document/generate-temporary-signing-link", [
            'access_token' => $this->token,
            'documents' => [(string) $docUuid],
            'callback_url' => route('marksign.callback'),
            'language' => 'lt',
            'signers' => [
                [
                    'name' => $claim->first_name,
                    'surname' => $claim->last_name,
                    'email' => $claim->email,
                ]
//                ,[
//                    'name' => 'Agneška',
//                    'surname' => ' Stasilo',
//                    'email' => 'agneska@sitandgo.lt',
//                ]
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('MarkSign Link Error: ' . $response->body());
        }
        $url = $response->json('temporary_signing_links.0.temporary_signing_link');

        if (!$url) {
            throw new \Exception('Nepavyko rasti temporary_signing_link atsakyme: ' . $response->body());
        }

        return $url;
    }
}
