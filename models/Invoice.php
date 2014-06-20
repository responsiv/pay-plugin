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


}