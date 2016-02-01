<?php

Route::group(['prefix' => 'api_responsiv_pay'], function() {

    Route::any('{code}/{slug}', function($code, $uri) {

        return \Responsiv\Pay\Classes\GatewayManager::runAccessPoint($code, $uri);

    })->where('slug', '(.*)?');

});