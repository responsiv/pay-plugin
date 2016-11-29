<?php namespace Responsiv\Pay\Models;

use Model;

/**
 * Invoice payment log
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
    protected $guarded = [];

    /**
     * @var array List of attribute names which are json encoded and decoded from the database.
     */
    protected $jsonable = ['request_data', 'response_data'];

    public static function createRecord($invoice, $message = null, $options = [])
    {
        extract(array_merge([
            'isSuccess' => null,
            'methodName' => 'Unspecified',
            'requestArray' => null,
            'responseArray' => null,
            'responseText' => null,
        ], $options));

        $record = new self;
        $record->message = $message;
        $record->invoice_id = $invoice->id;
        $record->payment_method_name = $methodName;
        $record->is_success = $isSuccess;

        $record->raw_response = $responseText;
        $record->request_data = $requestArray;
        $record->response_data = $responseArray;

        $record->save();

        return $record;
    }

    public static function createManualPayment($invoice, $message = null)
    {
        $record = new self;
        $record->message = $message;
        $record->invoice_id = $invoice->id;
        $record->payment_method_name = 'Manual payment';
        $record->is_success = true;
        $record->save();

        return $record;
    }
}
