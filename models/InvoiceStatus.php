<?php namespace Responsiv\Pay\Models;

use Model;

/**
 * InvoiceStatus Model
 */
class InvoiceStatus extends Model
{

    const STATUS_DRAFT = 'draft';
    const STATUS_APPROVED = 'approved';
    const STATUS_PAID = 'paid';
    const STATUS_VOID = 'void';

    protected static $codeCache = [];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_pay_invoice_statuses';

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

    public $timestamps = false;

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

    public static function getStatusDraft()
    {
        return static::getByCode(static::STATUS_DRAFT);
    }

    public static function getStatusApproved()
    {
        return static::getByCode(static::STATUS_APPROVED);
    }

    public static function getStatusPaid()
    {
        return static::getByCode(static::STATUS_PAID);
    }

    public static function getStatusVoid()
    {
        return static::getByCode(static::STATUS_VOID);
    }

    public static function getByCode($code)
    {
        if (array_key_exists($code, static::$codeCache))
            return static::$codeCache[$code];

        $status = static::whereCode($code)->first();

        return static::$codeCache[$code] = $status;
    }
}