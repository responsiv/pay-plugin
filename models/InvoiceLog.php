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

}