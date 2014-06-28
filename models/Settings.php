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
        $this->currency_code = 'USD';
        $this->currency_symbol = '$';
        $this->decimal_point = '.';
        $this->thousand_separator = ',';
        $this->place_symbol_before = true;
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

        if ($settings->place_symbol_before) {
            return $negativeSymbol . $settings->currency_symbol . $number;
        }
        else {
            return $negativeSymbol . $number . $settings->currency_symbol;
        }
    }

    public function getDefaultPaymentPageOptions()
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }
}