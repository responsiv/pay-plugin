<?php namespace Responsiv\Pay\Models;

use Event;
use Model;
use Carbon\Carbon;

/**
 * InvoiceLog Model
 */
class InvoiceLog extends Model
{

    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_pay_invoice_logs';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

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
        'status'  => ['Responsiv\Pay\Models\InvoiceStatus'],
        'invoice' => ['Responsiv\Pay\Models\Invoice', 'push' => false],
    ];

    public static function createRecord($statusId, $invoice, $comment = null)
    {
        if ($invoice->status_id == $statusId)
            return false;

        /*
         * Extensibility
         */
        $previousStatus = $invoice->status_id;

        if (Event::fire('responsiv.pay:beforeUpdateInvoiceStatus', [$invoice, $statusId, $previousStatus], true) === false)
            return false;

        if ($this->fireEvent('pay:beforeUpdateInvoiceStatus', [$invoice, $statusId, $previousStatus], true) === false)
            return false;

        /*
         * Create record
         */
        $record = new static;;
        $record->status_id = $statusId;
        $record->invoice_id = $invoice->id;
        $record->comment = $comment;
        $record->save();

        /*
         * Update invoice status
         */
        $invoice->update([
            'status_id' => $statusId,
            'status_updated_at' => Carbon:: now()
        ]);

        $statusPaid = InvoiceStatus::getStatusPaid();

        if (!$statusPaid)
            return traceLog('Unable to find payment status with paid code');

        // @todo Send email notifications

        if ($statusId == $statusPaid->id) {
            // Invoice is paid
        }
    }

}