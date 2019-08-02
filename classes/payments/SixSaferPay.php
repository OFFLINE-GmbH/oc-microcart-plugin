<?php

namespace OFFLINE\MicroCart\Classes\Payments;


use October\Rain\Exception\ValidationException;
use OFFLINE\MicroCart\Models\PaymentGatewaySettings;
use Session;
use Throwable;
use Ticketpark\SaferpayJson\Container;
use Ticketpark\SaferpayJson\Message\ErrorResponse;
use Ticketpark\SaferpayJson\PaymentPage\AssertRequest;
use Ticketpark\SaferpayJson\PaymentPage\InitializeRequest;

class SixSaferPay extends PaymentProvider
{
    protected $customerId;
    protected $terminalId;
    protected $apiSecret;
    protected $apiKey;

    public function init()
    {
        $this->customerId = PaymentGatewaySettings::get('six_customer_id');
        $this->apiKey     = PaymentGatewaySettings::get('six_api_key');
        $this->apiSecret  = PaymentGatewaySettings::get('six_api_secret');
        $this->terminalId = PaymentGatewaySettings::get('six_terminal_id');
    }

    /**
     * Return the display name of this payment provider.
     *
     * @return string
     */
    public function name(): string
    {
        return 'SIX SaferPay';
    }

    /**
     * Return a unique identifier for this payment provider.
     *
     * @return string
     */
    public function identifier(): string
    {
        return 'six-saferpay';
    }

    /**
     * Validate the given input data for this payment.
     *
     * @return bool
     * @throws ValidationException
     */
    public function validate(): bool
    {
        return true;
    }

    /**
     * Process the payment.
     *
     * @param PaymentResult $result
     *
     * @return PaymentResult
     */
    public function process(PaymentResult $result): PaymentResult
    {
        try {
            $requestHeader = (new Container\RequestHeader())
                ->setCustomerId($this->customerId)
                ->setRequestId(uniqid('six', true));

            $amount = (new Container\Amount())
                ->setCurrencyCode($this->cart->currency)
                ->setValue($this->cart->totals->grandPostTaxes); // amount in cents

            $payment = (new Container\Payment())
                ->setAmount($amount)
                ->setOrderId($this->cart->id)
                ->setDescription('MicroCart Order ' . $this->cart->id);

            $shippingAddress = (new Container\Address())
                ->setFirstName($this->data['shipping_firstname'])
                ->setLastName($this->data['shipping_lastname'])
                ->setStreet($this->data['shipping_lines'])
                ->setZip($this->data['shipping_zip'])
                ->setCity($this->data['shipping_city'])
                ->setCountryCode($this->data['shipping_country']);

            $billingAddress = null;
            if (isset($this->data['billing_differs']) && $this->data['billing_differs']) {
                $billingAddress = (new Container\Address())
                    ->setFirstName($this->data['billing_firstname'])
                    ->setLastName($this->data['billing_lastname'])
                    ->setStreet($this->data['billing_lines'])
                    ->setZip($this->data['billing_zip'])
                    ->setCity($this->data['billing_city'])
                    ->setCountryCode($this->data['billing_country']);
            }

            $payer = (new Container\Payer())
                ->setLanguageCode('en')
                ->setDeliveryAddress($shippingAddress);

            if ($billingAddress) {
                $payer->setBillingAddress($billingAddress);
            }

            $returnUrls = (new Container\ReturnUrls())
                ->setSuccess($this->returnUrl())
                ->setFail($this->failUrl())
                ->setAbort($this->cancelUrl());

            $response = (new InitializeRequest($this->apiKey, $this->apiSecret))
                ->setRequestHeader($requestHeader)
                ->setPayment($payment)
                ->setTerminalId($this->terminalId)
                ->setPayer($payer)
                ->setReturnUrls($returnUrls)
                ->execute();

        } catch (Throwable $e) {
            return $result->fail([], $e);
        }

        if ($response instanceof ErrorResponse) {
            return $result->fail($this->extractError($response), $response);
        }

        Session::put('microCart.payment.callback', self::class);
        Session::put('microCart.saferpay.token', $response->getToken());

        return $result->redirect($response->getRedirectUrl());
    }

    /**
     * PayPal has processed the payment and redirected the user back.
     *
     * @param PaymentResult $result
     *
     * @return PaymentResult
     */
    public function complete(PaymentResult $result): PaymentResult
    {
        $customerId = PaymentGatewaySettings::get('six_customer_id');
        $token      = Session::pull('microCart.saferpay.token');

        try {
            $requestHeader = (new Container\RequestHeader())
                ->setCustomerId($customerId)
                ->setRequestId(uniqid('six', true));

            $response = (new AssertRequest($this->apiKey, $this->apiSecret))
                ->setRequestHeader($requestHeader)
                ->setToken($token)
                ->execute();

        } catch (Throwable $e) {
            return $result->fail([], $e);
        }

        if ($response instanceof ErrorResponse) {
            return $result->fail($this->extractError($response), $response);
        }

        $data = ['id' => $response->getTransaction()->getId()];

        return $result->success($data, $response);
    }


    /**
     * {@inheritdoc}
     */
    public function settings(): array
    {
        return [
            'six_customer_id' => [
                'label' => 'offline.microcart::lang.payment_gateway_settings.six.customer_id',
                'span'  => 'auto',
                'type'  => 'text',
            ],
            'six_terminal_id' => [
                'label' => 'offline.microcart::lang.payment_gateway_settings.six.terminal_id',
                'span'  => 'auto',
                'type'  => 'text',
            ],
            'six_api_key'     => [
                'label' => 'offline.microcart::lang.payment_gateway_settings.six.api_key',
                'span'  => 'auto',
                'type'  => 'text',
            ],
            'six_api_secret'  => [
                'label' => 'offline.microcart::lang.payment_gateway_settings.six.api_secret',
                'span'  => 'auto',
                'type'  => 'text',
            ],
        ];
    }

    /**
     * @param ErrorResponse $response
     *
     * @return array
     */
    protected function extractError(ErrorResponse $response): array
    {
        $data = [
            'name'   => $response->getErrorName(),
            'msg'    => $response->getErrorMessage(),
            'detail' => $response->getErrorDetail(),
        ];

        return $data;
    }
}