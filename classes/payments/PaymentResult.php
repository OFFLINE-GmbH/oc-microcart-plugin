<?php

namespace OFFLINE\MicroCart\Classes\Payments;

use OFFLINE\MicroCart\Classes\PaymentState\FailedState;
use OFFLINE\MicroCart\Classes\PaymentState\PaidState;
use OFFLINE\MicroCart\Classes\PaymentState\PendingState;
use OFFLINE\MicroCart\Models\Cart;
use OFFLINE\MicroCart\Models\PaymentLog;

/**
 * The PaymentResult contains the result of a payment attempt.
 */
class PaymentResult
{
    /**
     * If the payment was successful.
     * @var bool
     */
    public $successful = false;
    /**
     * If this payment needs a redirect.
     * @var bool
     */
    public $redirect = false;
    /**
     * Use this response as redirect.
     * @var \Illuminate\Http\RedirectResponse
     */
    public $redirectResponse;
    /**
     * Redirect the user to this URL.
     * @var string
     */
    public $redirectUrl = '';
    /**
     * The failed payment log.
     * @var PaymentLog
     */
    public $failedPayment;
    /**
     * The cart that is being processed.
     * @var Cart
     */
    public $cart;
    /**
     * Error message in case of a failure.
     * @var string
     */
    public $message;
    /**
     * The used PaymentProvider for this payment.
     * @var PaymentProvider
     */
    public $provider;

    /**
     * PaymentResult constructor.
     *
     * @param PaymentProvider $provider
     * @param Cart            $cart
     */
    public function __construct(PaymentProvider $provider, Cart $cart)
    {
        $this->provider   = $provider;
        $this->cart       = $cart;
        $this->successful = false;
    }

    /**
     * The payment was successful.
     *
     * The payment is logged, associated with the cart
     * and the cart is marked as paid.
     *
     * @param array $data
     * @param       $response
     *
     * @return PaymentResult
     */
    public function success(array $data, $response): self
    {
        $this->successful = true;

        try {
            $payment = $this->logSuccessfulPayment($data, $response);
        } catch (\Throwable $e) {
            // Even if the log failed we *have* to mark this cart as paid since the payment went already through.
            logger()->error(
                'OFFLINE.MicroCart: Could not log successful payment.',
                ['data' => $data, 'response' => $response, 'cart' => $this->cart, 'exception' => $e]
            );
        }

        try {
            $this->cart->payment_id    = $payment->id;
            $this->cart->payment_state = PaidState::class;
            $this->cart->save();
        } catch (\Throwable $e) {
            // If the cart could not be marked as paid the shop admin will have to do this manually.
            logger()->critical(
                'OFFLINE.MicroCart: Could not mark paid cart as paid.',
                ['data' => $data, 'response' => $response, 'cart' => $this->cart, 'exception' => $e]
            );
        }

        return $this;
    }

    /**
     * The payment is pending.
     *
     * No payment is logged. The cart's payment state
     * is marked as pending.
     *
     * @return PaymentResult
     */
    public function pending(): self
    {
        $this->successful = true;

        try {
            $this->cart->payment_state = PendingState::class;
            $this->cart->save();
        } catch (\Throwable $e) {
            // If the cart could not be marked as pending the shop admin will have to do this manually.
            logger()->critical(
                'OFFLINE.MicroCart: Could not mark pending cart as pending.',
                ['cart' => $this->cart, 'exception' => $e]
            );
        }

        return $this;
    }

    /**
     * The payment has failed.
     *
     * The failed payment is logged and the cart's
     * payment state is marked as failed.
     *
     * @param array $data
     * @param       $response
     *
     * @return PaymentResult
     */
    public function fail(array $data, $response): self
    {
        $this->successful = false;

        logger()->error(
            'OFFLINE.MicroCart: A payment failed.',
            ['data' => $data, 'response' => $response, 'cart' => $this->cart]
        );

        try {
            $this->failedPayment = $this->logFailedPayment($data, $response);
            $this->cart->payment_id = $this->failedPayment->id;
        } catch (\Throwable $e) {
            logger()->error(
                'OFFLINE.MicroCart: Could not log failed payment.',
                ['data' => $data, 'response' => $response, 'cart' => $this->cart, 'exception' => $e]
            );
        }

        return $this;
    }

    /**
     * The payment requires a redirect to an external URL.
     *
     * @param $url
     *
     * @return PaymentResult
     */
    public function redirect($url): self
    {
        $this->redirect    = true;
        $this->redirectUrl = $url;

        return $this;
    }

    /**
     * Create a PaymentLog entry for a failed payment.
     *
     * @param array $data
     * @param       $response
     *
     * @return PaymentLog
     */
    protected function logFailedPayment(array $data, $response): PaymentLog
    {
        return $this->logPayment(true, $data, $response);
    }

    /**
     * Create a PaymentLog entry for a successful payment.
     *
     * @param array $data
     * @param       $response
     *
     * @return PaymentLog
     */
    protected function logSuccessfulPayment(array $data, $response): PaymentLog
    {
        return $this->logPayment(false, $data, $response);
    }

    /**
     * Create a PaymentLog entry.
     *
     * @param bool  $failed
     * @param array $data
     * @param       $response
     *
     * @return PaymentLog
     */
    protected function logPayment(bool $failed, array $data, $response): PaymentLog
    {
        $log                   = new PaymentLog();
        $log->failed           = $failed;
        $log->data             = $data;
        $log->ip               = request()->ip();
        $log->session_id       = session()->get('cart_session_id');
        $log->payment_provider = $this->provider->identifier();
        $log->payment_method   = $this->cart->payment_method->name . ' (ID ' . $this->cart->payment_method->id .')';
        $log->cart_data        = $this->cart;
        $log->cart_id          = $this->cart->id;

        if ($response) {
            $log->message = method_exists($response, 'getMessage')
                ? $response->getMessage()
                : json_encode($response);

            $log->code = method_exists($response, 'getCode')
                ? $response->getCode()
                : null;
        }

        return tap($log)->save();
    }
}
