<?php

namespace GoodSystem\O365Auth;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Microsoft\Graph\Graph;

class OAuthController extends Controller
{
    use OAuthTrait;

    // 1.  User click on a link to initiate authentication flow via Microsoft Graph (Office 365)
    public function init(Request $request)
    {
        if (! class_exists('\User')) {
            // #3
            $this->abortOAuth(500, 'User model not found.');
        }

        $oAuthProvider = $this->getOAuthProvider();
        $authorizationUrl = $oAuthProvider->getAuthorizationUrl();

        session(['O365_AUTH_STATE' => $oAuthProvider->getState()]);
        session(['URL_BEFORE_AUTH' => array_get($_SERVER, 'HTTP_REFERER') ?? '/']);

        return redirect($authorizationUrl);
    }
    // 2.   $authorizationUrl is a Microsoft link that takes user entered credentials and attempts to authenticate

    // 3.   In the end of above 2., Microsoft authentication server redirects user to "Redirect URL", as provided when
    //      creating the application at https://apps.dev.microsoft.com.  This URL is also needed when creating
    //      $oAuthProvider.  So we store it in .env.
    //      "Redirect URL" route, however we name it, is handled by the following controller method redirect().
    //      The method is named so that it's easier to associate why it's needed.  But the name doesn't tell all this method really does.
    public function redirect(Request $request)
    {
        $this->getAuthCodeOrAbort();
        $this->matchStateOrAbort();

        try {
            $user = $this->findOrCreateUser($this->getAuthenticatedUserdataOrAbort());
            Auth::login($user);
            // #1 Happy Scenario
            return redirect(session('URL_BEFORE_AUTH') ?? '/')->with('user', $user);
        } catch (\Exception $e) {
            // #2 Laravel Authentication Error
            $this->abortOAuth(500, "Authentication with Office 365 user failed.  Error: " . $e->getMessage());
        }
    }

    private function getAuthCodeOrAbort()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['code'])) {
            return $_GET['code'];
        }
        // #4
        $this->abortOAuth(500, 'Invalid authorization code.');
    }

    private function matchStateOrAbort()
    {
        if (empty($_GET['state']) || ($_GET['state'] !== session('O365_AUTH_STATE'))){
            // #4
            $this->abortOAuth(500, 'Authentication state not matched.  Aborted.');
        }
    }

    private function getAuthenticatedUserDataOrAbort()
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
            $this->matchDomainsOrAbort($userData);
            return $userData;
        } catch (\Exception $e) {
            // #4
            $this->abortOAuth(500, 'User information not retrieved.  Authentication failed. ' . $e->getMessage());
        }
    }

    private function getAccessTokenOrAbort()
    {
        if (! ($token = $this->getAccessToken())) {
            // #4
            $this->abortOAuth(500, "Office 365 access token doesn't exist.  User info cannot be retrieved.");
        }
        // If a valid token is retrieved, put it in session for later use, for example, allowing user to upload file.
        session(['access_token' => $token]);
        return $token;
    }

    private function matchDomainsOrAbort(array $userData)
    {
        $matched = false;
        $domainsStr = strtolower(trim(env('O365_DOMAIN')));
        if ($domainsStr) {
            $domains = explode(',', $domainsStr);
            foreach ($domains as $domain) {
                if (ends_with($userData['email'], trim($domain))) {
                    $matched = true;
                }
            }
        }

        if (! $matched) {
            // #5
            $this->abortOAuth(403, "Authorization failed.  Your email account is valid but not authorized to access this system.");
        }
    }

    private function findOrCreateUser(array $userData)
    {
        $user = \User::where('email', $userData['email'])->first();

        $timestamp = date('Y-m-d H:i:s');

        if (! $user) {
            // email_verified_at is added in Laravel 5.7.  It is ignored if the field doesn't exist for older version of Laravel
            $user = \User::create(array_merge($userData, ['email_verified_at' => $timestamp, 'password' => Hash::make(str_random())]));
        }

        if ($user) {
            if (array_key_exists('email_verified_at', $user->getAttributes()) && ! $user->email_verified_at) {
                $user->email_verified_at = $timestamp;
                $user->save();
            }
        } else {
            // #3 User Model Error
            $this->abortOAuth(500, 'Neither retrieved nor created user in our system as needed. Authentication failed.');
        }

        return $user;
    }

    private function abortOAuth($code, $message)
    {
        session(['O365_AUTH_STATE' => '']);
        // Use a GoodSystem view if found.
        $viewName = 'good-system::errors.' . $code;
        if (view()->exists($viewName)) {
            return view($viewName)->with('message', $message);
        }
        abort($code, $message);
    }
}
