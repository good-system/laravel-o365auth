<?php

namespace GoodSystem\O365Auth;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use League\OAuth2\Client\Token\AccessToken;
use Microsoft\Graph\Graph;

class OAuthController extends Controller
{
    use OAuthTrait;
    
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
            return redirect(session('URL_BEFORE_AUTH') ?? '/')->with('user', $user);

        } catch (\Exception $e) {
            $this->abortOAuth(500, "Office 365 token not obtained.  Authentication aborted.  Error: " . $e->getMessage());
        }
    }

    protected function getAuthCodeOrAbort()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['code'])) {
            return $_GET['code'];
        }
        $this->abortOAuth(500, 'Invalid authorization code.');
    }

    protected function matchStateOrAbort()
    {
        if (empty($_GET['state']) || ($_GET['state'] !== session('O365_AUTH_STATE'))){
            $this->abortOAuth(500, 'Authentication state not matched.  Aborted.');
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
            $this->abortOAuth(500, 'User information not retrieved.  Authentication failed. ' . $e->getMessage());
        }
    }

    protected function getAccessTokenOrAbort()
    {
        if (! $token = $this->getAccessToken()) {
            $this->abortOAuth(500, "Office 365 access token doesn't exist.  User info cannot be retrieved.");
        }

        // If a valid token is retrieved, put it in session for later use, for example, allowing user to upload file.
        session(['access_token' => $token]);

        return $token;
    }

    protected function matchDomainOrAbort(array $userData)
    {
        $domain = strtolower(trim(env('O365_DOMAIN')));
        if (! $domain || ! ends_with($userData['email'], $domain)) {
            $this->abortOAuth(404, "Authentication failed.  User email is on a domain that can not yet be authenticated this way.");
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
            $this->abortOAuth(500, 'Looking up in or adding user to our system was not successful. Authentication failed.');
        }

        return $user;
    }

    //
    protected function abortOAuth($code, $message)
    {
        session(['O365_AUTH_STATE' => '']);
        abort($code, $message);
    }
}
