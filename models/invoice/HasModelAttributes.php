<?php namespace Responsiv\Pay\Models\Invoice;

use Currency;
use Responsiv\Currency\Models\Currency as CurrencyModel;

/**
 * HasModelAttributes
 *
 * @property bool $is_paid
 * @property bool $is_payment_submitted
 * @property bool $is_past_due_date
 * @property int $amount_due
 * @property int $original_subtotal
 * @property int $final_subtotal
 * @property int $final_discount
 * @property string $street_address
 * @property string $status_code
 * @property string $tax_mode
 * @property \Illuminate\Support\Carbon $invoiced_at
 */
trait HasModelAttributes
{
    /**
     * getAmountDueAttribute returns the outstanding amount after credit applied
     */
    public function getAmountDueAttribute(): int
    {
        return max(0, ($this->total ?? 0) - ($this->credit_applied ?? 0));
    }

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
        return $this->subtotal + $this->discount;
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
     * getIsPaymentSubmittedAttribute returns true if the payment has been
     * submitted by the customer, either fully processed or awaiting
     * confirmation (e.g. PayPal PENDING capture).
     */
    public function getIsPaymentSubmittedAttribute()
    {
        if ($this->isPaymentProcessed()) {
            return true;
        }

        return in_array($this->status_code, [
            \Responsiv\Pay\Models\InvoiceStatus::STATUS_APPROVED,
            \Responsiv\Pay\Models\InvoiceStatus::STATUS_PAID,
        ]);
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
     * getCurrencyObject returns the Currency model for this invoice's currency_code
     */
    public function getCurrencyObject()
    {
        return $this->currency_code
            ? CurrencyModel::findByCode($this->currency_code)
            : Currency::getActive();
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
