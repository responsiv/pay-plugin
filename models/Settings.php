<?php namespace Responsiv\Pay\Models;

use Model;

class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'responsiv_pay_settings';
    public $settingsFields = 'fields.yaml';

    public function initSettingsData()
    {
        $this->currency_code = 'USD';
        $this->currency_symbol = '$';
        $this->decimal_point = '.';
        $this->thousand_separator = ',';
        $this->place_sign_before = true;
    }

    /**
     * Formats supplied currency to supplied settings.
     * @param  mixed  $number   Currency amount
     * @param  integer $decimals Decimal places to include
     * @return string
     */
    public static function formatCurrency($number, $decimals = 2)
    {
        if (!strlen($number))
            return null;

        $settings = self::instance();

        $negative = $number < 0;
        $negativeSymbol = null;

        if ($negative) {
            $number *= -1;
            $negativeSymbol = '-';
        }

        $number = number_format($number, $decimals, $settings->decimal_point, $settings->thousand_separator);

        if ($settings->place_sign_before) {
            return $negativeSymbol . $settings->sign . $number;
        }
        else {
            return $negativeSymbol . $number . $settings->sign;
        }
    }
}