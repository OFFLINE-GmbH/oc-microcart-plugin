<?php namespace OFFLINE\MicroCart\Tests\Classes;

use OFFLINE\MicroCart\Models\Cart;
use OFFLINE\MicroCart\Models\CartItem;
use OFFLINE\MicroCart\Models\PaymentMethod;
use OFFLINE\MicroCart\Models\Tax;
use OFFLINE\MicroCart\Tests\PluginTestCase;

class CartTest extends PluginTestCase
{
    public function test_it_calculates_totals_correctly_with_tax_excluded()
    {
        $lowTax  = Tax::create(['name' => '10%', 'percentage' => 10, 'is_default' => 1]);
        $highTax = Tax::create(['name' => '100%', 'percentage' => 100]);

        $payment = PaymentMethod::create([
            'price'            => 0.30,
            'payment_provider' => 'stripe',
            'percentage'       => 2.9,
            'name'             => 'Test method',
        ]);
        $payment->taxes()->attach($lowTax);

        $cart = Cart::fromSession();

        // -------------------------------------------------------------------
        // Item       Quantity         Price     Subtotal       Tax      Total
        // -------------------------------------------------------------------
        // A                 1         10.00        10.00      1.00      11.00
        // B                 5         10.00        50.00      5.00      55.00
        // C                 2        100.00       200.00    200.00     400.00
        // Discount          1       -100.00      -100.00      0.00    -100.00
        // -------------------------------------------------------------------
        // Subtotal                                160.00    206.00     366.00
        // -------------------------------------------------------------------
        // Shipping          1         50.00        50.00      5.00      55.00
        // Handling          1         10.00        10.00      1.00      11.00
        // -------------------------------------------------------------------
        // Service Total                            60.00      6.00      66.00
        // -------------------------------------------------------------------
        // Cart Total                              220.00    212.00     432.00
        // ===================================================================
        // Payment fee           2.9% + 0.30         6.88      0.69       7.57
        // -------------------------------------------------------------------
        // Payment Total                             6.88      0.69       7.57
        // -------------------------------------------------------------------
        // Grand Total                             226.88    212.69     439.57
        // ===================================================================

        $cart->addMany(
            new CartItem(['name' => 'A', 'price' => 10, 'is_before_tax' => true]),
            new CartItem(['name' => 'B', 'price' => 10, 'quantity' => 5, 'is_before_tax' => true]),
            new CartItem(['name' => 'C', 'price' => 100, 'quantity' => 2, 'tax_id' => $highTax->id, 'is_before_tax' => true]),
            new CartItem(['name' => 'Discount', 'price' => -100, 'is_tax_free' => true, 'is_before_tax' => true, 'kind' => CartItem::KIND_DISCOUNT]),
            new CartItem(['name' => 'Shipping', 'price' => 50, 'kind' => CartItem::KIND_SERVICE, 'is_before_tax' => true]),
            new CartItem(['name' => 'Handling', 'price' => 10, 'kind' => CartItem::KIND_SERVICE, 'is_before_tax' => true,])
        );

        $cart->setPaymentMethod($payment);

        $this->assertEquals(16000, $cart->totals->subPreTaxes, 'subPreTaxes is wrong');
        $this->assertEquals(20600, $cart->totals->subTaxes, 'subTaxes is wrong');
        $this->assertEquals(36600, $cart->totals->subPostTaxes, 'subPostTaxes is wrong');

        $this->assertEquals(6000, $cart->totals->servicePreTaxes, 'servicePreTaxes is wrong');
        $this->assertEquals(600, $cart->totals->serviceTaxes, 'serviceTaxes is wrong');
        $this->assertEquals(6600, $cart->totals->servicePostTaxes, 'servicePostTaxes is wrong');

        $this->assertEquals(22000, $cart->totals->cartPreTaxes, 'cartPreTaxes is wrong');
        $this->assertEquals(21200, $cart->totals->cartTaxes, 'cartTaxes is wrong');
        $this->assertEquals(43200, $cart->totals->cartPostTaxes, 'cartPostTaxes is wrong');

        $this->assertEquals(688, $cart->totals->paymentPreTaxes, 'paymentPreTaxes is wrong');
        $this->assertEquals(69, $cart->totals->paymentTaxes, 'paymentTaxes is wrong');
        $this->assertEquals(757, $cart->totals->paymentPostTaxes, 'paymentPostTaxes is wrong');

        $this->assertEquals(22688, $cart->totals->grandPreTaxes, 'grandPreTaxes is wrong');
        $this->assertEquals(21269, $cart->totals->grandTaxes, 'grandTaxes is wrong');
        $this->assertEquals(43957, $cart->totals->grandPostTaxes, 'grandPostTaxes is wrong');
    }

