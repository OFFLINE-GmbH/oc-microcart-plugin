<?php namespace OFFLINE\MicroCart\Components;

use Cms\Classes\ComponentBase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use October\Rain\Exception\ValidationException;
use OFFLINE\MicroCart\Classes\Payments\PaymentGateway;
use OFFLINE\MicroCart\Classes\Payments\PaymentRedirector;
use OFFLINE\MicroCart\Classes\Payments\PaymentResult;
use OFFLINE\MicroCart\Classes\Payments\PaymentService;
use OFFLINE\MicroCart\Models\Cart as CartModel;
use OFFLINE\MicroCart\Models\CartItem;
use OFFLINE\MicroCart\Models\PaymentMethod;
use Validator;

abstract class Cart extends ComponentBase
{
    /**
     * @var CartModel
     */
    public $cart;
    /**
     * @var Collection
     */
    public $paymentMethods;
    /**
     * @var string
     */
    public $result;

    public function componentDetails()
    {
        return [
            'name'        => 'offline.microcart::lang.components.cart.name',
            'description' => 'offline.microcart::lang.components.cart.description',
        ];
    }

    public function defineProperties()
    {
        return [];
    }

    public function init()
    {
        $this->cart           = CartModel::fromSession();
        $this->paymentMethods = PaymentMethod::orderBy('sort_order')->get();
        $this->result         = input('result', PaymentResult::RESULT_PENDING);
    }

    /**
     * This method handles returning off-site payment flows (like PayPal). Make sure this
     * functionality remains intact when you extend this method.
     */
    public function onRun()
    {
        // An off-site payment has been completed
        if ($type = request()->input('return')) {
            return (new PaymentRedirector($this->page->page->fileName))->handleOffSiteReturn($type);
        }
    }

    /**
     * Checkout is initialized.
     */
    public function onCheckout()
    {
        $rules    = $this->getValidationRules();
        $messages = $this->getValidationMessages();
        $fields   = $this->getFieldNames();

        $data = post('checkout', []);

        $v = Validator::make($data, $rules, $messages, $fields);
        if ( ! $v->passes()) {
            throw new ValidationException($v);
        }

        $this->cart->fill(
            array_only($data, array_keys($this->getValidationRules()))
        );

        // TODO: Lösung finden, wie dies schön gemacht werden kann sodass die payment_method_id vom Integrator gesetzt werden kann.
        $this->cart->payment_method_id = array_get($data, 'payment_method_id', $this->cart->payment_method_id);
        $this->cart->save();

        $paymentMethod = PaymentMethod::findOrFail($this->cart->payment_method_id);

        // Grab the PaymentGateway from the Service Container.
        $gateway = app(PaymentGateway::class);
        $gateway->init($paymentMethod, $data);

        // Process the payment
        $paymentService = new PaymentService(
            $gateway,
            $this->cart,
            $this->page->page->fileName
        );

        return $paymentService->process();
    }

    /**
     * An item is added to the cart.
     *
     * @return array
     * @throws \Exception
     */
    public function onAdd()
    {
        $item           = new CartItem();
        $item->name     = 'Your product ' . random_int(10000, 99999);
        $item->quantity = random_int(1, 4);
        $item->price    = random_int(10000, 99999) / 100;

        $this->cart->add($item);

        return $this->refreshCart();
    }

    /**
     * An item is removed from the cart.
     *
     * @return array
     * @throws \Exception
     */
    public function onRemove()
    {
        try {
            $this->cart->remove(post('id'));
        } catch (ModelNotFoundException $e) {
            throw new ValidationException(['error' => 'The specified product is not in your cart.']);
        }

        return $this->refreshCart();
    }

    /**
     * The quantity of an item is changed.
     *
     * @return array
     * @throws \Exception
     */
    public function onChangeQuantity()
    {
        $quantity = post('quantity');

        if ($quantity > 10) {
            $quantity = 10;
        }
        if ($quantity < 1) {
            $quantity = 1;
        }

        try {
            $this->cart->setQuantity(post('id'), $quantity);
        } catch (ModelNotFoundException $e) {
            throw new ValidationException(['error' => 'The specified product is not in your cart.']);
        }

        return $this->refreshCart();
    }

    /**
     * The payment method has been changed.
     *
     * @return array
     * @throws \Exception
     */
    public function onChangePaymentMethod()
    {
        $method = post('checkout[payment_method_id]');

        if ( ! $method = PaymentMethod::find($method)) {
            throw new ValidationException(['payment_method_id' => 'Invalid payment method']);
        }

        $this->cart->setPaymentMethod($method);

        return array_merge(
            $this->refreshCart(),
            ['#payment-method-data' => $this->renderPartial($this->alias . '::payment_data', ['method' => $method])]
        );
    }

    /**
     * Refresh the cart partial.
     *
     * @return array
     */
    protected function refreshCart(): array
    {
        return [
            '#cart' => $this->renderPartial($this->alias . '::table', ['cart' => $this->cart]),
        ];
    }

    /**
     * Checkout validation rules.
     * @return array
     */
    protected function getValidationRules(): array
    {
        return [
            'email' => 'required|email',

            'shipping_firstname' => 'required',
            'shipping_lastname'  => 'required',
            'shipping_lines'     => 'required',
            'shipping_zip'       => 'required',
            'shipping_city'      => 'required',
            'shipping_country'   => 'required',

            'billing_differs'   => 'boolean',
            'billing_firstname' => 'required_if:billing_differs,1',
            'billing_lastname'  => 'required_if:billing_differs,1',
            'billing_lines'     => 'required_if:billing_differs,1',
            'billing_zip'       => 'required_if:billing_differs,1',
            'billing_city'      => 'required_if:billing_differs,1',
            'billing_country'   => 'required_if:billing_differs,1',

            'payment_method_id' => 'required|exists:offline_microcart_payment_methods,id',
        ];
    }

    /**
     * Checkout validation field names.
     * @return array
     */
    protected function getFieldNames(): array
    {
        return [
            'email' => 'E-Mail',

            'shipping_company'   => 'Company',
            'shipping_firstname' => 'Firstname',
            'shipping_lastname'  => 'Lastname',
            'shipping_lines'     => 'Address',
            'shipping_zip'       => 'ZIP',
            'shipping_city'      => 'City',
            'shipping_country'   => 'Country',

            'billing_differs'   => 'Different billing address',
            'billing_company'   => 'Company',
            'billing_firstname' => 'Firstname',
            'billing_lastname'  => 'Lastname',
            'billing_lines'     => 'Address',
            'billing_zip'       => 'ZIP',
            'billing_city'      => 'City',
            'billing_country'   => 'Country',
        ];
    }

    /**
     * Checkout validation messages.
     * @return array
     */
    protected function getValidationMessages(): array
    {
        return [
            // 'shipping_company.required' => 'Specify a shipping company',
        ];
    }
}
