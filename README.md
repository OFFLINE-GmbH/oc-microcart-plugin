# oc-microcart-plugin

> The `OFFLINE.MicroCart` plugin aims to provide simple shopping cart and payment features.

This plugin is meant for projects where simple items are sold online (Tickets, Coupons, etc).
You will have to implement the "item part" (like a `Ticket` model) yourself. You can use
the plugin as cart and payment solution.

## Features

The `OFFLINE.MicroCart` plugin provides the following features:

* A `Cart` model with a nice API to add and remove `CartItems`
* A `Cart` component base, that is meant to be extended by you
* `Stripe` and `PayPal` payment integrations
* Support to add custom payment gateways
* Numerous events for you to hook into 

It **does not** provide any of these features:

* Product data management
* E-Mail notifications 
* Multi currency support
* Stock management
* Shipping rules
* ... and lots of other extended eCommerce features

If you are looking for a full featured eCommerce solution for October CMS
check out [OFFLINE.Mall](https://github.com/OFFLINE-GmbH/oc-mall-plugin).


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
properties that only contain the respective item types.

```php
$item     = new CartItem(['name' => 'A product']);
$shipping = new CartItem(['name' => 'Shipping fee', 'kind' => CartItem::KIND_SERVICE]);
$discount = new CartItem(['name' => 'Discount',  'kind' => CartItem::KIND_DISCOUNT]);

$cart->addMany($item, $shipping, $discount);

$cart->list_items->first()   === $item;     // true
$cart->service_fees->first() === $shipping; // true
$cart->discounts->first()    === $discount; // true
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
// $item->is_before_tax = true; // The specified price does not contain taxes. 
```

#### Access item information

```php
$item           = new CartItem(['name' => 'An item']);
$item->price    = 10.00;
$item->quantity = 2;
$item->tax_id   = 1; // 10% tax

$item->price;        // 10.00
$item->quantity;     // 2
$item->subtotal;     // 20.00 => price * quantity
$item->tax_amount;   // 2.00
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

* `$result`: a `PaymentResult` instance  
