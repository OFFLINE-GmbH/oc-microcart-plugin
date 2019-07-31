<?php

namespace OFFLINE\MicroCart\Classes\Payments;

use Cms\Classes\Controller;
use Illuminate\Support\Facades\Event;
use OFFLINE\MicroCart\Models\Cart;

/**
 * The PaymentRedirector handles all external and
 * internal redirects from or to different payment
 * services.
 */
class PaymentRedirector
{
    /**
     * @var Controller
     */
    protected $controller;
    /**
     * @var string
     */
    protected $page;

    /**
     * PaymentRedirector constructor.
     *
     * @param string $page
     *
     * @throws \Cms\Classes\CmsException
     */
    public function __construct(string $page)
    {
        $this->controller = new Controller();
        $this->page       = $page;
    }

    /**
     * Handle the final redirect after all payment processing is done.
     *
     * @param                    $state
     *
     * @param PaymentResult|null $result
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function finalRedirect($state, ?PaymentResult $result = null)
    {
        $states = [
            'failed'    => $this->getFailedUrl(),
            'cancelled' => $this->getCancelledUrl(),
            'succeeded' => $this->getSuccessfulUrl(),
        ];

        $url = $states[$state];

        // offline.microcart.checkout.failed
        // offline.microcart.checkout.cancelled
        // offline.microcart.checkout.succeeded
        Event::fire('offline.microcart.checkout.' . $state, [$result]);

        return redirect()->to($url);
    }

    /**
     * Handles a PaymentResult.
     *
     * @param PaymentResult $result
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handlePaymentResult(PaymentResult $result)
    {
        if ($result->redirect) {
            return $result->redirectUrl ? redirect()->to($result->redirectUrl) : $result->redirectResponse;
        }

        if ($result->successful) {
            Cart::regenerateSessionId();

            return $this->finalRedirect('succeeded', $result);
        }

        return $this->finalRedirect('failed', $result);
    }

    /**
     * Handles any off-site returns (PayPal, etc).
     *
     * @param $type
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleOffSiteReturn($type)
    {
        // Someone tampered with the url or the session has expired.
        $paymentId = session()->pull('microCart.payment.id');
        if ($paymentId !== request()->input('oc-microcart-payment-id')) {
            session()->forget('microCart.payment.callback');

            return $this->finalRedirect('failed');
        }

        // The user has cancelled the payment
        if ($type === 'cancel') {
            session()->forget('microCart.payment.callback');

            return $this->finalRedirect('cancelled');
        }

        // If a callback is set we need to do an additional step to
        // complete this payment.
        $callback = session()->pull('microCart.payment.callback');
        if ($callback) {
            /** @var PaymentProvider $paymentProvider */
            $paymentProvider = new $callback;
            if ( ! method_exists($paymentProvider, 'complete')) {
                throw new \LogicException('Payment providers that redirect off-site need to have a "complete" method!');
            }

            $result = new PaymentResult($paymentProvider, $paymentProvider->getCartFromSession());

            $paymentProvider->init();

            return $this->handlePaymentResult($paymentProvider->complete($result));
        }

        return $this->finalRedirect('succeeded');
    }

    /**
     * Returns the final result URL of the payment process.
     *
     * @param       $result
     * @param array $params
     *
     * @return string
     */
    public function resultUrl($result): string
    {
        return sprintf(
            '%s?%s',
            $this->controller->pageUrl($this->page),
            http_build_query([
                'result' => $result,
                'cart'   => session()->pull('microCart.processing_cart.id'),
            ])
        );
    }

    /**
     * The user is redirected to this URL if a payment failed.
     *
     * @return string
     */
    protected function getFailedUrl()
    {
        return $this->resultUrl(PaymentResult::RESULT_FAILED);
    }

    /**
     * The user is redirected to this URL if a payment was cancelled.
     *
     * @return string
     */
    protected function getCancelledUrl()
    {
        return $this->resultUrl(PaymentResult::RESULT_CANCELLED);
    }

    /**
     * The user is redirected to this URL if a payment was successful.
     *
     * @return string
     */
    protected function getSuccessfulUrl()
    {
        return $this->resultUrl(PaymentResult::RESULT_SUCCEEDED);
    }
}
