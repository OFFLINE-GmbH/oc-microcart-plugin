<?php

namespace OFFLINE\MicroCart\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use OFFLINE\MicroCart\Models\GeneralSettings;
use OFFLINE\MicroCart\Models\PaymentMethod;
use System\Classes\PluginManager;

abstract class PluginTestCase extends \PluginTestCase
{
    use DatabaseTransactions;

    public function setUp()
    {
        parent::setUp();

        $pluginManager = PluginManager::instance();
        $pluginManager->registerAll(true);
        $pluginManager->bootAll(true);

        GeneralSettings::set('default_currency', 'CHF');

        PaymentMethod::create([
            'price'            => 0.30,
            'payment_provider' => 'stripe',
            'percentage'       => 2.9,
            'name'             => 'Test method',
        ]);
    }

    public function tearDown()
    {
        parent::tearDown();

        $pluginManager = PluginManager::instance();
        $pluginManager->unregisterAll();
    }
}
