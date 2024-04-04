<?php namespace Responsiv\Pay\Models;

use Event;
use Model;
use Request;
use Responsiv\Pay\Classes\TaxLocation;
use Responsiv\Pay\Contracts\Invoice as InvoiceContract;
use Responsiv\Currency\Models\Currency as CurrencyModel;

/**
 * Invoice Model
 *
 * @property int $id
 * @property string $hash
 * @property string $user_ip
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $phone
 * @property string $company
 * @property string $address_line1
 * @property string $address_line2
 * @property string $city
 * @property string $zip
 * @property string $tax_id_number
 * @property int $total
 * @property int $total_tax
 * @property int $discount
 * @property int $discount_tax
 * @property int $subtotal
 * @property int $tax
 * @property string $taxes
 * @property bool $is_tax_exempt
 * @property bool $prices_include_tax
 * @property bool $is_throwaway
 * @property int $user_id
 * @property int $template_id
 * @property int $payment_method_id
 * @property int $currency_id
 * @property int $related_id
 * @property string $related_type
 * @property int $status_id
 * @property int $state_id
 * @property int $country_id
 * @property \Illuminate\Support\Carbon $sent_at
 * @property \Illuminate\Support\Carbon $due_at
 * @property \Illuminate\Support\Carbon $status_updated_at
 * @property \Illuminate\Support\Carbon $processed_at
 * @property \Illuminate\Support\Carbon $deleted_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon $created_at
 *
 * @package responsiv\pay
 * @author Alexey Bobkov, Samuel Georges
 */
class Invoice extends Model implements InvoiceContract
{
    use \RainLab\Location\Traits\LocationModel;
    use \Responsiv\Pay\Models\Invoice\HasInvoiceContract;
    use \Responsiv\Pay\Models\Invoice\HasModelAttributes;
    use \Responsiv\Pay\Models\Invoice\HasCalculatedAttributes;

    /**
     * @var string table used by the model
     */
    public $table = 'responsiv_pay_invoices';

    /**
     * @var array dates are attributes to convert to an instance of Carbon/DateTime objects.
     */
    protected $dates = [
        'processed_at',
        'status_updated_at',
        'deleted_at',
        'sent_at',
        'due_at'
    ];

    /**
     * @var array jsonable attribute names that are json encoded and decoded from the database
     */
    protected $jsonable = [
        'taxes'
    ];

    /**
     * @var array belongsTo
     */
    public $belongsTo = [
        'status' => InvoiceStatus::class,
        'template' => InvoiceTemplate::class,
        'payment_method' => PaymentMethod::class,
        'user' => \RainLab\User\Models\User::class,
        'currency' => CurrencyModel::class,
    ];

    /**
     * @var array hasMany
     */
    public $hasMany = [
        'items' => [InvoiceItem::class, 'delete' => true],
        'status_log' => [InvoiceStatusLog::class, 'delete' => true],
        'payment_log' => [InvoicePaymentLog::class, 'delete' => true],
    ];

    /**
     * @var array morphTo
     */
    public $morphTo = [
        'related' => []
    ];

    /**
     * makeThrowaway constructor
     */
    public static function makeThrowaway($user = null)
    {
        $invoice = $user === null ? new static : self::makeForUser($user);
        $invoice->is_throwaway = true;

        return $invoice;
    }

    /**
     * makeForUser constructor
     */
    public static function makeForUser($user)
    {
        $invoice = new static;

        $invoice->user = $user;
        $invoice->first_name = $user->first_name;
        $invoice->last_name = $user->last_name;
        $invoice->email = $user->email;
        $invoice->phone = $user->phone;

        return $invoice;
    }

    /**
     * beforeCreate event
     */
    public function beforeCreate()
    {
        $this->generateHash();

        $this->user_ip = Request::getClientIp();

        /**
         * @event responsiv.pay.beforeCreateInvoiceRecord
         * Triggered before a new invoice is created via the admin panel, or programmatically
         *
         * Example usage:
         *
         *     Event::listen('responsiv.pay.beforeCreateInvoiceRecord', function($invoice) {
         *         // Do something with the invoice
         *     });
         *
         */
        Event::fire('responsiv.pay.beforeCreateInvoiceRecord', [$this]);
    }

