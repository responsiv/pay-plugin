<?php

// Access points
Route::group([
    'middleware' => Config::get('shop.middleware_group', 'web'),
    'prefix' => 'api_responsiv_pay',
], function() {
    Route::any('{code}/{slug?}', function($code, $uri) {
        return \Responsiv\Pay\Classes\GatewayManager::runAccessPoint($code, $uri);
    })->where('slug', '(.*)?');
});
