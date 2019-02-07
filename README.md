# Office 365 PHP Auth

This package allows a Laravel application to authenticate users using their Office 365 accounts.

The package is largely based on program logic from [`microsoftgraph/php-connect-sample`](https://github.com/microsoftgraph/php-connect-sample), but aims to easier integration with Laravel 5, and utilized more Laravel specific things.

## Prerequisite

### Microsoft Application and `.env`

Create an application at https://apps.dev.microsoft.com.  And add the following to .env file with the application's parameters.

```
# If any of the following is missing, authentication will fail.
O365_DOMAIN=ALLOWED-EMAIL-DOMAIN-NAMES,SEPARATED-BY-COMMA
O365_CLIENT_ID=YOUR-APPLICATION-ID-OR-CLIENT-ID-IN-CREATED-MICROSOFT-APPLICATION
O365_CLIENT_SECRET=YOUR-CLIENT-SECRETE-OR-CLIENT-PASSWORD-IN-CREATED-MICROSOFT-APPLICATION
# This needs to be the full URL (https). 
O365_REDIRECT_URL=YOUR-REDIRECT-URL-IN-CREATED-MICROSOFT-APPLICATION
```

### User Model

This package will be looking for a Laravel model `\O365User`.  This could be an alias of `App\User` or other user class such as `GoodSystem\User`, explicitly set in the application.

## Installation

Run `composer require good-system/o365auth` under Laraval application root directory.

Laravel (5.6 and newer) should "discover" the package, without having to add service provider to `config/app.php`.
    
### Required Laravel Version

This package might work with Laravel framework before 5.7, it's not been tested.

## Error Page Templates  

Error templates in package will be looked up and used if exist.  Otherwise, fall back to default Laravel error display.

## Expected Behaviors

### Default Routes

Two default routes are provided: 

- `/o365auth/init`
- `/o365auth/redirect`

Users should always start at `/o365auth/init`, and then expect to be redirected to Office 365 authentication page at `https://login.microsoftonline.com/common/oauth2/v2.0/authorize`, with parameters.
 
### 1. Happy Scenario

Upon successful authentication with an Office 365 account on any of the domains specified by "O365_DOMAIN" in `.env`,

- if not exists in the system, and user model is configured properly, user record is added to the system (retrieved if already exists)
- system access is granted (Laravel manual authentication)
- user is finally redirected to the previous page or web root `/` (it doesn't have anything to do with "O365_REDIRECT_URL" in `.env`)
- Both new user and existing user will be able to bypass email verification, if not yet verified (verification flag will be set manually)

### 2. Laravel Authentication Error

If above #2 fails, which is unlikely, expect the system to throw a `500` error -- something really unexpected.

### 3. User Model Error

If above #1 fails due to bad configuration for user model, which is possible (but not expected), or some unknown error while adding user to system, also expect system to throw a `500` error.

### 4. Bad Data Coming from Microsoft

Could be one of the several scenarios.
  
### 5. Email Not on The Allowed Domains List

Expect the system to throw a `403` error.
