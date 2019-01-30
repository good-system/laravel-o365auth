<?php
// At this time, all configurations are NOT being published, hence NOT configurable at application level.

return [
    'baseUrl' => 'https://login.microsoftonline.com/common',
    'authorizeUrl' => '/oauth2/v2.0/authorize',
    'tokenUrl' => '/oauth2/v2.0/token',
    'scopes' => 'profile openid User.Read Group.Read.All',

    // The following are placeholder configurations.  Not in use yet.

    // Grant system access to users from any domain -- disallowed by default.
    // If desired to allow all domain, set "allow-any-domain" to true, and set "O365_DOMAIN" in .env to "*".
    // Originally this package is intended to help with admin access.  So, intentionally making it a little less easy
    // to allow any domain, just in case access is changed by accident.
    'allow-any-domain' => false,
    // When authenticated, if the user doesn't already exist in the system, create it.  And "user-model" specifies the
    // model to be used.
    // When not specified, authentication will fail, even if OAuth was successful.
    'user-model' => 'App\User',
];