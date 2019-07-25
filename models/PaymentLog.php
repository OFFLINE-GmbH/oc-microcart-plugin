<?php namespace OFFLINE\MicroCart\Models;

use Model;
use October\Rain\Database\Traits\Validation;

class PaymentLog extends Model
{
    use Validation;

    public $jsonable = ['data', 'cart_data'];
    public $table = 'offline_microcart_payments_log';
    public $casts = [
        'failed' => 'boolean',
    ];
    public $rules = [
        'failed'         => 'required|boolean',
        'payment_method' => 'required',
    ];
    public $belongsTo = [
        'cart' => Cart::class,
    ];

    public static function boot()
    {
        parent::boot();
        static::creating(function (self $log) {
            $log->reference = str_random(16);
        });
    }

    /**
     * Try to json_decode the message. If it's not json encoded data
     * just return the original value.
     *
     * @return string
     */
    public function getMessageAttribute()
    {
        if ( ! isset($this->attributes['message'])) {
            return '';
        }

        $result = json_decode($this->attributes['message']);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }

        return $this->attributes['message'];
    }

    /**
     * Return the raw json encoded message.
     *
     * @return string
     */
    public function getMessageRawAttribute()
    {
        if ( ! isset($this->attributes['message'])) {
            return '';
        }

        return $this->attributes['message'];
    }
}
