<?php

namespace OFFLINE\MicroCart\Classes\Payments;

use OFFLINE\MicroCart\Models\Cart;
use OFFLINE\MicroCart\Models\PaymentMethod;
use Session;

/**
 * The DefaultPaymentGateway is responsible for the orchestration
 * of all available payment providers.
 *
 * When a payment is being processed, the gateway sets up
 * all needed data to process this payment.
 */
class DefaultPaymentGateway implements PaymentGateway
{
    /**
     * The currently active PaymentProvider.
     * @var PaymentProvider
     */
    protected $provider;
    /**
     * An array of all registered PaymentProviders.
     * @var PaymentProvider[]
     */
    protected $providers = [];

    /**
     * {@inheritdoc}
     */
    public function init(PaymentMethod $paymentMethod, array $data)
    {
        $this->provider = $this->getProviderForMethod($paymentMethod);
        $this->provider->setData($data);
        $this->provider->validate();
    }

    /**
     * {@inheritdoc}
     */
    public function process(Cart $cart): PaymentResult
    {
        if ( ! $this->provider) {
            throw new \LogicException('Missing data for payment. Make sure to call init() before process()');
        }

        $this->provider->init();

        Session::put('microCart.payment.id', str_random(8));

        $this->provider->setCart($cart);
        $result = new PaymentResult($this->provider, $cart);

        return $this->provider->process($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getProviders(): array
    {
        return ProviderManager::instance()->all();
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveProvider(): PaymentProvider
    {
        return $this->provider;
    }

    /**
     * Get the PaymentProvider that belongs to a PaymentMethod.
     *
     * @param PaymentMethod $method
     *
     * @return PaymentProvider
     */
    protected function getProviderForMethod(PaymentMethod $method): PaymentProvider
    {
        $providers = ProviderManager::instance()->all();
        if (isset($providers[$method->payment_provider])) {
            return new $providers[$method->payment_provider];
        }

        throw new \LogicException(
            sprintf('The selected payment provider "%s" is unavailable.', $method->payment_provider)
        );
    }
}
