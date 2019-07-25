<?php

namespace OFFLINE\MicroCart\Classes;


use Closure;
use October\Rain\Support\Traits\Singleton;
use OFFLINE\MicroCart\Models\GeneralSettings;

class Money
{
    use Singleton;

    /**
     * @var Closure
     */
    public $formatter;

    public function init()
    {
        $this->formatter = function ($value): string {
            return sprintf(
                '%s %s',
                number_format((int)$value / 100, 2),
                GeneralSettings::get('default_currency')
            );
        };
    }

    public function setFormatter(Closure $fn)
    {
        $this->formatter = $fn;
    }

    public function format(int $value): string
    {
        return call_user_func($this->formatter, $value);
    }
}