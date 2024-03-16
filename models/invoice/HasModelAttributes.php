<?php namespace Responsiv\Pay\Models\Invoice;

use Responsiv\Currency\Models\Currency;

/**
 * HasModelAttributes
 *
 * @property bool $is_paid
 * @property bool $is_past_due_date
 * @property string $currency_code
 * @property string $status_code
 */
trait HasModelAttributes
{
    /**
     * getIsPaidAttribute
     */
    public function getIsPaidAttribute()
    {
        return $this->isPaymentProcessed();
    }

    /**
     * getIsPastDueDateAttribute
     */
    public function getIsPastDueDateAttribute()
    {
        if (!$this->due_at) {
            return true;
        }

        return $this->due_at->isPast() || $this->due_at->isToday();
    }

    /**
     * getCurrencyAttribute
     */
    public function getCurrencyCodeAttribute()
    {
        return $this->currency ?: Currency::getPrimary()?->currency_code;
    }

    /**
     * getStatusCodeAttribute
     */
    public function getStatusCodeAttribute()
    {
        return $this->status ? $this->status->code : null;
    }
}
