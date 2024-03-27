<?php namespace Responsiv\Pay\Models\InvoiceItem;

/**
 * HasModelAttributes
 */
trait HasModelAttributes
{
    /**
     * setDiscountAttribute checks for negative and percentage values
     */
    public function setDiscountAttribute($amount)
    {
        if (strpos($amount, '-') !== false) {
            $amount = str_replace('-', '', $amount);
        }

        if (strpos($amount, '%') !== false) {
            $amount = str_replace('%', '', $amount);
            $amount = $this->price * ($amount / 100);
        }

        $this->attributes['discount'] = $amount;
    }
}
