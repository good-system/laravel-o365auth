<?php
namespace GoodSystem\O365Auth;

use League\OAuth2\Client\Provider\GenericProvider as OAuth2Provider;

trait OAuthTrait
{
    protected function getOAuthProvider()
    {
        // define these 3 in project_root/.env
        $clientId = env('O365_CLIENT_ID');
        $clientSecret = env('O365_CLIENT_SECRET');
        $redirectUrl = env('O365_REDIRECT_URL');
        if (!$clientId || !$clientSecret || !$redirectUrl) {
            abort(500, 'Office 365 authentication parameters are not provided. Authentication aborted.');
        }

        return new OAuth2Provider([
            'clientId'                => $clientId,
            'clientSecret'            => $clientSecret,
            'redirectUri'             => $redirectUrl,
            // these 3 are defined in config.php of this package project
            'urlAuthorize'            => config('O365Auth.baseUrl') . config('O365Auth.authorizeUrl'),
            'urlAccessToken'          => config('O365Auth.baseUrl') . config('O365Auth.tokenUrl'),
            'scopes'                  => config('O365Auth.scopes'),
            'urlResourceOwnerDetails' => ''
        ]);
    }

    protected function getAccessToken()
    {
        return $this->getOAuthProvider()
            ->getAccessToken('authorization_code', ['code' => $_GET['code']])
            ->getToken();
    }

}