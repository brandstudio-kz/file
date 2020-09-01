<?php
namespace BrandStudio\File;

use Illuminate\Support\ServiceProvider;

class FileServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/config/file.php', 'file');

        if ($this->app->runningInConsole()) {
            $this->publish();
        }

    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publish();
        }
    }

    private function publish()
    {
        $this->publishes([
            __DIR__.'/config/file.php' => config_path('file.php')
        ], 'file');

    }

}
