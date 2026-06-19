<?php

foreach (glob(app_path('*/Routes/web_*.php')) as $routeFile) {
    require $routeFile;
}
