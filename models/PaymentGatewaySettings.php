<?php

namespace OFFLINE\MicroCart\Models;

use Illuminate\Support\Collection;
use Model;
use October\Rain\Database\Traits\Encryptable;
use OFFLINE\MicroCart\Classes\Payments\PaymentGateway;
use OFFLINE\MicroCart\Classes\Payments\PaymentProvider;
use OFFLINE\MicroCart\Classes\Payments\ProviderManager;
use Session;
use System\Classes\PluginManager;

class PaymentGatewaySettings extends Model
{
    use Encryptable;

    protected $encryptable = [];

    public $implement = ['System.Behaviors.SettingsModel'];
    public $settingsCode = 'offline_microcart_payment_gateway_settings';
    public $settingsFields = '$/offline/microcart/models/settings/fields_payment_gateways.yaml';

    /**
     * @var PaymentGateway
     */
    protected $gateway;
    /**
     * @var Collection<PaymentProvider>
     */
    protected $providers;


    public function __construct(array $attributes = [])
    {
        $this->gateway   = app(PaymentGateway::class);

        $this->providers = ProviderManager::instance()->all();
        $this->providers->each(function ($provider) {
            $this->encryptable = array_merge($this->encryptable, $provider->encryptedSettings());
        });
        parent::__construct($attributes);
    }

    /**
     * Extend the setting form with input fields for each
     * registered plugin.
     */
    public function getFieldConfig()
    {
        if ($this->fieldConfig !== null) {
            return $this->fieldConfig;
        }

        $config                 = parent::getFieldConfig();
        $config->tabs['fields'] = [];

        $this->providers->each(function ($provider) use ($config) {
            $settings = $this->setDefaultTab($provider->settings(), $provider->name());

            $config->tabs['fields'] = array_merge($config->tabs['fields'], $settings);
        });

        return $config;
    }

    protected function setDefaultTab(array $settings, $tab)
    {
        return array_map(function ($i) use ($tab) {
            if ( ! isset($i['tab'])) {
                $i['tab'] = $tab;
            }

            return $i;
        }, $settings);
    }
}
