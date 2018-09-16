<?php

Route::group(['middleware' => ['web']], function () {
    // URL to start authentication flow
    Route::get('/o365auth/init', 'Singingfox\O365Auth\OAuthController@init')->name('o365auth');
    // URL to start authentication flow
    Route::get('/o365auth/redirect', 'Singingfox\O365Auth\OAuthController@redirect')->name('o365auth.redirect');
});
