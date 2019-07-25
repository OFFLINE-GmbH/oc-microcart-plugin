<?php

namespace OFFLINE\MicroCart\Classes\PaymentState;

abstract class PaymentState
{
    abstract public static function getAvailableTransitions(): array;

    public static function label(): string
    {
        $parts = explode('\\', get_called_class());
        $state = snake_case($parts[count($parts) - 1]);

        return trans('offline.microcart::lang.order.payment_states.' . $state);
    }

    public static function color(): string
    {
        return '#333';
    }
}
