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


}