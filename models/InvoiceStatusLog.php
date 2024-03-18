<?php namespace Responsiv\Pay\Models;

use Event;
use Carbon\Carbon;
use October\Rain\Database\Model;

/**
 * InvoiceStatusLog record
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $status_id
 * @property string $comment
 * @property int $admin_id
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon $created_at
 *
 * @package responsiv\pay
 * @author Alexey Bobkov, Samuel Georges
 */
class InvoiceStatusLog extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table used by the model
     */
    public $table = 'responsiv_pay_invoice_status_logs';

    /**
     * @var array rules for validation
     */
    public $rules = [
        'status' => 'required',
    ];

    /**
     * @var array belongsTo
     */
    public $belongsTo = [
        'status'  => InvoiceStatus::class,
        'invoice' => Invoice::class,
    ];

    /**
     * getStatusOptions
     */
    public function getStatusOptions()
    {
        return InvoiceStatus::lists('name', 'id');
    }

    /**
     * filterFields
     */
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

    /**
     * createRecord
     */
    public static function createRecord($statusId, $invoice, $comment = null, $sendNotifications = true)
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

        // Create record
        $record = new static;
        $record->status_id = $statusId;
        $record->invoice_id = $invoice->id;
        $record->comment = $comment;

        // Extensibility
        if (Event::fire('responsiv.pay.beforeUpdateInvoiceStatus', [$record, $invoice, $statusId, $previousStatus], true) === false) {
            return false;
        }

        $record->save();

        // Update invoice status
        $invoice->newQuery()->where('id', $invoice->id)->update([
            'status_id' => $statusId,
            'status_updated_at' => Carbon::now()
        ]);

        $statusPaid = InvoiceStatus::getPaidStatus();
        if (!$statusPaid) {
            return traceLog('Unable to find payment status with paid code');
        }

        // @todo Send email notifications

        if ($statusId == $statusPaid->id) {
            // Invoice is paid
        }
    }
}
