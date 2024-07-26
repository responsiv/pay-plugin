<?php namespace Responsiv\Pay\Models\Invoice;

use Responsiv\Currency\Models\Currency;

/**
 * HasModelAttributes
 *
 * @property bool $is_paid
 * @property bool $is_past_due_date
 * @property int $original_subtotal
 * @property int $final_subtotal
 * @property int $final_discount
 * @property string $currency_code
 * @property string $street_address
 * @property string $status_code
 * @property string $tax_mode
 * @property \Illuminate\Support\Carbon $invoiced_at
 */
trait HasModelAttributes
{
    /**
     * getInvoicedAtAttribute returns the `invoiced_at` attribute
     */
    public function getInvoicedAtAttribute()
    {
        return $this->sent_at ?: $this->created_at;
    }

    /**
     * getInvoiceNumberAttribute
     */
    public function getInvoiceNumberAttribute()
    {
        return $this->getUniqueId();
    }

    /**
     * getOriginalSubtotalAttribute
     */
    public function getOriginalSubtotalAttribute(): int
    {
        return $this->subtotal - $this->discount;
    }

    /**
     * getFinalSubtotalAttribute
     */
    public function getFinalSubtotalAttribute(): int
    {
        if ($this->prices_include_tax) {
            return $this->subtotal;
        }

        return $this->subtotal + $this->tax;
    }

    /**
     * getFinalDiscountAttribute
     */
    public function getFinalDiscountAttribute(): int
    {
        return $this->discount + $this->discount_tax;
    }

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
     * getCurrencyCodeAttribute returns `currency_code`
     */
    public function getCurrencyCodeAttribute()
    {
        return $this->currency ?: Currency::getPrimary()?->currency_code;
    }

    /**
     * getStatusCodeAttribute returns `status_code`
     */
    public function getStatusCodeAttribute()
    {
        return $this->status?->code;
    }

    /**
     * getStreetAddressAttribute
     */
    public function getStreetAddressAttribute()
    {
        return "{$this->address_line1}\n{$this->address_line2}";
    }

    /**
     * setStreetAddressAttribute
     */
    public function setStreetAddressAttribute($address)
    {
        $parts = explode("\n", $address, 2);
        $this->attributes['address_line1'] = $parts[0];
        $this->attributes['address_line2'] = $parts[1] ?? '';
    }
}
