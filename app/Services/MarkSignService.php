<?php

namespace App\Services;

use App\Models\Claim;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MarkSignService
{
    protected string $baseUrl;

    protected ?string $token;

    public function __construct()
    {
        $this->token = config('services.marksign.token');
        $this->baseUrl = config('services.marksign.base_url');
    }

    public function isConfigured(): bool
    {
        return filled($this->token);
    }

    public function uploadDocument(string $pdfPath): string
    {
        if (! $this->isConfigured()) {
            throw new Exception('MarkSign access token is not configured (MARKSIGN_TOKEN).');
        }

        $fullPath = Storage::disk('local')->path($pdfPath);

        if (! is_readable($fullPath)) {
            throw new Exception("PDF file not found for upload: {$fullPath}");
        }

        $fileName = basename($fullPath);
        $fileContents = file_get_contents($fullPath);

        $response = Http::timeout(90)
            ->attach('files[]', $fileContents, $fileName)
            ->post("{$this->baseUrl}/v2/document/upload", [
                'access_token' => $this->token,
                'access' => 'private',
                'billing_type' => 'document_owner',
            ]);

        if (! $response->successful()) {
            Log::error('MarkSign upload failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new Exception('MarkSign Upload Error: '.$response->body());
        }

        $uuid = $response->json('data.0.uuid');

        if (! $uuid) {
            throw new Exception('Nepavyko gauti dokumento UUID. Atsakymas: '.$response->body());
        }

        Log::info('MarkSign document uploaded', ['uuid' => $uuid]);

        return $uuid;
    }

    public function getDocumentStatus(string $docUuid): array
    {
        $response = Http::timeout(30)->post("{$this->baseUrl}/document/{$docUuid}/signers", [
            'access_token' => $this->token,
        ]);

        if (! $response->successful()) {
            throw new Exception('MarkSign API Error: '.$response->body());
        }

        return $response->json();
    }

    public function downloadSignedDocument(string $docUuid): ?string
    {
        $response = Http::timeout(90)->get("{$this->baseUrl}/document/{$docUuid}/download", [
            'access_token' => $this->token,
        ]);

        if (! $response->successful()) {
            Log::error('MarkSign Download Error: '.$response->body());

            return null;
        }

        $filename = 'signed/claim_'.$docUuid.'_'.time().'.pdf';
        Storage::disk('local')->put($filename, $response->body());

        return $filename;
    }

    public function generateSigningLink(string $docUuid, Claim $claim): string
    {
        $callbackUrl = route('marksign.callback');

        Log::info('MarkSign generate signing link', [
            'uuid' => $docUuid,
            'callback_url' => $callbackUrl,
        ]);

        $response = Http::timeout(60)->post("{$this->baseUrl}/v2/document/generate-temporary-signing-link", [
            'access_token' => $this->token,
            'documents' => [(string) $docUuid],
            'callback_url' => $callbackUrl,
            'language' => 'lt',
            'signers' => [
                [
                    'name' => $claim->first_name,
                    'surname' => $claim->last_name,
                    'email' => $claim->email,
                ],
            ],
        ]);

        if (! $response->successful()) {
            Log::error('MarkSign signing link failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new Exception('MarkSign Link Error: '.$response->body());
        }

        $url = $response->json('temporary_signing_links.0.temporary_signing_link');

        if (! $url) {
            throw new Exception('Nepavyko rasti temporary_signing_link atsakyme: '.$response->body());
        }

        return $url;
    }
}
