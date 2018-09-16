# Office 365 PHP Auth

This package adds authenticate against Office 365 to a Laravel application.

The package is largely based on program logic from [`microsoftgraph/php-connect-sample`](https://github.com/microsoftgraph/php-connect-sample), but aimed to easier integration with Laravel 5, and utilized more Laravel native things.

## Expected Behaviors

- Failed authentication of an Office 365 account won't be granted access -- 500 error
- Successful authentication of an Office 365 account that doesn't belong to the specified domain won't be granted access -- 404 error
- Upon a successful authentication with an Office 365 account that is on the specified domain, user is redirected to the previous page, or to site root URL.  
- If the successfully authenticated user is NOT in the system, the user would be added
- New user or existing user, if successfully authenticated, will be able to bypass email verification (verification flag will be set manually)

## Installation and essential configurations

### Install package with Composer 

**(NOTE: packagist not updated yet, the following may not work)**

First, run `composer require singingfox/o365auth` under Laraval application root directory.

Add the following to `composer.json`

`Singingfox\\O365Auth\\": "vendor/singingfox/o365auth/src`

Add the following to `config/app.php`

`Singingfox\O365Auth\O365AuthServiceProvider::class,`

Run 

`composer dump-autoload`

Alternatively, add then following to `composer.json` then run `composer update`

```
"required": [
    ...
    "singingfox/o365auth": "*"
],
```
 
#### Dependencies

When installing this package, the following packages and their dependencies are going to be pulled in as well. 

```
"league/oauth2-client": "^2.3.*",
"microsoft/microsoft-graph": "^1.3.*"
```

### Configurations

#### Office 365 parameters needed in Laravel .env
     
Add the following to .env file 

```
O365_DOMAIN=ALLOWED-EMAIL-DOMAIN-NAME
O365_CLIENT_ID=YOUR-APPLICATION-ID-OR-CLIENT-ID-IN-CREATED-MICROSOFT-APPLICATION
O365_CLIENT_SECRET=YOUR-CLIENT-SECRETE-OR-CLIENT-PASSWORD-IN-CREATED-MICROSOFT-APPLICATION
# This needs to be the full URL (https)
O365_REDIRECT_URL=YOUR-REDIRECT-URL-IN-CREATED-MICROSOFT-APPLICATION
```

If not specified, a successful authentication will attempt to redirect application to the URL immediately prior to the authentication, or fall back to web root "/".

#### Display specific errors 

Add a new view `resources/views/errors/500.blade.php`.  The content could be as simple as 

```html
<h1>{{ $exception->getMessage() }}</h1>
```

This is optional, but if done, specific error message from this package, could be displayed.  Otherwise, the following generic error message will be displayed for all errors:
 
`Whoops, looks like something went wrong.`

## What to do after authentication

At this time, the built-in program logic try to 

The following is a sample of what can be done after successful authentication:

1. Retrieve `access_token` as stored in session
2. Initialize a `Graph` object, and assign the token to it
3. Now we should be able to call all kinds of Graph API endpoints and do whatever we need to do
4. Optionally, `O365_AFTER_AUTH_URL` can be specified in `.env` under Laraval application root, then a successful authentication process would be followed by some immediate actions, such as setting authenticated user locally, etc.  The following sample route doesn't really do that.  It only illustrates how to get a piece of user info -- email.

```php
Route::get("/o365-user/email", function () {
    if (session_status() == PHP_SESSION_NONE)
        session_start();

    $graph = new \Microsoft\Graph\Graph();
    $graph->setAccessToken($_SESSION['access_token']);

    $me = $graph->createRequest("get", "/me")
        ->setReturnType(\Microsoft\Graph\Model\User::class)
        ->execute();

    return $me->getMail();
});
```

This authentication process can also be used to register new users that allows for access.