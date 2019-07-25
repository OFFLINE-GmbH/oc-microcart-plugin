<?php

namespace OFFLINE\MicroCart\Models;

use Model;
use Session;

class GeneralSettings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];
    public $settingsCode = 'offline_microcart_settings';
    public $settingsFields = '$/offline/microcart/models/settings/fields_general.yaml';
}
