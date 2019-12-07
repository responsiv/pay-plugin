<?php namespace Responsiv\Pay\Models;

use Model;

/**
 * InvoiceItem Model
 */
class InvoiceItem extends Model
{
    use \October\Rain\Database\Traits\Sortable;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_pay_invoice_items';

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'invoice' => ['Responsiv\Pay\Models\Invoice', 'push' => false],
        'tax_class' => ['Responsiv\Pay\Models\Tax'],
    ];

    public $morphTo = [
        'related' => []
    ];

    public function beforeSave()
    {
        if (!$this->tax_class_id) {
            $this->tax_class = Tax::getDefault();
        }

        $this->calculateTotals();
    }

    /**
     * Calculates the totals for this line item, including taxes.
     * @return void
     */
    public function calculateTotals()
    {
        $discountAmount = $this->price * $this->discount;
        $this->subtotal = ($this->price - $discountAmount) * $this->quantity;

        $taxClass = $this->getTaxClass();

        if ($taxClass && $this->invoice && !$this->is_tax_exempt) {
            $this->tax = $taxClass->getTotalTax($this->subtotal);
        }

        $this->total = $this->subtotal + $this->tax;
    }

    public function getTaxClass()
    {
        if ($this->tax_class_id && $this->tax_class) {
            $taxClass = $this->tax_class;
        }
        elseif ($this->invoice && $this->invoice->tax_class) {
            $taxClass = $this->invoice->tax_class;
        }
        else {
            $taxClass = Tax::getDefault();
        }

        if ($taxClass && $this->invoice) {
            $taxClass->setLocationInfo($this->invoice->getLocationInfo());
        }

        return $taxClass;
    }

    //
    // Scopes
    //

    public function scopeApplyRelated($query, $object)
    {
        return $query
            ->where('related_type', get_class($object))
            ->where('related_id', $object->getKey())
        ;
    }

    public function scopeApplyInvoice($query, $invoice)
    {
        return $query->where('invoice_id', $invoice->id);
    }
}
