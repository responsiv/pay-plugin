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
 * @property int $updated_user_id
 * @property int $created_user_id
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon $created_at
 *
 * @package responsiv\pay
 * @author Alexey Bobkov, Samuel Georges
 */
class InvoiceStatusLog extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\UserFootprints;

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
     * createRecord
     */
    public static function createRecord($statusId, $invoice, $comment = null)
    {
        if (is_string($statusId) && !is_numeric($statusId)) {
            $statusId = InvoiceStatus::findByCode($statusId);
        }

        if ($statusId instanceof Model) {
            $statusId = $statusId->getKey();
        }

        $previousStatus = $invoice->status_id;
        if (!$statusId || $previousStatus == $statusId) {
            return false;
        }

        if (!static::checkStatusTransition($previousStatus, $statusId)) {
            return false;
        }

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

        if ($status = InvoiceStatus::findByKey($statusId)) {
            Event::fire('pay.invoice.updateStatus', [$invoice, $status, $previousStatus]);
        }

        return true;
    }

    /**
     * checkStatusTransition ensures invoice status can be moved in a specific direction
     */
    protected static function checkStatusTransition($fromStatusId, $toStatusId): bool
    {
        // New record, allow everything to start
        if (!$fromStatusId) {
            return true;
        }

        $fromStatus = InvoiceStatus::findByKey($fromStatusId);
        $toStatus = InvoiceStatus::findByKey($toStatusId);

        if (!$toStatus || !$fromStatus) {
            return false;
        }

        $statusMap = [
            InvoiceStatus::STATUS_DRAFT => [
                InvoiceStatus::STATUS_APPROVED,
                InvoiceStatus::STATUS_PAID,
                InvoiceStatus::STATUS_VOID,
            ],
            InvoiceStatus::STATUS_APPROVED => [
                InvoiceStatus::STATUS_PAID,
                InvoiceStatus::STATUS_VOID,
            ],
            InvoiceStatus::STATUS_PAID => [
                InvoiceStatus::STATUS_VOID,
            ],
            InvoiceStatus::STATUS_VOID => [],
        ];

        $allowTransitions = $statusMap[$fromStatus->code] ?? [];

        return in_array($toStatus->code, $allowTransitions);
    }
}
