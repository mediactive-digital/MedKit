<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Helpers\TranslationHelper;
use App\Helpers\FormatHelper;

use Illuminate\Foundation\AliasLoader;

use Barryvdh\Debugbar\Facade;

class AliasServiceProvider extends ServiceProvider {
    
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() {

        $loader = AliasLoader::getInstance();

        $loader->alias('Debugbar', Facade::class);
        $loader->alias('Translation', TranslationHelper::class);
        $loader->alias('Format', FormatHelper::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot() {

    }
}