    /**
     * beforeSave event
     */
    public function beforeSave()
    {
        if (!$this->template_id) {
            $this->template_id = InvoiceTemplate::getDefault()?->id;
        }

        if (!$this->currency_id) {
            $this->currency_id = CurrencyModel::getPrimary()?->id;
        }
    }

    /**
     * beforeUpdate
     */
    public function beforeUpdate()
    {
        /**
         * @event responsiv.pay.beforeUpdateInvoiceRecord
         * Triggered before a new invoice is updated via the admin panel, or programmatically
         *
         * Example usage:
         *
         *     Event::listen('responsiv.pay.beforeUpdateInvoiceRecord', function($invoice) {
         *         // Do something with the invoice
         *     });
         *
         */
        Event::fire('responsiv.pay.beforeUpdateInvoiceRecord', [$this]);
    }

    /**
     * afterCreate event
     */
    public function afterCreate()
    {
        $statusId = InvoiceStatus::getNewStatus()?->getKey();

        $invoiceCopy = static::find($this->getKey());

        InvoiceStatusLog::createRecord($statusId, $invoiceCopy);

        /**
         * @event responsiv.pay.newInvoice
         * Triggered after a new invoice is placed.
         *
         * Use this listener to perform further invoice processing.
         *
         * Example usage:
         *
         *     Event::listen('responsiv.pay.newInvoice', function($invoice) {
         *         // Do something with the invoice
         *     });
         *
         */
        Event::fire('responsiv.pay.newInvoice', [$invoiceCopy]);

        if ($paymentMethod = $this->payment_method) {
            $paymentMethod->getDriverObject()->invoiceAfterCreate($paymentMethod, $this);
        }
    }

    /**
     * scopeApplyRelated scope
     */
    public function scopeApplyRelated($query, $object)
    {
        return $query
            ->where('related_type', get_class($object))
            ->where('related_id', $object->getKey())
        ;
    }

    /**
     * scopeApplyUser scope
     */
    public function scopeApplyUser($query, $user)
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * scopeApplyThrowaway scope
     */
    public function scopeApplyThrowaway($query)
    {
        return $query->where('is_throwaway', 1);
    }

    /**
     * scopeApplyNotThrowaway scope
     */
    public function scopeApplyNotThrowaway($query)
    {
        return $query->where(function($q) {
            $q->where('is_throwaway', 0);
            $q->orWhereNull('is_throwaway');
        });
    }

    /**
     * scopeApplyUnpaid scope
     */
    public function scopeApplyUnpaid($query)
    {
        return $query->whereNull('processed_at');
    }

    //
    // Utils
    //

    /**
     * convertToPermanent converts a temporary/throwaway invoice to a permanent one.
     * @return void
     */
    public function convertToPermanent()
    {
        $this->is_throwaway = false;
        $this->save();
    }

    /**
     * submitManualPayment
     */
    public function submitManualPayment($comment = null)
    {
        InvoicePaymentLog::createManualPayment($this, $comment);

        return $this->markAsPaymentProcessed();
    }

    /**
     * getTaxableAddress returns the address used for calculating tax on this order
     */
    public function getTaxableAddress(): TaxLocation
    {
        $address = new TaxLocation;
        $address->fillFromInvoice($this);
        return $address;
    }

    /**
     * findByInvoiceHash
     */
    public static function findByInvoiceHash($invoiceHash): ?static
    {
        return static::where('hash', $invoiceHash)->first();
    }

    /**
     * generateHash is an internal helper to set generate a unique hash for this invoice.
     */
    protected function generateHash()
    {
        $this->hash = $this->createHash();

        while ($this->newQuery()->where('hash', $this->hash)->count() > 0) {
            $this->hash = $this->createHash();
        }
    }

    /**
     * createHash is an internal helper, create a hash for this invoice.
     */
    protected function createHash(): string
    {
        return md5(uniqid('invoice', microtime()));
    }
}
