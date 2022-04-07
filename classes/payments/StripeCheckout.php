<?php

namespace OFFLINE\MicroCart\Classes\Payments;

use DigiTickets\Stripe\CheckoutGateway;
use DigiTickets\Stripe\Lib\ComplexTransactionRef;
use October\Rain\Exception\ValidationException;
use OFFLINE\MicroCart\Models\PaymentGatewaySettings;
use Omnipay\Common\GatewayInterface;
use Omnipay\Omnipay;
use Throwable;
use Validator;
use Session;

/**
 * Process the payment via Stripe.
 */
class StripeCheckout extends PaymentProvider
{
    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'StripeCheckout';
    }

    /**
     * {@inheritdoc}
     */
    public function identifier(): string
    {
        return 'stripe-checkout';
    }

    /**
     * {@inheritdoc}
     */
    public function validate(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function process(PaymentResult $result): PaymentResult
    {
        $gateway = $this->getGateway();

        try {
            $items = $this->cart->items->map(function ($item) {
                return [
                    'name' => $item->name,
                    'price' => $item->price / 100,
                    'quantity' => $item->quantity,
                    'description' => $item->description
                ];
            })->toArray();

            $request = $gateway->purchase([
                'description'       => 'Payment for Cart ID  ' . $this->cart->id,
                'currency'          => $this->cart->currency,
                'returnUrl'         => $this->returnUrl(),
                'cancelUrl'         => $this->cancelUrl(),
                'transactionId'     => $this->cart->id,
                'customerEmail'     => $this->cart->email,
                'items'             => $items
            ]);

            $response = $request->send();
            $data = (array)$response->getData();

            Session::put('microCart.payment.callback', self::class);
            Session::put('microCart.stripecheckout.session_id', $response->getSessionID());

            if (!isset($data['session']['url'])) {
                return $result->fail([], $response);
            }

            return $result->redirect($data['session']['url']);

        } catch (Throwable $e) {
            return $result->fail([], $e);
        }
    }

    public function complete(PaymentResult $result): PaymentResult
    {
        try {
            $gateway = $this->getGateway();

            $transactionReference = (new ComplexTransactionRef(Session::pull('microCart.stripecheckout.session_id')))->asJson();

            $response = $gateway->completePurchase(['transactionReference' => $transactionReference])->send();

            if ($response->isSuccessful()) {
                return $result->success($response->getData(), $response);
            }

            return $result->fail([], $response);
        }
        catch (Throwable $e) {
            return $result->fail([], $e);
        }
    }

    protected function getGateway()
    {
        $gateway = Omnipay::create(CheckoutGateway::class);
        $gateway->setApiKey(decrypt(PaymentGatewaySettings::get('stripe_checkout_api_key')));

        return $gateway;
    }

    /**
     * {@inheritdoc}
     */
    public function settings(): array
    {
        return [
            'stripe_checkout_api_key'         => [
                'label'   => 'offline.microcart::lang.payment_gateway_settings.stripe.api_key',
                'comment' => 'offline.microcart::lang.payment_gateway_settings.stripe.api_key_comment',
                'span'    => 'left',
                'type'    => 'text',
            ],
            'stripe_checkout_publishable_key' => [
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
        return ['stripe_checkout_api_key'];
    }
}
