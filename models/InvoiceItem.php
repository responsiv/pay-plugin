<?php namespace Responsiv\Pay\Models;

use Model;

/**
 * InvoiceItem Model
 *
 * @property int $id
 * @property string $description
 * @property int $quantity quantity the total quantity of units
 * @property int $price price the per-unit price
 * @property int $price_less_tax price_less_tax the per-unit price excluding tax
 * @property int $price_with_tax price_with_tax the per-unit price including tax
 * @property int $discount discount the line discount amount (total for the row)
 * @property int $tax tax the line tax amount
 * @property int $subtotal subtotal the line subtotal (qty * price - discount)
 * @property int $total total the line total (subtotal + tax)
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
     * @var string table used by the model
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

    /**
     * beforeSave
     */
    public function beforeSave()
    {
        if (!$this->tax_class_id) {
            $this->tax_class = Tax::getDefault();
        }

        $this->subtotal = $this->quantity * $this->price - $this->discount;
        $this->total = $this->subtotal + $this->tax;
    }

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
