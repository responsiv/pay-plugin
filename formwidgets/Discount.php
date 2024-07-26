<?php namespace Responsiv\Pay\FormWidgets;

use Backend\Classes\FormField;
use Backend\Classes\FormWidgetBase;
use Responsiv\Currency\Models\Currency as CurrencyModel;

/**
 * Discount input
 */
class Discount extends FormWidgetBase
{
    //
    // Configurable properties
    //

    /**
     * @var bool fixedPrice allows a set price override
     */
    public $fixedPrice = false;

    //
    // Object properties
    //

    /**
     * {@inheritDoc}
     */
    public $defaultAlias = 'discount';

    /**
     * {@inheritDoc}
     */
    public function init()
    {
        $this->fillFromConfig([
            'fixedPrice'
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function render()
    {
        $this->prepareVars();

        return $this->makePartial('discount');
    }

    /**
     * Prepares the list data
     */
    public function prepareVars()
    {
        [$amount, $symbol] = $this->getLoadAmount();
        $this->vars['amount'] = $amount;
        $this->vars['symbol'] = $symbol;

        $this->vars['name'] = $this->formField->getName();
        $this->vars['field'] = $this->formField;
        $this->vars['fixedPrice'] = $this->fixedPrice;
    }

    /**
     * getLoadAmount
     */
    public function getLoadAmount()
    {
        $value = $this->getLoadValue();
        if ($value === null) {
            return [null, null];
        }

        $amount = $value;

        // Percentage
        $isPercentage = strpos($value, '%') !== false;
        if ($isPercentage) {
            $amount = str_replace('%', '', $amount);
            return [$amount.'%', '%'];
        }

        // Fixed price or discount
        $symbol = '';
        $isNegativeFloat = strpos($amount, '-') !== false;
        if ($isNegativeFloat) {
            $symbol = '-';
            $amount = str_replace('-', '', $amount);
        }

        $currencyObj = CurrencyModel::getPrimary();
        $amount = $currencyObj->fromBaseValue((int) $amount);
        $amount = number_format(
            $amount,
            $currencyObj->decimal_scale,
            $currencyObj->decimal_point,
            ""
        );

        return [$symbol.$amount, $symbol];
    }

    /**
     * {@inheritDoc}
     */
    public function getSaveValue($value)
    {
        if ($this->formField->disabled || $this->formField->hidden) {
            return FormField::NO_SAVE_DATA;
        }

        if (!is_array($value) || !array_key_exists('amount', $value)) {
            return FormField::NO_SAVE_DATA;
        }

        $amount = $value['amount'];
        $symbol = trim($value['symbol'] ?? '');

        if (!strlen($amount)) {
            return null;
        }

        // Percentage
        if ($symbol === '%' || str_ends_with($amount, '%') !== false) {
            $amount = str_replace('%', '', $amount);
            return "{$amount}%";
        }

        // Subtract
        if ($symbol === '-' || str_starts_with($amount, '-') !== false) {
            $amount = str_replace('-', '', $amount);
        }

        $currencyObj = CurrencyModel::getPrimary();
        $amount = floatval(str_replace($currencyObj->decimal_point, '.', $amount));
        $amount = $currencyObj->toBaseValue((float) $amount);
        return $symbol . $amount;
    }
}
