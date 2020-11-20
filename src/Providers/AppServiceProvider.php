<?php

namespace Uccello\UccelloCouchbaseSync\Providers;

use Illuminate\Support\ServiceProvider;
use Uccello\UccelloCouchbaseSync\Console\Commands\SyncsFromCouchbase;

/**
 * App Service Provider
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    public function boot()
    {
        // Views
        // $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'uccello-couchbase-sync');

        // Translations
        // $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'uccello-couchbase-sync');

        // Migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Routes
        $this->loadRoutesFrom(__DIR__ . '/../Http/routes.php');

        // Publish assets
        $this->publishes([
            __DIR__ . '/../../public' => public_path('vendor/uccello/uccello-couchbase-sync'),
        ], 'uccello-couchbase-sync-assets');

        // Config
        $this->publishes([
            __DIR__ . '/../../config/couchbase.php' => config_path('couchbase.php'),
        ], 'uccello-couchbase-sync-config');
    }

    public function register()
    {
        // Config
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/couchbase.php',
            'couchbase'
        );

        // Commands
        $this->commands([
            SyncsFromCouchbase::class
        ]);
    }
}
