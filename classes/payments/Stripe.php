<?php

namespace OFFLINE\MicroCart\Classes\Payments;

use October\Rain\Exception\ValidationException;
use OFFLINE\MicroCart\Models\PaymentGatewaySettings;
use Omnipay\Common\GatewayInterface;
use Omnipay\Omnipay;
use Throwable;
use Validator;

/**
 * Process the payment via Stripe.
 */
class Stripe extends PaymentProvider
{
    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'Stripe';
    }

    /**
     * {@inheritdoc}
     */
    public function identifier(): string
    {
        return 'stripe';
    }

    /**
     * {@inheritdoc}
     */
    public function validate(): bool
    {
        $rules      = [
            'token' => 'required|size:28|regex:/tok_[0-9a-zA-z]{24}/',
        ];
        $validation = Validator::make($this->data, $rules);
        if ($validation->fails()) {
            throw new ValidationException($validation);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function process(PaymentResult $result): PaymentResult
    {
        $gateway = Omnipay::create('Stripe');
        $gateway->setApiKey(decrypt(PaymentGatewaySettings::get('stripe_api_key')));
        $response = null;

        try {
            $response = $this->createCustomer($gateway);
            if ( ! $response->isSuccessful()) {
                return $result->fail([], $response);
            }

            $customerReference = $response->getCustomerReference();
            $cardReference     = $response->getCardReference();

            $response = $gateway->purchase([
                'amount'            => round($this->cart->totals->grandPostTaxes / 100, 2),
                'description'       => 'Payment for Cart ID  ' . $this->cart->id,
                'currency'          => $this->cart->currency,
                'customerReference' => $customerReference,
                'cardReference'     => $cardReference,
                'returnUrl'         => $this->returnUrl(),
                'cancelUrl'         => $this->cancelUrl(),
            ])->send();
        } catch (Throwable $e) {
            return $result->fail([], $e);
        }

        $data = (array)$response->getData();
        if ( ! $response->isSuccessful()) {
            return $result->fail($data, $response);
        }

        return $result->success($data, $response);
    }

    /**
     * {@inheritdoc}
     */
    public function settings(): array
    {
        return [
            'stripe_api_key'         => [
                'label'   => 'offline.microcart::lang.payment_gateway_settings.stripe.api_key',
                'comment' => 'offline.microcart::lang.payment_gateway_settings.stripe.api_key_comment',
                'span'    => 'left',
                'type'    => 'text',
            ],
            'stripe_publishable_key' => [
                'label'   => 'offline.microcart::lang.payment_gateway_settings.stripe.publishable_key',
                'comment' => 'offline.microcart::lang.payment_gateway_settings.stripe.publishable_key_comment',
                'span'    => 'left',
                'type'    => 'text',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function encryptedSettings(): array
    {
        return ['stripe_api_key'];
    }

    /**
     * Create a new customer.
     *
     * @param GatewayInterface $gateway
     *
     * @return mixed
     */
    protected function createCustomer(GatewayInterface $gateway)
    {
        $name = $this->buildCustomerName();

        return $gateway->createCustomer([
            'name'        => $name,
            'description' => 'Created by OFFLINE.MicroCart',
            'source'      => $this->data['token'] ?? false,
            'email'       => $this->data['email'] ?? 'nobody@unknown.org',
            'shipping'    => $this->getShippingInformation(),
            'metadata'    => [
                'name' => $name,
            ],
        ])->send();
    }


    /**
     * Get all available shipping information.
     *
     * @return array
     */
    protected function getShippingInformation(): array
    {
        $name = $this->buildCustomerName();
        if ($this->data['shipping_company']) {
            $name = sprintf('%s (%s)', $name, $this->data['shipping_company']);
        }

        $addressLines = explode("\n", $this->data['shipping_lines']);

        return [
            'name'    => $name,
            'address' => [
                'line1'       => $addressLines[0] ?? '',
                'line2'       => $addressLines[1] ?? '',
                'city'        => $this->data['shipping_city'],
                'country'     => $this->data['shipping_country'],
                'postal_code' => $this->data['shipping_zip'],
            ],
        ];
    }

    /**
     * Build the customer's name based on the billing or shipping information.
     *
     * @return string
     */
    protected function buildCustomerName(): string
    {
        return sprintf(
            '%s %s',
            $this->data['billing_firstname'] ?: $this->data['shipping_firstname'],
            $this->data['billing_lastname'] ?: $this->data['shipping_lastname']
        );
    }
}