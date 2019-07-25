<?php namespace OFFLINE\MicroCart\Models;

use Model;
use October\Rain\Database\Traits\Sluggable;
use October\Rain\Database\Traits\Sortable;
use October\Rain\Database\Traits\Validation;
use OFFLINE\MicroCart\Classes\Payments\PaymentGateway;
use System\Models\File;

/**
 * Model
 */
class PaymentMethod extends Model
{
    use Sluggable;
    use Sortable;
    use Validation;

    public $rules = [
        'name'             => 'required',
        'payment_provider' => 'required',
    ];

    public $table = 'offline_microcart_payment_methods';

    public $appends = ['settings'];
    public $guarded = [];
    public $slugs = [
        'code' => 'name',
    ];
    public $hasMany = [
        'carts' => Cart::class,
    ];
    public $belongsToMany = [
        'taxes' => [Tax::class, 'table' => 'offline_microcart_payment_method_tax'],
    ];
    public $attachOne = [
        'logo' => File::class,
    ];
    public $casts = [
        'is_default' => 'boolean',
    ];

    public function beforeSave()
    {
        if ($this->is_default) {
            self::where('is_default', true)->update(['is_default' => false]);
        }
    }

    public function setPriceAttribute($value)
    {
        $this->attributes['price'] = is_numeric($value) ? $value * 100 : 0;
    }

    public function setPercentageAttribute($value)
    {
        $this->attributes['percentage'] = is_numeric($value) ? $value : 0;
    }

    public function getPriceAttribute()
    {
        if ( ! isset($this->attributes['price'])) {
            return '';
        }

        return round($this->attributes['price'] / 100, 2);
    }

    public function getPaymentProviderOptions(): array
    {
        /** @var PaymentGateway $gateway */
        $gateway = app(PaymentGateway::class);

        $options = [];
        foreach ($gateway->getProviders() as $id => $class) {
            $method       = new $class;
            $options[$id] = $method->name();
        }

        return $options;
    }

    public static function getDefault()
    {
        return static::orderBy('is_default', 'DESC')->first();
    }

    public function getSettingsAttribute()
    {
        /** @var PaymentGateway $gateway */
        $gateway  = app(PaymentGateway::class);
        $provider = $gateway->getProviderById($this->payment_provider);

        return $provider->getSettings();
    }
}
