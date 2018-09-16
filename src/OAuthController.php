<?php

namespace Singingfox\O365Auth;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use League\OAuth2\Client\Provider\GenericProvider as OAuth2Provider;
use League\OAuth2\Client\Token\AccessToken;
use Microsoft\Graph\Graph;

class OAuthController extends Controller
{
    // 1.  User click on a link to initiate authentication flow via Microsoft Graph (Office 365)
    public function init(Request $request)
    {
        $oAuthProvider = $this->getOAuthProvider();
        $authorizationUrl = $oAuthProvider->getAuthorizationUrl();

        session(['O365_AUTH_STATE' => $oAuthProvider->getState()]);
        session(['URL_BEFORE_AUTH' => array_get($_SERVER, 'HTTP_REFERER') ?? '/']);

        return redirect($authorizationUrl);
    }
    // 2.   $authorizationUrl is a Microsoft link that takes user entered credentials and attempts to authenticate

    // 3.   In the end of #2, Microsoft authentication server redirects user to "Redirect URL", as provided when
    //      creating the application at https://apps.dev.microsoft.com.  This URL is also needed when creating
    //      $oAuthProvider.  So we store it in .env.
    //      "Redirect URL" route, however we name it, is handled by the following controller method redirect().
    //      The method is named so that it's easier to associate why it's needed.  But the name doesn't tell what this method really does.
    public function redirect(Request $request)
    {
        $this->getAuthCodeOrAbort();
        $this->matchStateOrAbort();

        try {
            $user = $this->findOrCreateUser($this->getAuthenticatedUserdataOrAbort());
            // Log in user manually at this point
            Auth::login($user);
            return redirect(session('URL_BEFORE_AUTH') ?? '/');

        } catch (\Exception $e) {
            abort(500, "Office 365 token not obtained.  Authentication aborted.  Error: " . $e->getMessage());
        }
    }

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

    protected function getAuthCodeOrAbort()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['code'])) {
            return $_GET['code'];
        }
        abort(500, 'Invalid authorization code.');
    }

    protected function matchStateOrAbort()
    {
        if (empty($_GET['state']) || ($_GET['state'] !== session('O365_AUTH_STATE'))){
            session(['O365_AUTH_STATE' => '']);
            abort(500, 'Authentication state not matched.  Aborted.');
        }
    }

    protected function getAuthenticatedUserDataOrAbort()
    {
        try {
            $graph = new Graph();
            $token = $this->getAccessTokenOrAbort();
            $authenticatedUser = $graph->setAccessToken($token)
                ->createRequest("get", "/me")
                ->setReturnType(\Microsoft\Graph\Model\User::class)
                ->execute();
            $userData = [
                'name'  => $authenticatedUser->getGivenName() . ' ' . $authenticatedUser->getSurname(),
                'email' => strtolower($authenticatedUser->getMail())
            ];
            $this->matchDomainOrAbort($userData);
            return $userData;
        } catch (\Exception $e) {
            abort(500, 'User information not retrieved.  Authentication failed. ' . $e->getMessage());
        }
    }

    protected function getAccessTokenOrAbort()
    {
        $oAuthProvider = $this->getOAuthProvider();
        $accessToken = $oAuthProvider->getAccessToken('authorization_code', ['code' => $_GET['code']]);
        $token = $accessToken->getToken();
        if (! $token) {
            abort(500, "Office 365 access token doesn't exist.  User info cannot be retrieved.");
        }
        return $token;
    }

    protected function matchDomainOrAbort(array $userData)
    {
        $domain = strtolower(trim(env('O365_DOMAIN')));
        if (! $domain || ! ends_with($userData['email'], $domain)) {
            abort(404, "Authentication failed.  User email is on a domain that can not yet be authenticated this way.");
        }
    }

    protected function findOrCreateUser(array $userData)
    {
        $user = User::where('email', $userData['email'])->first();

        $timestamp = date('Y-m-d H:i:s');

        if (! $user) {
            // email_verified_at is added in Laravel 5.7.  It is ignored if the field doesn't exist for older version of Laravel
            $user = User::create(array_merge($userData, ['email_verified_at' => $timestamp, 'password' => Hash::make(str_random())]));
        }

        if ($user) {
            if (array_key_exists('email_verified_at', $user->getAttributes()) && ! $user->email_verified_at) {
                $user->email_verified_at = $timestamp;
                $user->save();
            }
        } else {
            abort(500, 'Looking up in or adding user to our system was not successful. Authentication failed.');
        }

        return $user;
    }
}
