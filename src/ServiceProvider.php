<?php

namespace Elvenstar\StatamicTypesense;

use Http\Discovery\HttpClientDiscovery;
use Statamic\Facades\Search;
use Statamic\Providers\AddonServiceProvider;
use Typesense\Client as TypesenseClient;

class ServiceProvider extends AddonServiceProvider
{
    public function boot()
    {
        parent::boot();

        $this->bootAddonConfig();

        $this->bootSearchClient();
    }

    protected function bootAddonConfig()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/typesense.php', 'typesense');

        $this->publishes([
            __DIR__.'/../config/typesense.php' => config_path('typesense.php'),
        ]);

        return $this;
    }

    protected function bootSearchClient()
    {
        Search::extend('typesense', function ($app, array $config, $name) {
            $credentials = $config['credentials'];

            $client = new TypesenseClient([
                'api_key' => $credentials['api_key'],
                'nodes' => array_map(function($url) use ($credentials) {
                    return [
                        'host' => $url,
                        'port' => $credentials['port'],
                        'protocol' => $credentials['protocol'],
                    ];
                }, $credentials['nodes']),
                'nearest_node' => $credentials['nearest_node'],
                'connection_timeout_seconds' => $credentials['connection_timeout'],
                'client' => HttpClientDiscovery::find(),
            ]);

            return new Typesense\Index($client, $name, $config);
        });
    }
}
