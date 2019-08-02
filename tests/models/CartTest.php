<?php namespace OFFLINE\MicroCart\Tests\Models;

use DB;
use OFFLINE\MicroCart\Models\Cart;
use OFFLINE\MicroCart\Models\CartItem;
use OFFLINE\MicroCart\Tests\PluginTestCase;

class CartTest extends PluginTestCase
{
    public function test_it_adds_products()
    {
        $cart = Cart::fromSession();

        $item           = new CartItem();
        $item->name     = 'Test';
        $item->quantity = 2;
        $item->price    = 20.00;

        $this->assertEquals(0, $cart->items->count());

        $cart->add($item);

        $this->assertEquals(1, $cart->items->count());
    }

    public function test_it_ensures_products()
    {
        $cart = Cart::fromSession();

        $item           = new CartItem();
        $item->name     = 'Test';
        $item->code     = 'shipping';
        $item->quantity = 2;
        $item->price    = 20.00;

        $this->assertEquals(0, $cart->items->count());

        $cart->ensure($item);

        $this->assertEquals(1, $cart->items->count());

        // "Ensuring" the product a second time should not add it again.
        $cart->ensure($item);

        $this->assertEquals(1, $cart->items->count());
    }

    public function test_it_ensures_product_quantities()
    {
        $cart = Cart::fromSession();

        $item           = new CartItem();
        $item->name     = 'Test';
        $item->code     = 'shipping';
        $item->quantity = 2;
        $item->price    = 20.00;

        $cart->add($item);

        $this->assertEquals(1, $cart->items->count());
        $this->assertEquals(2, $cart->items->first()->quantity);

        // "Ensuring" the product a second time should not add it again.
        $cart->ensure($item, 5);

        $cart->reloadRelations('items');

        $this->assertEquals(1, $cart->items->count());
        $this->assertEquals(5, $cart->items->first()->quantity);
    }

    public function test_it_removes_products()
    {
        $cart = Cart::fromSession();

        $itemA = new CartItem(['name' => 'A']);
        $itemB = new CartItem(['name' => 'B']);

        $cart->add($itemA);
        $cart->add($itemB);

        $this->assertEquals(2, $cart->items->count());

        $cart->remove($itemA);

        $this->assertEquals(1, $cart->items->count());

        $cart->remove($itemB->id);

        $this->assertEquals(0, $cart->items->count());
    }

    public function test_it_removes_products_by_code()
    {
        $cart = Cart::fromSession();

        $itemA = new CartItem(['name' => 'A', 'code' => 'removeme']);
        $itemB = new CartItem(['name' => 'B']);
        $itemC = new CartItem(['name' => 'C', 'code' => 'removeme']);

        $cart->add($itemA);
        $cart->add($itemB);
        $cart->add($itemC);

        $this->assertEquals(3, $cart->items->count());

        $cart->removeByCode('removeme');

        $this->assertEquals(1, $cart->items->count());
        $this->assertEquals($itemB->id, $cart->items->first()->id);
    }

    public function test_it_updates_quantities()
    {
        $cart = Cart::fromSession();
        $item = new CartItem(['name' => 'An item']);

        $cart->add($item);
        $this->assertEquals(1, $cart->items->first()->quantity);

        $cart->setQuantity($item->id, 5);
        $this->assertEquals(5, $cart->items->first()->quantity);

        $cart->setQuantity($item, 10);
        $this->assertEquals(10, $cart->items->first()->quantity);
    }
}
