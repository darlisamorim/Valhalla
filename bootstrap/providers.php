<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\LojaPanelProvider;
use App\Providers\Filament\MasterPanelProvider;
use App\Providers\NucleoServiceProvider;

return [
    AppServiceProvider::class,
    NucleoServiceProvider::class,
    LojaPanelProvider::class,
    MasterPanelProvider::class,
];
