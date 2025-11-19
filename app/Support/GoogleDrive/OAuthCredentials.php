<?php

namespace App\Support\GoogleDrive;

use Illuminate\Support\Facades\File;
use RuntimeException;

class OAuthCredentials
{
    public static function resolve(): array
    {
        $credentials = config('services.google_drive.credentials');

        if (blank($credentials)) {
            throw new RuntimeException('Provide GOOGLE_DRIVE_CREDENTIALS with the OAuth client JSON or file path.');
        }

        if (is_string($credentials) && File::exists($credentials)) {
            $credentials = File::get($credentials);
        }

        if (is_string($credentials)) {
            $decoded = json_decode($credentials, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $base64 = base64_decode($credentials, true);
                if ($base64 !== false) {
                    $decoded = json_decode($base64, true);
                }
            }

            if (isset($decoded) && is_array($decoded)) {
                $credentials = $decoded;
            }
        }

        if (! is_array($credentials)) {
            throw new RuntimeException('Invalid GOOGLE_DRIVE_CREDENTIALS value.');
        }

        if (isset($credentials['web']) && is_array($credentials['web'])) {
            $credentials = $credentials['web'];
        } elseif (isset($credentials['installed']) && is_array($credentials['installed'])) {
            $credentials = $credentials['installed'];
        }

        if (! isset($credentials['client_id'], $credentials['client_secret'])) {
            throw new RuntimeException('OAuth credentials must contain client_id and client_secret.');
        }

        return $credentials;
    }
}
