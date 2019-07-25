<?php namespace OFFLINE\MicroCart\Models;

use Illuminate\Support\Optional;
use Model;
use October\Rain\Database\Traits\Validation;

class Tax extends Model
{
    use Validation;

    public $rules = [
        'name'       => 'required',
        'percentage' => 'numeric|min:0|max:100',
    ];
    public $fillable = [
        'name',
        'percentage',
        'is_default',
    ];
    public $table = 'offline_microcart_taxes';
    public $casts = [
        'is_default' => 'boolean',
    ];
    public $belongsToMany = [
        'carts' => [
            Cart::class,
            'table' => 'offline_microcart_carts',
        ],
    ];

    public function beforeSave()
    {
        if ($this->is_default) {
            self::where('is_default', true)->update(['is_default' => false]);
        }
    }

    /**
     * Returns the default Tax rate.
     */
    public static function getDefault(): Optional
    {
        return optional(self::orderBy('is_default', 'DESC')->first());
    }

    public function getPercentageDecimalAttribute()
    {
        return (float)$this->percentage / 100;
    }
}
