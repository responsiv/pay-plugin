<?php namespace Responsiv\Pay\Models;

use Model;
use Responsiv\Pay\Models\Tax as TaxModel;

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
        'tax_class' => 'Responsiv\Pay\Models\Tax',
    ];

    public function getTaxClass()
    {
        return $this->tax_class ?: TaxModel::getDefault();
    }

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

            $taxClass = $this->tax_class_id
                ? TaxModel::findById($this->tax_class_id)
                : TaxModel::getDefault();

            if ($taxClass) {
                $taxClass->setLocationInfo($this->invoice->getLocationInfo());
                $this->tax = $taxClass->getTotalTax($this->subtotal);
            }
            else {
                $this->tax = 0;
            }
        }

        $this->total = $this->subtotal + $this->tax;
    }
}
