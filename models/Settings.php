<?php namespace Responsiv\Pay\Models;

use Model;
use Cms\Classes\Page;

class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'responsiv_pay_settings';
    public $settingsFields = 'fields.yaml';

    public function initSettingsData()
    {
        $this->invoice_prefix = '';
    }
}