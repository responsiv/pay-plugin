<?php namespace Responsiv\Pay\Models;

use Model;

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
    public $hasOne = [];
    public $hasMany = [];
    public $belongsTo = [];
    public $belongsToMany = [];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];


    public static function createRecord($status_id, $invoice, $comment = null) 
    { 
        // Nothing to do
        if ($invoice->status_id == $status_id)
            return false;

        // Extensibility
        $previous_status = $invoice->status_id;
        $result = Phpr::$events->fire_event('payment:on_invoice_before_update', $invoice, $status_id, $previous_status);
        
        if ($result === false)
            return false;

        // Create record
        $record = self::create();
        $record->status_id = $status_id;
        $record->invoice_id = $invoice->id;
        $record->comment = $comment;
        $record->save();

        // Update invoice status
        Db_Helper::query('update payment_invoices set status_id=:status_id, status_updated_at=:now where id=:id', array(
            'status_id'=>$status_id,
            'now'=>Phpr_Date::user_date(Phpr_DateTime::now()),
            'id'=>$invoice->id
        ));

        $status_paid = Payment_Invoice_Status::get_status_paid();

        if (!$status_paid)
            return trace_log('Unable to find payment status with paid code');

        // @todo Send email notifications
        
        if ($status_id == $status_paid->id)
        {
            // Resolve any promises kept by this invoice
            // this may redirect so place last
            Payment_Fee_Promise::resolve_from_invoice($invoice);
        }
    }

}