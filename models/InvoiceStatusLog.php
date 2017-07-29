<?php namespace Responsiv\Pay\Models;

use Event;
use Carbon\Carbon;
use October\Rain\Database\Model;

/**
 * Invoice Status Log Model
 */
class InvoiceStatusLog extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var array The rules to be applied to the data.
     */
    public $rules = [
        'status' => 'required'
    ];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_pay_invoice_status_logs';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'status'  => ['Responsiv\Pay\Models\InvoiceStatus'],
        'invoice' => ['Responsiv\Pay\Models\Invoice', 'push' => false],
    ];

    public function filterFields($fields, $context = null)
    {
        if (isset($fields->status) && $this->invoice) {
            $fields->status->value = $this->invoice->status_id;
        }

        if (
            isset($fields->mark_paid) &&
            $this->invoice &&
            $this->invoice->isPaymentProcessed()
        ) {
            $fields->mark_paid->disabled = true;
            $fields->mark_paid->value = true;
        }
    }

    public function getStatusOptions()
    {
        return InvoiceStatus::lists('name', 'id');
    }

    public static function createRecord($statusId, $invoice, $comment = null)
    {
        if (is_string($statusId) && !is_numeric($statusId)) {
            $statusId = InvoiceStatus::findByCode($statusId);
        }

        if ($statusId instanceof Model) {
            $statusId = $statusId->getKey();
        }

        if (!$statusId || $invoice->status_id == $statusId) {
            return false;
        }

        $previousStatus = $invoice->status_id;

        /*
         * Create record
         */
        $record = new static;
        $record->status_id = $statusId;
        $record->invoice_id = $invoice->id;
        $record->comment = $comment;

        /*
         * Extensibility
         */
        if (Event::fire('responsiv.pay.beforeUpdateInvoiceStatus', [$record, $invoice, $statusId, $previousStatus], true) === false) {
            return false;
        }

        if ($record->fireEvent('pay.beforeUpdateInvoiceStatus', [$record, $invoice, $statusId, $previousStatus], true) === false) {
            return false;
        }

        $record->save();

        /*
         * Update invoice status
         */
        $invoice->newQuery()->where('id', $invoice->id)->update([
            'status_id' => $statusId,
            'status_updated_at' => Carbon::now()
        ]);

        $statusPaid = InvoiceStatus::findByCode(InvoiceStatus::STATUS_PAID);

        if (!$statusPaid) {
            return traceLog('Unable to find payment status with paid code');
        }

        // @todo Send email notifications

        if ($statusId == $statusPaid->id) {
            // Invoice is paid
        }
    }

}
