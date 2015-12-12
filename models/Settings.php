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
        $this->default_invoice_template = 1;
    }

    public function getDefaultPaymentPageOptions()
    {
        return Page::getNameList();
    }

    public static function getDetaultPaymentPage($params = [])
    {
        $settings = self::instance();
        if (empty($settings->default_payment_page))
            return null;

        return Page::url($settings->default_payment_page, $params);
    }
}