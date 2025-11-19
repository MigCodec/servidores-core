<?php

namespace App\Support\GoogleDrive;

use Google\Client;
use Google\Service\Drive;
use RuntimeException;

class DriveClientFactory
{
    public static function make(string $applicationSuffix = 'Drive Integration'): Drive
    {
        $client = self::makeConfiguredClient($applicationSuffix);

        $refreshToken = config('services.google_drive.refresh_token');

        if (blank($refreshToken)) {
            throw new RuntimeException('Set GOOGLE_DRIVE_REFRESH_TOKEN with a valid token generated for your OAuth client.');
        }

        $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);

        if (isset($token['error'])) {
            throw new RuntimeException(sprintf(
                'Unable to refresh Google OAuth token: %s',
                $token['error_description'] ?? $token['error']
            ));
        }

        $client->setAccessToken($token + ['refresh_token' => $refreshToken]);

        return new Drive($client);
    }

    public static function makeForAuthorization(?string $redirectUri = null): Client
    {
        return self::makeConfiguredClient('Drive Authorization', $redirectUri);
    }

    protected static function makeConfiguredClient(string $applicationSuffix, ?string $redirectUri = null): Client
    {
        $oauthConfig = OAuthCredentials::resolve();

        $client = new Client();
        $client->setApplicationName(trim(config('app.name').' '.$applicationSuffix));
        $client->setClientId($oauthConfig['client_id']);
        $client->setClientSecret($oauthConfig['client_secret']);

        if ($redirectUri) {
            $client->setRedirectUri($redirectUri);
        } elseif (! empty($oauthConfig['redirect_uris'][0])) {
            $client->setRedirectUri($oauthConfig['redirect_uris'][0]);
        }

        $client->setScopes([Drive::DRIVE_FILE]);
        $client->setAccessType('offline');

        return $client;
    }
}
