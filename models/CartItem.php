<?php namespace OFFLINE\MicroCart\Models;

use Model;
use October\Rain\Database\Traits\Sortable;

class CartItem extends Model
{
    /**
     * Marks an item as a default cart item.
     * @var string
     */
    const KIND_ITEM = '100_item';
    /**
     * Marks an item as a discount.
     * @var string
     */
    const KIND_DISCOUNT = '500_discount';
    /**
     * Marks an item as a service fee.
     * @var string
     */
    const KIND_SERVICE = '900_service_fee';

    use \October\Rain\Database\Traits\Validation;
    use Sortable;

    public $table = 'offline_microcart_items';
    public $rules = [
        'cart_id'       => 'exists:offline_microcart_carts,id',
        'name'          => 'required',
        'quantity'      => 'integer|min:0',
        'percentage'    => 'nullable|numeric',
        'tax_id'        => 'nullable|exists:offline_microcart_taxes,id',
        'is_tax_free'   => 'boolean',
        'is_before_tax' => 'boolean',
    ];
    public $with = ['taxes'];
    public $fillable = [
        'name',
        'code',
        'quantity',
        'price',
        'tax_id',
        'is_before_tax',
        'is_tax_free',
        'percentage',
        'meta',
        'kind',
        'sort_order',
    ];
    public $jsonable = ['meta'];

    protected $touches = ['cart'];

    public $casts = [
        'quantity'      => 'integer',
        'price'         => 'integer',
        'total'         => 'integer',
        'subtotal'      => 'integer',
        'tax_amount'    => 'integer',
        'is_before_tax' => 'boolean',
        'is_tax_free'   => 'boolean',
        'sort_order'    => 'integer',
        'percentage'    => 'float',
    ];
    public $belongsTo = [
        'cart' => Cart::class,
    ];
    public $belongsToMany = [
        'taxes' => [Tax::class, 'table' => 'offline_microcart_cart_item_tax'],
    ];

    public function scopeIsListItem($q)
    {
        return $q->where('kind', self::KIND_ITEM);
    }

    public function scopeIsServiceFee($q)
    {
        return $q->where('kind', self::KIND_SERVICE);
    }

    public function scopeIsDiscount($q)
    {
        return $q->where('kind', self::KIND_DISCOUNT);
    }

    public function beforeSave()
    {
        if ( ! $this->kind) {
            $this->kind = self::KIND_ITEM;
        }

        $this->calculateTotals();
    }

    public function afterFetch()
    {
        $this->calculateTotals();
    }

    public function setQuantityAttribute($value)
    {
        $this->attributes['quantity'] = $value;
        $this->calculateTotals();
    }

    public function setPriceAttribute($value)
    {
        $this->attributes['price'] = $value * 100;
        $this->calculateTotals();
    }

    public function setTaxIdAttribute($value)
    {
        $this->taxes()->attach($value);
        $this->reloadRelations('taxes');
        $this->calculateTotals();
    }

    /**
     * Calculate read-only values on the CartItem.
     */
    protected function calculateTotals()
    {
        $this->attributes['subtotal'] = $this->quantity * $this->price;

        $taxFactor = optional($this->taxes)->sum('percentage_decimal') ?? 0;

        $tax = $this->is_before_tax
            ? $this->subtotal * $taxFactor
            : $this->subtotal / (1 + $taxFactor) * $taxFactor;

        $this->attributes['tax_amount'] = round($tax);

        if ($this->is_before_tax) {
            $this->attributes['total'] = $this->subtotal + $this->tax_amount;
        } else {
            $this->attributes['total'] = $this->subtotal;
        }
    }
}
