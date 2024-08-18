<?php namespace Responsiv\Pay\Models;

use Model;

/**
 * InvoiceStatus record
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property bool $is_enabled
 * @property bool $notify_user
 * @property string $user_message_template
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property string $color_background
 *
 * @package responsiv\pay
 * @author Alexey Bobkov, Samuel Georges
 */
class InvoiceStatus extends Model
{
    use \System\Traits\KeyCodeModel;
    use \October\Rain\Database\Traits\Validation;

    const STATUS_DRAFT = 'draft';
    const STATUS_APPROVED = 'approved';
    const STATUS_PAID = 'paid';
    const STATUS_VOID = 'void';

    /**
     * @var string table used by the model
     */
    public $table = 'responsiv_pay_invoice_statuses';

    /**
     * @var array rules for validation
     */
    public $rules = [
        'name' => 'required',
    ];

    /**
     * getNewStatus
     */
    public static function getNewStatus()
    {
        return static::findByCode(static::STATUS_DRAFT);
    }

    /**
     * getPaidStatus
     */
    public static function getPaidStatus()
    {
        return static::findByCode(static::STATUS_PAID);
    }

    /**
     * listStatuses
     */
    public static function listStatuses()
    {
        return static::lists('name', 'code');
    }

    /**
     * getStatusNameOptions
     */
    public function getStatusCodeOptions()
    {
        return [
            'draft' => ['Draft', '#98a0a0'],
            'approved' => ['Approved', 'var(--bs-info)'],
            'void' => ['Void', 'var(--bs-danger)'],
            'paid' => ['Paid', 'var(--bs-success)'],
        ];
    }

    /**
     * getColorBackgroundAttribute returns the `color_background` attribute
     */
    public function getColorBackgroundAttribute()
    {
        return $this->getStatusCodeOptions()[$this->code][1] ?? null;
    }
}
