<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

class SharePointFileUrl
{
    public static function fromSettings(): ?string
    {
        if (! Schema::hasTable('settings')) {
            return null;
        }

        $settings = Setting::query()
            ->where('scope', 'sharepoint')
            ->pluck('value', 'setting');

        $siteName = $settings['site_name'] ?? null;
        $filePath = $settings['file_path'] ?? null;
        $fileName = $settings['file_name'] ?? null;

        if (! $siteName || ! $filePath || ! $fileName) {
            return null;
        }

        if (preg_match('#^(https://[^/]+):?(/sites/.+)$#', $siteName, $matches)) {
            $siteBase = $matches[1].$matches[2];
        } else {
            $siteBase = rtrim($siteName, '/');
        }

        $relativePath = trim($filePath, '/').'/'.ltrim($fileName, '/');
        $encodedPath = implode('/', array_map(rawurlencode(...), explode('/', $relativePath)));

        return "{$siteBase}/Shared Documents/{$encodedPath}";
    }
}
