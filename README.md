# oc-microcart-plugin

> The `OFFLINE.MicroCart` plugin aims to provide simple shopping cart and payment features.

This plugin is meant for projects where simple items are sold online (Tickets, Coupons, etc).
You will have to implement the "item part" (like a `Ticket` model) yourself. You can use
the plugin as cart and payment solution.

## Features

The `OFFLINE.MicroCart` plugin provides the following features:

* A `Cart` model with a nice API to add and remove `CartItems`
* A `Cart` component base, that is meant to be extended by you
* `Stripe`, `PayPal` and `SIX SaferPay` payment integrations
* Support to add custom payment gateways
* Numerous events for you to hook into 

It **does not** provide any of these features:

* Product data management
* E-Mail notifications 
* Multi currency support
* Stock management
* Shipping rules
* ... and lots of other extended eCommerce features

If you are looking for a fully featured eCommerce solution for October CMS
check out [OFFLINE.Mall](https://github.com/OFFLINE-GmbH/oc-mall-plugin).

## Getting up and running

### Create your own plugin that extends MicroCart

The MicroCart plugin is meant to be extended by your own plugins.
Refer to [the official October CMS docs](https://octobercms.com/docs/plugin/extending)
for a list of all extension possibilities.

```bash
php artisan create:plugin YourVendor.PluginName
```

### Backend menu

The plugin does by default not register any backend menu items. You can use the
following snippet in your own `Plugin.php` to use the default orders overview.

```php
    public function registerNavigation()
    {
        return [
            'main-menu-item' => [
                'label'        => 'Your Plugin', // Your label
                'url'          => \Backend::url('offline/microcart/carts'),
                'iconSvg'      => 'plugins/offline/microcart/assets/icon.svg',
            ],
        ];
    }
``` 

### Cart component

The plugin does not register any components but it provides you with a `Cart` base 
component that you can extend.

Simply register your own component and build from there.

```bash
php artisan create:component YourVendor.PluginName Cart
```

Register your component in your `Plugin.php`.

```php
    public function registerComponents()
    {
        return [
            \YourVendor\YourPlugin\Components\Cart::class => 'cart',
        ];
    }
```

Make sure the component extends MicroCart's base component.

```php
<?php namespace YourVendor\YourPlugin\Components;

use OFFLINE\MicroCart\Models\CartItem;
use OFFLINE\MicroCart\Classes\Payments\PaymentRedirector;

class Cart extends \OFFLINE\MicroCart\Components\Cart
{        
    public function onRun()
    {
        // An off-site payment has been completed. Important, this code
        // needs to be present if you are using PayPal or Six. 
        if ($type = request()->input('return')) {
            return (new PaymentRedirector($this->page->page->fileName))->handleOffSiteReturn($type);
        }

        // Do something.
    }

    public function onAdd()
    {
        $item           = new CartItem();
        $item->name     = 'Your product';
        $item->quantity = random_int(1, 4);
        $item->price    = 100.00;

        $this->cart->add($item);

        return $this->refreshCart();
    }
}
```

To modify validation rules and messages take a look at the `getValidationRules`, `getFieldNames`
and `getValidationMessages` methods on the base `Cart` class.

Copy [the default partials](./components/cart) from MicroCart's base component to your own component.
Modify them as needed.

```bash
cp -rv plugins/offline/microcart/components/cart/* plugins/yourvendor/yourplugin/components/cart/
```

#### Updating the cart partials

You can `return $this->refreshCart();` from your Cart component's methods to refresh the cart display. 

### Using payment providers

There are three payment providers supported out-of-the-box:

* PayPal (`composer require omnipay/stripe`)
* Stripe (`composer require omnipay/paypal`)
* Six SaferPay (`composer require ticketpark/saferpay-json-api`)

Run the composer commands beside each provider you plan to use. You can configure the
providers via October's backend settings.

### Custom payment providers

To register a custom `PaymentProvider` create a class that extends MicroCart's PaymentProvider class. [
Check out the existing providers](./classes/payments) to get some inspiration on how to create your own.

```php
<?php

namespace YourVendor\YourPlugin\Classes;

use OFFLINE\MicroCart\Classes\Payments\PaymentProvider;

class YourCustomProvider extends PaymentProvider
{
    // Implement all abstract methods.
}
```

In your `Plugin.php` register this custom provider by returning them from a `registerPaymentProviders` method.

```php
use OFFLINE\MicroCart\Classes\Payments\PaymentGateway;

class Plugin extends PluginBase
{
    public function registerPaymentProviders()
    {
        return [
            new YourCustomProvider(),
        ];
    }
}
```  

### Link your model to cart items

The easiest way to link a model to a `CartItem` is to add a simple `belongsTo` relationship.

```php
// Voucher is the thing we are selling (the CartItem)
class Voucher extends Model
{
    public $belongsTo = [
        'cartitem' => CartItem::class
    ];
}
``` 

## API

### Cart

#### Get the user's cart

```php
// This cart will be unique to the current user.
$cart = Cart::fromSession();
```

#### Add an item to the cart

```php
$item           = new CartItem();
$item->name     = 'An item'; // The only required field
$item->quantity = 2;
$item->price    = 20.00;

$cart->add($item);
```

#### Ensure an item is in the cart

```php
$item = new CartItem(['name' => 'Shipping fee', 'kind' => CartItem::KIND_SERVICE]);

// A code property is required! A product with the specified
// code is ensured to be present in the Cart.
$item->code = 'shipping'; 

// ensure the item is in the cart. If it's not, it will be added.
$cart->ensure($item);
// A second call will not add the item again.
$cart->ensure($item);
// You can force a new quantity by passing a second parameter.
$cart->ensure($item, 4);
```


#### Remove an item from the cart

```php
$item = new CartItem(['name' => 'An item']);

// You can remove an item by passing in a CartItem object or an id.
$cart->remove($item);
$cart->remove($item->id);
```

#### Remove all items with a given code from the cart

```php
$item = new CartItem(['name' => 'Shipping fee', 'code' => 'shipping', 'kind' => CartItem::KIND_SERVICE]);

// Removes all items with a given code (reverse of the `ensure` method).
$cart->removeByCode('shipping');
```


#### Update an item's quantity

```php
$item = new CartItem(['name' => 'An item']);

// You can set the quantity by passing in a CartItem object or an id.
$cart->setQuantity($item, 4);
$cart->setQuantity($item->id, 4);
```

#### Service fees and discounts

Set the `kind` attribute to either `CartItem::KIND_SERVICE` or `CartItem::KIND_DISCOUNT`
if the item is a service fee (Shipping, Handling) or a discount. 
Use the `ensure` method to make sure it's only added once to the Cart.


```php
$item = new CartItem(['name' => 'Shipping fee', 'kind' => CartItem::KIND_SERVICE, 'price' => 10]);

// The code is required to use the `ensure` method.
$item->code = 'shipping'; 

$cart->ensure($item);


$item = new CartItem(['name' => 'Discount', 'kind' => CartItem::KIND_DISCOUNT, 'price' => -100]);
$item->code = 'discount'; 

$cart->ensure($item);
```

#### Access cart contents

You can access all cart items using the `$cart->items` relation.

You also have access to filtered `list_items`, `service_fees` and `discounts` 
properties that only contain the given item types.

```php
$item     = new CartItem(['name' => 'A product']);
$shipping = new CartItem(['name' => 'Shipping fee', 'kind' => CartItem::KIND_SERVICE]);
$discount = new CartItem(['name' => 'Discount',     'kind' => CartItem::KIND_DISCOUNT]);

$cart->addMany($item, $shipping, $discount);

$cart->list_items->first()   === $item;     // true
$cart->service_fees->first() === $shipping; // true
$cart->discounts->first()    === $discount; // true
```  

#### Print the customer's address

After a `Cart` checkout has been successful, you can use the following helpers
to get the customer's address as an array or an escaped HTML string.

```php
$cart->getShippingAddressArray();
// ['Company', 'Firstname Lastname', ...];

$cart->getBillingAddressHtml();
// Company<br />
// Firstname Lastname<br />
// Street 123<br />
// ZIP City

$cart->getBillingAddressHtmlZipReversed();
// Company<br />
// Firstname Lastname<br />
// Street 123<br />
// City ZIP
```

### CartItem

#### Create an item

```php
// Short format
$item = new CartItem(['name' => 'An item']);

// Or long format
$item = new CartItem();
$item->name = 'An item';
$item->description = 'The description to this item';
$item->price = 20.00; // Includes tax by default.
$item->quantity = 10;
$item->meta = [
    'any' => 'additional',
    'data' => true,
];
// $item->tax_id = 2;           // If not specified the default tax will be used. 
// $item->tax_free = true;      // Don't add taxes to this item, not even the default tax. 
// $item->is_before_tax = true; // The specified price does not include taxes. 
```

#### Access item information

```php
// Create an example item
$item           = new CartItem(['name' => 'An item']);
$item->price    = 10.00;
$item->quantity = 2;
$item->tax_id   = 1; // 10% tax

// Access the item's information
$item->price;        // 10.00
$item->quantity;     // 2
$item->subtotal;     // 20.00 => price * quantity
$item->tax_amount;   //  2.00 => 10 % on price 
$item->total;        // 22.00 => (price * quantity) + tax_amount
```


### Money

There is a `Money` singleton class available to format cents as a string.
A `microcart_money` Twig helper is registered as well.

```php
Money::instance()->format(12000); // 120.00 USD

// or in Twig
{{ 120000 | microcart_money }}   // 120.00 USD
```

#### Change the default money formatter

You can register your own formatter function by adding the following code
to your Plugin's `register` method.

```php
    public function register()
    {
        \Event::listen('offline.microcart.moneyformatter', function () {
            return function ($cents): string {
                return 'Your custom implementation to format: ' . $cents;
            };
        });
    }
```

## Events

### Cart

#### `offline.microcart.cart.beforeAdd`

Fired before an item is added to the Cart. It receives the following arguments:

* `$cart`: the `Cart` of the current user 
* `$item`: the `CartItem` being added 

#### `offline.microcart.cart.afterAdd`

Fired after an item has been added to the Cart. It receives the following arguments:

* `$cart`: the `Cart` of the current user 
* `$item`: the `CartItem` added 

#### `offline.microcart.cart.beforeRemove`

Fired before an item is removed from the Cart. It receives the following arguments:

* `$cart`: the `Cart` of the current user 
* `$item`: the `CartItem` being removed 

#### `offline.microcart.cart.afterRemove`

Fired after an item has been removed from the Cart. It receives the following arguments:

* `$cart`: the `Cart` of the current user 
* `$item`: the `CartItem` removed 

#### `offline.microcart.cart.quantityChanged`

Fired after the quantity of a cart item has changed.

* `$cart`: the `Cart` of the current user 
* `$item`: the `CartItem` that was updated

### Checkout

#### `offline.microcart.checkout.succeeded`

Fired after a checkout was successful.

* `$result`: a `PaymentResult` instance

#### `offline.microcart.checkout.failed`

Fired after a checkout has failed.

* `$result`: a `?PaymentResult` instance (nullable)  

#### `offline.microcart.checkout.cancelled`

Fired after a checkout has been cancelled.
