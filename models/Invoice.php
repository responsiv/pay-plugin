<?php namespace Responsiv\Pay\Models;

use Model;
use DB as Db;
use Carbon\Carbon;

/**
 * Invoice Model
 */
class Invoice extends Model
{

    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_pay_invoices';

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
     * @var array List of datetime attributes to convert to an instance of Carbon/DateTime objects.
     */
    public $dates = ['processed_at', 'status_updated_at', 'deleted_at', 'sent_at', 'due_at'];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'user' => ['RainLab\User\Models\User'],
        'status' => ['Responsiv\Pay\Models\InvoiceStatus'],
        'payment_type' => ['Responsiv\Pay\Models\Type'],
    ];

    public $hasMany = [
        'items' => ['Responsiv\Pay\Models\InvoiceItem'],
    ];

    public function isPaymentProcessed($force = false)
    {
        if ($force)
            return $this->where('id', $this->id)->pluck('processed_at');

        return $this->processed_at;
    }

    public function markAsPaymentProcessed()
    {
        if (!$isPaid = $this->isPaymentProcessed(true)) {
            $now = $this->processed_at = Carbon::now();

            // Instant update here in case a simultaneous request causes invalid data
            $this->where('id', $this->id)->update(['processed_at' => $now]);

            $this->save();
        }

        return !$isPaid;
    }

    public function getReceiptUrl($page = null, $addHostname = false)
    {
        // @todo Need a way to obtain this
        return '/receipt_url';
    }

}