<?php namespace OFFLINE\MicroCart;

use OFFLINE\MicroCart\Classes\Money;
use OFFLINE\MicroCart\Classes\Payments\DefaultPaymentGateway;
use OFFLINE\MicroCart\Classes\Payments\Offline;
use OFFLINE\MicroCart\Classes\Payments\PaymentGateway;
use OFFLINE\MicroCart\Classes\Payments\PayPalRest;
use OFFLINE\MicroCart\Classes\Payments\Stripe;
use OFFLINE\MicroCart\Models\GeneralSettings;
use OFFLINE\MicroCart\Models\PaymentGatewaySettings;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public function boot()
    {
        $this->app->singleton(PaymentGateway::class, function () {
            $gateway = new DefaultPaymentGateway();
            $gateway->registerProvider(new Offline());
            $gateway->registerProvider(new PayPalRest());
            $gateway->registerProvider(new Stripe());

            return $gateway;
        });

        $moneyFns = array_filter(\Event::fire('offline.microcart.moneyformatter'), 'is_callable');
        if (count($moneyFns)) {
            Money::instance()->setFormatter(array_first($moneyFns));
        }
    }

    public function registerMarkupTags()
    {
        return [
            'filters' => [
                'microcart_money' => [Money::instance(), 'format'],
            ],
        ];
    }

    public function registerComponents()
    {
        return [
            \OFFLINE\MicroCart\Components\Cart::class => 'cart',
        ];
    }

//    public function registerNavigation()
//    {
//        return [
//            'main-menu-item' => [
//                'label'        => 'offline.microcart::lang.common.orders',
//                'url'          => Backend::url('offline/microcart/carts'),
//                'iconSvg'      => 'plugins/offline/microcart/assets/icon.svg',
//            ],
//        ];
//    }

    public function registerSettings()
    {
        return [
            'general_settings'          => [
                'label'       => 'offline.microcart::lang.general_settings.label',
                'description' => 'offline.microcart::lang.general_settings.description',
                'category'    => 'offline.microcart::lang.plugin.name',
                'icon'        => 'icon-shopping-cart',
                'class'       => GeneralSettings::class,
                'order'       => 0,
                'permissions' => ['offline.microcart.settings.manage_general'],
                'keywords'    => 'shop store microcart general',
            ],
            'payment_gateways_settings' => [
                'label'       => 'offline.microcart::lang.payment_gateway_settings.label',
                'description' => 'offline.microcart::lang.payment_gateway_settings.description',
                'category'    => 'offline.microcart::lang.plugin.name',
                'icon'        => 'icon-credit-card',
                'class'       => PaymentGatewaySettings::class,
                'order'       => 30,
                'permissions' => ['offline.microcart.settings.manage_payment_gateways'],
                'keywords'    => 'shop store microCart payment gateways',
            ],
            'payment_method_settings'   => [
                'label'       => 'offline.microcart::lang.common.payment_methods',
                'description' => 'offline.microcart::lang.payment_method_settings.description',
                'category'    => 'offline.microcart::lang.plugin.name',
                'icon'        => 'icon-money',
                'url'         => \Backend::url('offline/microcart/paymentmethods'),
                'order'       => 40,
                'permissions' => ['offline.microcart.settings.manage_payment_methods'],
                'keywords'    => 'shop store microCart payment methods',
            ],
        ];
    }
}
