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

        // Recalculate taxes
        $taxClass = TaxClass::findByKey($this->tax_class_id);
        if (!$taxClass) {
            return;
        }

        $this->price_with_tax = $this->price + $taxClass->getTotalTax($this->price, $address);
        $this->discount_with_tax = $this->discount + $taxClass->getTotalTax($this->discount, $address);
        $this->extras_price_with_tax = $this->extras_price + $taxClass->getTotalTax($this->extras_price, $address);
        $this->tax = ($this->price_with_tax - $this->price) - ($this->discount_with_tax - $this->discount);
    }
}
