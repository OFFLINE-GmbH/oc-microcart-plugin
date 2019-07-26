<?php

namespace OFFLINE\MicroCart\Classes\Payments;


use October\Rain\Support\Traits\Singleton;
use System\Classes\PluginManager;

class ProviderManager
{
    use Singleton;

    protected $providers;

    protected $pluginManager;

    /**
     * Initialize this singleton.
     */
    protected function init()
    {
        $this->pluginManager = PluginManager::instance();
    }

    protected function loadProviders()
    {
        $this->providers = collect(PluginManager::instance()->getPlugins())->mapWithKeys(function ($plugin) {
            if ( ! method_exists($plugin, 'registerPaymentProviders')) {
                return [];
            }

            $providers = $plugin->registerPaymentProviders();
            if ( ! is_array($providers)) {
                return [];
            }

            return collect($providers)->mapWithKeys(function(PaymentProvider $gateway) {
                return [$gateway->identifier() => $gateway];
            });
        })->filter();
    }

    public function all()
    {
        if ($this->providers === null) {
            $this->loadProviders();
        }

        return collect($this->providers);
    }
}