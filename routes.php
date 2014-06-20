<?php

Route::group(['prefix' => 'api_responsiv_pay'], function() {

    Route::any('{code}/{slug}', ['Responsiv\Pay\Classes\GatewayManager', 'runAccessPoint'])->where('slug', '(.*)?');

});