    public function test_it_calculates_totals_correctly_with_tax_included()
    {
        $lowTax  = Tax::create(['name' => '10%', 'percentage' => 10, 'is_default' => 1]);
        $highTax = Tax::create(['name' => '100%', 'percentage' => 100]);

        $cart = Cart::fromSession();

        $payment = PaymentMethod::create([
            'price'            => 0.30,
            'payment_provider' => 'stripe',
            'percentage'       => 2.9,
            'name'             => 'Test method',
        ]);
        $payment->taxes()->attach($lowTax);

        // -------------------------------------------------------------------
        // Item       Quantity         Price     Subtotal       Tax      Total
        // -------------------------------------------------------------------
        // A                 1         10.00        10.00      0.91      10.00
        // B                 5         10.00        50.00      4.55      50.00
        // C                 2        100.00       200.00    100.00     200.00
        // Discount          1       -100.00      -100.00      0.00    -100.00
        // -------------------------------------------------------------------
        // Subtotal                                160.00    105.46     160.00
        // -------------------------------------------------------------------
        // Shipping          1         50.00        50.00      4.55      50.00
        // Handling          1         10.00        10.00      0.91      10.00
        // -------------------------------------------------------------------
        // Service Total                            60.00      5.46      60.00
        // -------------------------------------------------------------------
        // Cart Total                              220.00    110.92     220.00
        // ===================================================================
        // Payment fee           2.9% + 0.30         6.88      0.69       7.57
        // -------------------------------------------------------------------
        // Payment Total                             6.88      0.69       7.57
        // -------------------------------------------------------------------
        // Grand Total                180.00       226.88    111.61     227.57
        // ===================================================================

        $cart->addMany(
            new CartItem(['name' => 'A', 'price' => 10]),
            new CartItem(['name' => 'B', 'price' => 10, 'quantity' => 5]),
            new CartItem(['name' => 'C', 'price' => 100, 'quantity' => 2, 'tax_id' => $highTax->id]),

            new CartItem(['name' => 'Discount', 'price' => -100, 'is_tax_free' => true, 'kind' => CartItem::KIND_DISCOUNT]),

            new CartItem(['name' => 'Shipping', 'price' => 50, 'kind' => CartItem::KIND_SERVICE]),
            new CartItem(['name' => 'Handling', 'price' => 10, 'kind' => CartItem::KIND_SERVICE])
        );

        $cart->setPaymentMethod($payment);

        $this->assertEquals(16000, $cart->totals->subPreTaxes, 'subPreTaxes is wrong');
        $this->assertEquals(10546, $cart->totals->subTaxes, 'subTaxes is wrong');
        $this->assertEquals(16000, $cart->totals->subPostTaxes, 'subPostTaxes is wrong');

        $this->assertEquals(6000, $cart->totals->servicePreTaxes, 'servicePreTaxes is wrong');
        $this->assertEquals(546, $cart->totals->serviceTaxes, 'serviceTaxes is wrong');
        $this->assertEquals(6000, $cart->totals->servicePostTaxes, 'servicePostTaxes is wrong');

        $this->assertEquals(22000, $cart->totals->cartPreTaxes, 'cartPreTaxes is wrong');
        $this->assertEquals(11092, $cart->totals->cartTaxes, 'cartTaxes is wrong');
        $this->assertEquals(22000, $cart->totals->cartPostTaxes, 'cartPostTaxes is wrong');

        $this->assertEquals(688, $cart->totals->paymentPreTaxes, 'paymentPreTaxes is wrong');
        $this->assertEquals(69, $cart->totals->paymentTaxes, 'paymentTaxes is wrong');
        $this->assertEquals(757, $cart->totals->paymentPostTaxes, 'paymentPostTaxes is wrong');

        $this->assertEquals(22688, $cart->totals->grandPreTaxes, 'grandPreTaxes is wrong');
        $this->assertEquals(11161, $cart->totals->grandTaxes, 'grandTaxes is wrong');
        $this->assertEquals(22757, $cart->totals->grandPostTaxes, 'grandPostTaxes is wrong');
    }
}