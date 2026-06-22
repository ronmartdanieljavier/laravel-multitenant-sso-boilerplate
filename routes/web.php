<?php

use Illuminate\Support\Facades\Route;

foreach (glob(app_path('*/Routes/web_*.php')) as $routeFile) {
    require $routeFile;
}

Route::get('/', function () {
    return redirect()->route('login');
});
