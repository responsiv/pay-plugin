<?php namespace Responsiv\Pay\Models;

use Model;

/**
 * InvoiceItem Model
 */
class InvoiceItem extends Model
{

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
     * @var array Validation rules
     */
    public $rules = [];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'invoice' => ['Responsiv\Pay\Models\Invoice', 'push' => false],
        'tax_class' => ['Responsiv\Pay\Models\Tax'],
    ];

    public function beforeSave()
    {
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

        if ($this->invoice && !$this->is_tax_exempt) {
            $this->tax = Tax::getTotalTax($this->tax_class_id, $this->subtotal, $this->invoice->getLocationInfo());
        }

        $this->total = $this->subtotal + $this->tax;
    }

}