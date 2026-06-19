<?php

foreach (glob(app_path('*/Routes/api_*.php')) as $routeFile) {
    require $routeFile;
}
