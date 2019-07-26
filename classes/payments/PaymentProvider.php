<?php

namespace OFFLINE\MicroCart\Classes\Payments;

use October\Rain\Exception\ValidationException;
use OFFLINE\MicroCart\Models\Cart;
use OFFLINE\MicroCart\Models\PaymentGatewaySettings;
use Request;
use Session;
use Url;

/**
 * A PaymentProvider handles the integration with external
 * payment providers.
 */
abstract class PaymentProvider
{
    /**
     * The cart that is being paid.
     *
     * @var Cart
     */
    public $cart;
    /**
     * Data that is needed for the payment.
     *
     * @var array
     */
    public $data;

    /**
     * Return the display name of this payment provider.
     *
     * @return string
     */
    abstract public function name(): string;

    /**
     * Return a unique identifier for this payment provider.
     *
     * @return string
     */
    abstract public function identifier(): string;

    /**
     * Return any custom backend settings fields.
     *
     * @return array
     */
    abstract public function settings(): array;

    /**
     * Validate the given input data for this payment.
     *
     * @return bool
     * @throws ValidationException
     */
    abstract public function validate(): bool;

    /**
     * Process the payment.
     *
     * @param PaymentResult $result
     *
     * @return PaymentResult
     */
    abstract public function process(PaymentResult $result): PaymentResult;

    /**
     * PaymentProvider constructor.
     *
     * Optionally pass an cart or payment data.
     *
     * @param Cart|null $cart
     * @param array     $data
     */
    public function __construct(Cart $cart = null, array $data = [])
    {
        if ($cart) {
            $this->setCart($cart);
        }
        if ($data) {
            $this->setData($data);
        }
    }

    /**
     * Do your custom initialization in here.
     */
    public function init()
    {

    }

    /**
     * Fields returned from this method are stored encrypted.
     *
     * Use this to store API tokens and other secret data
     * that is needed for this PaymentProvider to work.
     *
     * @return array
     */
    public function encryptedSettings(): array
    {
        return [];
    }

    /**
     * Set the cart that is being paid.
     *
     * @param null|Cart
     *
     * @return PaymentProvider
     */
    public function setCart(?Cart $cart)
    {
        $this->cart = $cart;
        Session::put('microCart.payment.cart', optional($this->cart)->id);

        return $this;
    }

    /**
     * Set the data for this payment.
     *
     * @param array $data
     *
     * @return PaymentProvider
     */
    public function setData(array $data)
    {
        $this->data = $data;
        Session::put('microCart.payment.data', $data);

        return $this;
    }

    /**
     * Get the settings of this PaymentProvider.
     *
     * @return \October\Rain\Support\Collection
     */
    public function getSettings()
    {
        return collect($this->settings())->mapWithKeys(function ($settings, $key) {
            return [$key => PaymentGatewaySettings::get($key)];
        });
    }

    /**
     * Get an cart that was stored in the session.
     *
     * This is used to get the current cart back into memory after the
     * user has been redirected to an external payment service.
     *
     * @return Cart
     */
    public function getCartFromSession(): Cart
    {
        $id = Session::pull('microCart.payment.cart');

        return Cart::findOrFail($id);
    }

    /**
     * Return URL passed to external payment services.
     *
     * The user will be redirected back to this URL once the external
     * payment service has done its work.
     *
     * @return string
     */
    public function returnUrl(): string
    {
        return Request::url() . '?' . http_build_query([
                'return'                  => 'return',
                'oc-microcart-payment-id' => $this->getPaymentId(),
            ]);
    }

    /**
     * Fail URL passed to external payment services.
     *
     * The user will be redirected back to this URL if an external error occurs.
     *
     * @return string
     */
    public function failUrl(): string
    {
        return Request::url() . '?' . http_build_query([
                'return'                  => 'fail',
                'oc-microcart-payment-id' => $this->getPaymentId(),
            ]);
    }

    /**
     * Cancel URL passed to external payment services.
     *
     * The user will be redirected back to this URL if she cancels
     * the payment on an external payment service.
     *
     * @return string
     */
    public function cancelUrl(): string
    {
        return Request::url() . '?' . http_build_query([
                'return'                  => 'cancel',
                'oc-microcart-payment-id' => $this->getPaymentId(),
            ]);
    }

    /**
     * Get this payment's id form the session.
     *
     * @return string
     */
    public function getPaymentId()
    {
        return Session::get('microCart.payment.id');
    }
}
