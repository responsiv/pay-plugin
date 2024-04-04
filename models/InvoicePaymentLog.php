<?php namespace Responsiv\Pay\Models;

use Model;

/**
 * InvoicePaymentLog Model
 *
 * @property int $id
 * @property string $payment_method_name
 * @property bool $is_successful
 * @property string $message
 * @property array $request_data
 * @property array $response_data
 * @property array $card_data
 * @property string $raw_response
 * @property int $invoice_id
 * @property int $updated_user_id
 * @property int $created_user_id
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon $created_at
 *
 * @package responsiv/pay
 * @author Alexey Bobkov, Samuel Georges
 */
class InvoicePaymentLog extends Model
{
    use \October\Rain\Database\Traits\UserFootprints;

    /**
     * @var string table used by the model
     */
    public $table = 'responsiv_pay_invoice_logs';

    /**
     * @var array jsonable attribute names that are json encoded and decoded from the database
     */
    protected $jsonable = [
        'request_data',
        'response_data'
    ];

    /**
     * @var array belongsTo
     */
    public $belongsTo = [
        'invoice' => Invoice::class,
    ];

    /**
     * createRecord
     */
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

    /**
     * createManualPayment
     */
    public static function createManualPayment($invoice, $message = null)
    {
        $record = new self;
        $record->message = $message;
        $record->invoice_id = $invoice->id;
        $record->payment_method_name = 'Manual Payment';
        $record->is_success = true;
        $record->save();

        return $record;
    }
}
