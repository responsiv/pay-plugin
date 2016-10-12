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

        if ($this->invoice && !$this->is_tax_exempt) {
            $this->tax = Tax::getTotalTax($this->tax_class_id, $this->subtotal, $this->invoice->getLocationInfo());
        }

        $this->total = $this->subtotal + $this->tax;
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
}
