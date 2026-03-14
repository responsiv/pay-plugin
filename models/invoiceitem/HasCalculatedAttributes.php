<?php namespace Responsiv\Pay\Models\InvoiceItem;

use Responsiv\Pay\Models\Tax;

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
        $this->tax = 0;

        // Recalculate taxes
        $taxClass = Tax::findByKey($this->tax_class_id);
        if (!$taxClass) {
            return;
        }

        if ($this->prices_include_tax) {
            $this->price_less_tax = $this->price - $taxClass->getTotalUntax($this->price, $address);
            $priceTax = ($this->price - $this->price_less_tax) * $this->quantity;
            $discountTax = $taxClass->getTotalUntax($this->discount, $address);
        }
        else {
            $this->price_with_tax = $this->price + $taxClass->getTotalTax($this->price, $address);
            $priceTax = ($this->price_with_tax - $this->price) * $this->quantity;
            $discountTax = $taxClass->getTotalTax($this->discount, $address);
        }

        $this->tax = $priceTax - $discountTax;
    }
}
