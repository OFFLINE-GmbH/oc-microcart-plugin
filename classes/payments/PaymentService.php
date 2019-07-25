<?php

namespace OFFLINE\MicroCart\Classes\Payments;

use MongoDB\Driver\Exception\LogicException;
use OFFLINE\MicroCart\Models\Cart;

/**
 * The PaymentService orchestrates the payment process.
 */
class PaymentService
{
    /**
     * The used PaymentGateway for this payment.
     * @var PaymentGateway
     */
    public $gateway;
    /**
     * The cart that is being paid.
     * @var Cart
     */
    public $cart;
    /**
     * Page filename of the checkout page.
     * @var string
     */
    public $pageFilename;
    /**
     * A PaymentRedirector instance.
     * @var PaymentRedirector
     */
    protected $redirector;

    /**
     * PaymentService constructor.
     *
     * @param PaymentGateway $gateway
     * @param Cart           $cart
     * @param string         $pageFilename
     *
     * @throws \Cms\Classes\CmsException
     */
    public function __construct(PaymentGateway $gateway, Cart $cart, string $pageFilename)
    {
        $this->gateway      = $gateway;
        $this->cart         = $cart;
        $this->pageFilename = $pageFilename;
        $this->redirector   = new PaymentRedirector($pageFilename);
    }

    /**
     * Processes the payment.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function process()
    {
        $lock = 'microCart.checkout.locked';
        if (session()->has($lock)) {
            throw new LogicException('OFFLINE.MicroCart: A checkout is already in progress!');
        }

        session()->put($lock);
        session()->put('microCart.processing_cart.id', $this->cart->id);

        try {
            $result = $this->gateway->process($this->cart);
        } catch (\Throwable $e) {
            $result = new PaymentResult($this->gateway->getActiveProvider(), $this->cart);
            $result->fail($this->cart->toArray(), $e);
        } finally {
            session()->forget($lock);
        }

        return $this->redirector->handlePaymentResult($result);
    }
}
