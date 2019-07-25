<?php

namespace OFFLINE\MicroCart\Classes\Payments;

use October\Rain\Exception\ValidationException;
use OFFLINE\MicroCart\Models\Cart;
use OFFLINE\MicroCart\Models\PaymentMethod;

/**
 * The PaymentGateway is responsible for the orchestration
 * of all available payment providers.
 *
 * When a payment is being processed, the gateway sets up
 * all needed data to process this payment.
 */
interface PaymentGateway
{
    /**
     * Register a new PaymentProvider on this gateway.
     *
     * @param PaymentProvider $provider
     *
     * @return PaymentProvider
     */
    public function registerProvider(PaymentProvider $provider): PaymentProvider;

    /**
     * Initialize the PaymentGateway.
     *
     * @param PaymentMethod $paymentMethod
     * @param array         $data
     *
     * @throws ValidationException
     */
    public function init(PaymentMethod $paymentMethod, array $data);

    /**
     * Process the payment.
     *
     * @param Cart $cart
     *
     * @return PaymentResult
     */
    public function process(Cart $cart): PaymentResult;

    /**
     * Find a PaymentProvider by its ID.
     *
     * @param string $identifier
     *
     * @return PaymentProvider
     */
    public function getProviderById(string $identifier): PaymentProvider;

    /**
     * Get an array of all available providers.
     * @return array
     */
    public function getProviders(): array;

    /**
     * Get the currently active provider.
     *
     * @return PaymentProvider
     */
    public function getActiveProvider(): PaymentProvider;
}
