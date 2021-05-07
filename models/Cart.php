<?php namespace OFFLINE\MicroCart\Models;

use Cookie;
use Event;
use Illuminate\Support\Collection;
use Model;
use OFFLINE\MicroCart\Classes\Totals;
use Session;

/**
 * Class Cart
 * @package OFFLINE\MicroCart\Models
 *
 * @property Totals $totals
 */
class Cart extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'offline_microcart_carts';
    public $with = ['items'];
    public $rules = []; // handled in Cart component, extend as you need.
    public $guarded = [];
    public $hasMany = [
        'items'        => [CartItem::class, 'order' => ['kind', 'sort_order']],
        'payment_logs' => [PaymentLog::class, 'order' => ['id desc']],
    ];
    public $belongsTo = [
        'payment_method' => PaymentMethod::class,
    ];

    /**
     * Cached totals of this cart.
     *
     * @var Totals
     */
    protected $cachedTotals;
    /**
     * Cached list items of this cart.
     *
     * @var Collection
     */
    protected $cachedListItems;
    /**
     * Cached service fees of this cart.
     *
     * @var Collection
     */
    protected $cachedServiceFees;
    /**
     * Cached payment fees of this cart.
     *
     * @var Collection
     */
    protected $cachedDiscounts;

    public function beforeSave()
    {
        if ( ! $this->currency) {
            $this->currency = GeneralSettings::get('default_currency');
            if ( ! $this->currency) {
                throw new \LogicException(
                    'OFFLINE.MicroCart: You need to set a default currency in the backend settings before using this plugin.'
                );
            }
        }

        if ( ! $this->payment_method_id) {
            if ( ! $defaultMethod = PaymentMethod::getDefault()) {
                throw new \LogicException(
                    'OFFLINE.MicroCart: You need to create a payment method in the backend settings before using this plugin.'
                );
            }
            $this->payment_method_id = $defaultMethod->id;
        }
    }

    /**
     * Create a cart for an unregistered user. The cart id
     * is stored to the session and to a cookie. When the user
     * visits the website again we will try to fetch the id of an old
     * cart from the session or from the cookie.
     *
     * @return self
     */
    public static function fromSession(): self
    {
        return self::orderBy('created_at', 'DESC')
                   ->whereNull('payment_state')
                   ->firstOrCreate(['session_id' => self::getSessionId()]);
    }

    public function scopeCompletedOrders($q)
    {
        $q->whereNotNull('payment_state');
    }

    /**
     * Return and store a session id.
     *
     * @return string
     */
    private static function getSessionId(): string
    {
        if (app()->runningUnitTests()) {
            return 'testing';
        }

        $sessionId = Cookie::get('cart_session_id') ?? str_random(100);
        Cookie::queue('cart_session_id', $sessionId, 9e6);

        return $sessionId;
    }

    /**
     * Regenerate a new session id.
     *
     * @return string
     */
    public static function regenerateSessionId(): string
    {
        $sessionId = str_random(100);
        Cookie::queue('cart_session_id', $sessionId, 9e6);

        return $sessionId;
    }

    /**
     * Add a CartItem to the Cart.
     *
     * @param CartItem $item
     * @param int      $quantity
     */
    public function add(CartItem $item, int $quantity = 1)
    {
        if ( ! $item->exists) {
            $item->save();
        }

        if ($item->quantity === null) {
            $item->quantity = $quantity;
        }

        if ($item->is_tax_free !== true && optional($item->taxes)->count() === 0) {
            $defaultTax = Tax::getDefault();
            if ($defaultTax) {
                $item->taxes()->sync($defaultTax->id);
            }
        }

        Event::fire('offline.microcart.cart.beforeAdd', [$this, $item]);

        $this->items()->add($item);
        $this->reloadRelations('items');
        $item->reloadRelations('cart');

        $this->flushCache();

        Event::fire('offline.microcart.cart.afterAdd', [$this, $item]);
        
        return true;
    }

    /**
     * Add multiple CartItems at once.
     *
     * @param CartItem[] $items
     */
    public function addMany(CartItem ...$items)
    {
        foreach ($items as $item) {
            $this->add($item);
        }
    }

    /**
     * Ensure a CartItem is in the cart. If it is already present, it
     * will not be added again. The item will be searched by its
     * unique code property.
     *
     * @param CartItem $item
     * @param int      $quantity
     */
    public function ensure(CartItem $item, ?int $quantity = null)
    {
        if ($item->code === null) {
            throw new \InvalidArgumentException('Only CartItems with a "code" property can be ensured');
        }

        /** @var CartItem $existing */
        if ($existing = $this->items->where('code', $item->code)->first()) {

            $existing->fill(array_except($item->toArray(), 'quantity', 'price'));
            $existing->price = $item->price / 100;

            // If a $quantity was specified, enforce it for this item. This is done separately
            // to trigger the respective events.
            if ($quantity !== null) {
                $this->setQuantity($existing, $quantity);
            }

            $existing->save();

            $this->reloadRelations('items');
            $existing->reloadRelations('cart');

            return;
        }

        // If the item is not in the cart, add it.
        return $this->add($item, $quantity ?? 1);
    }

    /**
     * Remove an item from the cart.
     *
     * @param CartItem|integer $item
     *
     * @throws \Exception
     */
    public function remove($item)
    {
        $item = $this->resolveItem($item);

        Event::fire('offline.microcart.cart.beforeRemove', [$this, $item]);

        $item->delete();

        $this->reloadRelations('items');
        $this->flushCache();

        Event::fire('offline.microcart.cart.afterRemove', [$this, $item]);
    }

    /**
     * Remove all items with a given code.
     *
     * @param string $code
     *
     * @throws \Exception
     */
    public function removeByCode(string $code)
    {
        $cart = static::fromSession();
        $item = $this->items->filter(function (CartItem $item) use ($cart, $code) {
            return $item->code === $code && (int)$item->cart_id === (int)$cart->id;
        });
        $this->removeMany(...$item);
    }

    /**
     * Remove multiple CartItems at once.
     *
     * @param CartItem[] $items
     *
     * @throws \Exception
     */
    public function removeMany(CartItem ...$items)
    {
        foreach ($items as $item) {
            $this->remove($item);
        }
    }

    /**
     * Update the quantity of a cart item.
     *
     * @param CartItem|integer $item
     *
     * @param int              $quantity
     */
    public function setQuantity($item, int $quantity)
    {
        $item = $this->resolveItem($item);

        Event::fire('offline.microcart.cart.beforeQuantityChange', [$this, $item]);

        $item->quantity = $quantity;
        $item->save();

        $this->reloadRelations('items');
        $this->flushCache();

        Event::fire('offline.microcart.cart.afterQuantityChange', [$this, $item]);
    }

    /**
     * Make a proper CartItem from any valid input.
     * Validates the specified item belongs to the current user's cart.
     *
     * @param $item
     *
     * @return CartItem
     */
    private function resolveItem($item): CartItem
    {
        if ( ! $item instanceof CartItem) {
            $item = $this->itemFromCartSession($item);
        } else {
            $this->ensureItemBelongsToSession($item);
        }

        return $item;
    }

    /**
     * Return an item from the current user's cart.
     *
     * @param $id int
     *
     * @return CartItem
     */
    protected function itemFromCartSession($id)
    {
        return CartItem::whereHas('cart', function ($q) {
            $q->where('session_id', static::getSessionId());
        })->findOrFail($id);
    }

    /**
     * Make sure an item belongs to the current user's cart.
     *
     * @param CartItem $item
     */
    protected function ensureItemBelongsToSession(CartItem $item)
    {
        if ($item->cart->session_id !== static::getSessionId()) {
            throw new \LogicException('The modified item does not belong to the current user\'s cart.');
        }
    }

    /**
     * Return the cart's totals
     */
    public function getTotalsAttribute()
    {
        if ($this->cachedTotals !== null) {
            return $this->cachedTotals;
        }

        return $this->cachedTotals = new Totals($this);
    }

    public function getListItemsAttribute()
    {
        if ($this->cachedListItems) {
            return $this->cachedListItems;
        }

        return $this->cachedListItems = $this->items->where('kind', CartItem::KIND_ITEM);
    }

    public function getServiceFeesAttribute()
    {
        if ($this->cachedServiceFees) {
            return $this->cachedServiceFees;
        }

        return $this->cachedServiceFees = $this->items->where('kind', CartItem::KIND_SERVICE);
    }

    public function getDiscountsAttribute()
    {
        if ($this->cachedDiscounts) {
            return $this->cachedDiscounts;
        }

        return $this->cachedDiscounts = $this->items->where('kind', CartItem::KIND_DISCOUNT);
    }

    /**
     * Return all parts of the shipping address as an array.
     *
     * @param bool $reverseZip
     *
     * @return array
     */
    public function getShippingAddressArray($reverseZip = false): array
    {
        $parts = [
            $this->shipping_company,
            $this->shipping_firstname . ' ' . $this->shipping_lastname,
            $this->shipping_lines,
            $reverseZip
                ? $this->shipping_city . ' ' . $this->shipping_zip
                : $this->shipping_zip . ' ' . $this->shipping_city,
            $this->shipping_country,
        ];

        return array_filter($parts);
    }

    /**
     * Returns the shipping address as formatted and escaped HTML string.
     */
    public function getShippingAddressHtml($reverseZip = false): string
    {
        $address = array_map('e', $this->getShippingAddressArray($reverseZip));

        return implode('<br>', $address);
    }

    /**
     * Return all parts of the billing address as an array.
     *
     * @param bool $reverseZip
     *
     * @return array
     */
    public function getBillingAddressArray($reverseZip = false): array
    {
        $parts = [
            $this->billing_company,
            $this->billing_firstname . ' ' . $this->billing_lastname,
            $this->billing_lines,
            $reverseZip
                ? $this->billing_city . ' ' . $this->billing_zip
                : $this->billing_zip . ' ' . $this->billing_city,
            $this->billing_country,
        ];

        return array_filter($parts);
    }

    /**
     * Returns the billing address as formatted and escaped HTML string.
     *
     * @param bool $reverseZip
     *
     * @return string
     */
    public function getBillingAddressHtml($reverseZip = false): string
    {
        $address = array_map('e', $this->getBillingAddressArray($reverseZip));

        return implode('<br>', $address);
    }

    public function getShippingAddressHtmlZipReversed(): string
    {
        return $this->getShippingAddressHtml(true);
    }

    public function getBillingAddressHtmlZipReversed(): string
    {
        return $this->getBillingAddressHtml(true);
    }

    private function flushCache(): void
    {
        $this->cachedTotals      = null;
        $this->cachedListItems   = null;
        $this->cachedServiceFees = null;
        $this->cachedDiscounts   = null;
    }

    public function setPaymentMethod(PaymentMethod $method)
    {
        $this->payment_method_id = $method->id;
        $this->save();
    }
}
