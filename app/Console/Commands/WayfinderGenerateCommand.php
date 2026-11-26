<?php

namespace App\Console\Commands;

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Str;
use Laravel\Wayfinder\GenerateCommand as BaseGenerateCommand;

class WayfinderGenerateCommand extends BaseGenerateCommand
{
    protected $signature = 'wayfinder:generate {--path=} {--skip-actions} {--skip-routes} {--skip-database} {--with-form}';

    public function handle()
    {
        $router = app('router');
        $originalRoutes = $router->getRoutes();
        $filteredCollection = new RouteCollection;

        $excludedRoutePatterns = (array) config('wayfinder.exclude.routes', ['horizon.*']);
        $excludedUriPatterns = (array) config('wayfinder.exclude.uris', ['horizon*']);
        $excludedControllers = (array) config('wayfinder.exclude.controllers', ['Laravel\Horizon\*']);

        foreach ($originalRoutes as $route) {
            $name = $route->getName() ?: '';
            $uri = ltrim($route->uri(), '/');
            $action = ltrim($route->getActionName(), '\\');

            if (! empty($excludedRoutePatterns) && Str::is($excludedRoutePatterns, $name)) {
                continue;
            }

            if (! empty($excludedUriPatterns) && Str::is($excludedUriPatterns, $uri)) {
                continue;
            }

            if (! empty($excludedControllers) && Str::is($excludedControllers, $action)) {
                continue;
            }

            $filteredCollection->add($route);
        }

        $router->setRoutes($filteredCollection);

        try {
            return parent::handle();
        } finally {
            $router->setRoutes($originalRoutes);
        }
    }
}
