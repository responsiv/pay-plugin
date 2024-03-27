<?php namespace Responsiv\Pay\Models\InvoiceItem;

use Responsiv\Shop\Models\TaxClass;

/**
 * HasCalculatedAttributes
 */
trait HasCalculatedAttributes
{
    /**
     * evalInvoiceItemTotals
     */
    public function evalInvoiceItemTotals(array $options = [])
    {
        extract(array_merge([
            'invoice' => null,
            'address' => null,
        ], $options));

        // Locate address
        if ($address === null && $invoice) {
            $address = $invoice->getTaxableAddress();
        }

        // Defaults
        $this->price_with_tax = $this->price_less_tax = $this->price;
        $this->discount_with_tax = $this->discount_less_tax = $this->discount;
        $this->tax = 0;

        // Recalculate taxes
        $taxClass = TaxClass::findByKey($this->tax_class_id);
        if (!$taxClass) {
            return;
        }

        if ($this->prices_include_tax) {
            $this->price_less_tax = $this->price - $taxClass->getTotalUntax($this->price, $address);
            $this->discount_less_tax = $this->discount - $taxClass->getTotalUntax($this->discount, $address);
            $this->tax = ($this->price - $this->price_less_tax) - ($this->discount - $this->discount_less_tax);
        }
        else {
            $this->price_with_tax = $this->price + $taxClass->getTotalTax($this->price, $address);
            $this->discount_with_tax = $this->discount + $taxClass->getTotalTax($this->discount, $address);
            $this->tax = ($this->price_with_tax - $this->price) - ($this->discount_with_tax - $this->discount);
        }
    }
}
