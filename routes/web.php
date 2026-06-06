<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['status' => 'Nia Life OS API', 'version' => '1.0.0']);
});

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});
