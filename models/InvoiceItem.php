<?php namespace Responsiv\Pay\Models;

use Model;

/**
 * InvoiceItem Model
 *
 * @property int $id
 * @property string $description
 * @property int $quantity quantity the total quantity of units
 * @property int $price
 * @property int $total
 * @property int $discount
 * @property int $subtotal
 * @property int $tax
 * @property int $tax_discount
 * @property bool $is_tax_exempt
 * @property int $sort_order
 * @property int $related_id
 * @property string $related_type
 * @property int $invoice_id
 * @property int $tax_class_id
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon $created_at
 *
 * @package responsiv\pay
 * @author Alexey Bobkov, Samuel Georges
 */
class InvoiceItem extends Model
{
    use \Responsiv\Pay\Models\InvoiceItem\HasModelAttributes;
    use \Responsiv\Pay\Models\InvoiceItem\HasCalculatedAttributes;
    use \October\Rain\Database\Traits\Sortable;
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var array The rules to be applied to the data.
     */
    public $rules = [
        'quantity' => 'required',
        'price' => 'required'
    ];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_pay_invoice_items';

    /**
     * @var array belongsTo
     */
    public $belongsTo = [
        'invoice' => Invoice::class,
        'tax_class' => Tax::class,
    ];

    /**
     * @var array morphTo
     */
    public $morphTo = [
        'related' => []
    ];

    // /**
    //  * beforeSave
    //  */
    // public function beforeSave()
    // {
    //     if (!$this->tax_class_id) {
    //         $this->tax_class = Tax::getDefault();
    //     }

    //     $this->calculateTotals();
    // }

    // /**
    //  * calculateTotals for this line item, including taxes.
    //  */
    // public function calculateTotals()
    // {
    //     $discountAmount = $this->price * $this->discount;
    //     $this->subtotal = ($this->price - $discountAmount) * $this->quantity;

    //     $taxClass = $this->getTaxClass();

    //     if ($taxClass && $this->invoice && !$this->is_tax_exempt) {
    //         $this->tax = $taxClass->getTotalTax($this->subtotal);
    //     }

    //     $this->total = $this->subtotal + $this->tax;
    // }

    // public function getTaxClass()
    // {
    //     if ($this->tax_class_id && $this->tax_class) {
    //         $taxClass = $this->tax_class;
    //     }
    //     elseif ($this->invoice && $this->invoice->tax_class) {
    //         $taxClass = $this->invoice->tax_class;
    //     }
    //     else {
    //         $taxClass = Tax::getDefault();
    //     }

    //     if ($taxClass && $this->invoice) {
    //         $taxClass->setLocationInfo($this->invoice->getLocationInfo());
    //     }

    //     return $taxClass;
    // }

    /**
     * scopeApplyRelated
     */
    public function scopeApplyRelated($query, $object)
    {
        return $query
            ->where('related_type', get_class($object))
            ->where('related_id', $object->getKey())
        ;
    }

    /**
     * scopeApplyInvoice
     */
    public function scopeApplyInvoice($query, $invoice)
    {
        return $query->where('invoice_id', $invoice->id);
    }
}
