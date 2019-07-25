<?php

namespace OFFLINE\MicroCart\Classes;


use OFFLINE\MicroCart\Models\Cart;
use OFFLINE\MicroCart\Models\CartItem;

class Totals
{
    /**
     * @var Cart
     */
    public $cart;

    /**
     * Totals of all generic items.
     */
    public $subPreTaxes = 0;
    public $subTaxes = 0;
    public $subPostTaxes = 0;

    /**
     * Totals of all `KIND_SERVICE` marked items.
     */
    public $servicePreTaxes = 0;
    public $serviceTaxes = 0;
    public $servicePostTaxes = 0;

    /**
     * Cart totals (items + service).
     */
    public $cartPreTaxes = 0;
    public $cartTaxes = 0;
    public $cartPostTaxes = 0;


    /**
     * Payment totals.
     */
    public $paymentPreTaxes = 0;
    public $paymentTaxes = 0;
    public $paymentPostTaxes = 0;

    /**
     * Grand totals.
     */
    public $grandPreTaxes = 0;
    public $grandTaxes = 0;
    public $grandPostTaxes = 0;

    public function __construct(Cart $cart)
    {
        $this->cart = $cart;

        $grouped = $this->cart->items->groupBy('kind');

        $items     = $grouped->get(CartItem::KIND_ITEM);
        $discounts = $grouped->get(CartItem::KIND_DISCOUNT);
        $services  = $grouped->get(CartItem::KIND_SERVICE);

        if ($items) {
            $items->each(function (CartItem $item) {
                $this->subPreTaxes  += $item->subtotal;
                $this->subTaxes     += $item->tax_amount;
                $this->subPostTaxes += $item->total;
            });
        }

        if ($discounts) {
            $discounts->each(function (CartItem $item) {
                $this->subPreTaxes  += $item->subtotal;
                $this->subTaxes     += $item->tax_amount;
                $this->subPostTaxes += $item->total;
            });
        }

        if ($services) {
            $services->each(function (CartItem $item) {
                $this->servicePreTaxes  += $item->subtotal;
                $this->serviceTaxes     += $item->tax_amount;
                $this->servicePostTaxes += $item->total;
            });
        }

        $this->cartPreTaxes  = $this->servicePreTaxes + $this->subPreTaxes;
        $this->cartTaxes     = $this->serviceTaxes + $this->subTaxes;
        $this->cartPostTaxes = $this->servicePostTaxes + $this->subPostTaxes;

        if ($method = $this->cart->payment_method) {

            $base = $this->cartPreTaxes;

            $percentage = ($method->percentage ?? 0) / 100;
            $price      = $method->price * 100;

            $charge = ($base + $price) / (1 - $percentage);

            $taxPercentage = $method->taxes->sum('percentage');

            $this->paymentPreTaxes  = round($charge - $base);
            $this->paymentTaxes     = round($this->paymentPreTaxes * ($taxPercentage / 100));
            $this->paymentPostTaxes = $this->paymentPreTaxes + $this->paymentTaxes;
        }

        $this->grandPreTaxes  = $this->paymentPreTaxes + $this->cartPreTaxes;
        $this->grandTaxes     = $this->paymentTaxes + $this->cartTaxes;
        $this->grandPostTaxes = $this->paymentPostTaxes + $this->cartPostTaxes;
    }
}