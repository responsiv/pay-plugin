<?php namespace Responsiv\Pay\Models;

use Event;
use Model;
use Request;
use Exception;
use Carbon\Carbon;
use RainLab\Location\Models\State;
use RainLab\Location\Models\Country;
use Responsiv\Pay\Classes\TaxLocation;
use Responsiv\Currency\Models\Currency;
use Responsiv\Pay\Models\PaymentMethod as TypeModel;
use Responsiv\Pay\Contracts\Invoice as InvoiceContract;

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
 * @property string $street_addr
 * @property string $city
 * @property string $zip
 * @property string $vat_id
 * @property int $total
 * @property int $subtotal
 * @property int $discount
 * @property int $tax
 * @property int $tax_discount
 * @property bool $is_tax_exempt
 * @property string $currency
 * @property string $tax_data
 * @property string $return_page
 * @property bool $is_throwaway
 * @property int $user_id
 * @property int $template_id
 * @property int $payment_method_id
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
     * @var array belongsTo
     */
    public $belongsTo = [
        'user' => \RainLab\User\Models\User::class,
        'status' => InvoiceStatus::class,
        'template' => InvoiceTemplate::class,
        'payment_method' => PaymentMethod::class,
    ];

    /**
     * @var array hasMany
     */
    public $hasMany = [
        'items' => [InvoiceItem::class, 'delete' => true],
        'status_log' => [InvoiceStatusLog::class, 'delete' => true],
        'payment_log' => [InvoiceLog::class, 'delete' => true],
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
        $invoice->first_name = $user->name;
        $invoice->last_name = $user->surname;
        $invoice->email = $user->email;
        $invoice->phone = $user->phone;

        return $invoice;
    }

    /**
     * getCurrencyOptions options
     */
    public function getCurrencyOptions()
    {
        $emptyOption = [
            '' => "Default currency"
        ];

        return $emptyOption + Currency::listAvailable();
    }

    /**
     * beforeCreate event
     */
    public function beforeCreate()
    {
        $this->generateHash();

        if (!$this->sent_at) {
            $this->sent_at = Carbon::now();
        }

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
        $this->setDefaults();
        $this->calculateTotals();
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
        InvoiceStatusLog::createRecord(InvoiceStatus::STATUS_DRAFT, $this);

        $invoiceCopy = static::find($this->getKey());

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
     * Converts a throwaway invoice to a permanent one.
     * @return void
     */
    public function convertToPermanent()
    {
        $this->is_throwaway = false;
        $this->save();
    }

    public function submitManualPayment($comment = null)
    {
        if ($comment) {
            InvoiceLog::createManualPayment($this, $comment);
        }

        if ($this->payment_method && $this->payment_method->invoice_status) {
            $this->updateInvoiceStatus($this->payment_method->invoice_status);
        }
        else {
            $this->updateInvoiceStatus(InvoiceStatus::STATUS_PAID);
        }

        $this->markAsPaymentProcessed();
    }

    public function setDefaults()
    {
        if (!$this->country_id) {
            $this->country = Country::getDefault();
            $this->state = State::getDefault();
        }

        if (!$this->template_id) {
            $this->template_id = InvoiceTemplate::value('id');
        }
    }

    /**
     * Useful to recalculate the total for this invoice and items.
     * @return void
     */
    public function touchTotals()
    {
        $this->touch();

        $this->items->each(function($item) {
            $item->touch();
        });
    }

    /**
     * Calculate totals from invoice items
     * @param  Model $items
     * @return float
     */
    public function calculateTotals($items = null)
    {
        if (!$items) {
            $items = $this->items()->withDeferred($this->sessionKey)->get();
        }

        /*
         * Discount and subtotal
         */
        $discount = 0;
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item->subtotal;
            $discount += $item->discount * $item->price;
        }

        /*
         * Calculate tax
         */
        $taxInfo = Tax::calculateInvoiceTaxes($this, $items);
        $this->setSalesTaxes($taxInfo->taxes);
        $tax = $taxInfo->tax_total;

        /*
         * Grand total
         */
        $this->discount = $discount;
        $this->subtotal = $subtotal;
        $this->tax = $tax;
        $this->total = $subtotal + $tax;

        return $this->total;
    }

    /**
     * Build a helper object for this invoice's location, used by tax calcuation.
     * @return object
     */
    public function getLocationInfo()
    {
        $this->setDefaults();

        return TaxLocation::makeFromObject($this);
    }

    /**
     * Sets the tax data for the invoice
     * @param array $taxes
     * @return void
     */
    public function setSalesTaxes($taxes)
    {
        if (!is_array($taxes)) {
            $taxes = [];
        }

        $taxesToSave = $taxes;

        foreach ($taxesToSave as $taxName => &$taxInfo) {
            $taxInfo->total = round($taxInfo->total, 2);
        }

        $this->tax_data = json_encode($taxesToSave);
    }

    /**
     * Lists tax breakdown for this invoice.
     * @return array
     */
    public function listSalesTaxes()
    {
        $result = [];

        if (!strlen($this->tax_data)) {
            return $result;
        }

        try {
            $taxes = json_decode($this->tax_data);
            foreach ($taxes as $taxName => $taxInfo) {
                if ($taxInfo->total <= 0) {
                    continue;
                }

                $result = $this->addTaxItem($result, $taxName, $taxInfo->total, 0, 'Sales tax');
            }
        }
        catch (Exception $ex) {
            return $result;
        }

        return $result;
    }

    /**
     * Internal method, adds a tax item to the list of taxes.
     * @param array  $list
     * @param string $name
     * @param float  $amount
     * @param float  $discount
     * @param string $defaultName
     * @return array
     */
    protected function addTaxItem($list, $name, $amount, $discount, $defaultName = 'Tax')
    {
        if (!$name) {
            $name = $defaultName;
        }

        if (!array_key_exists($name, $list)) {
            $taxInfo = [
                'name'     => $name,
                'amount'   => 0,
                'discount' => 0,
                'total'    => 0
            ];
            $list[$name] = (object) $taxInfo;
        }

        $list[$name]->amount += $amount;
        $list[$name]->discount += $discount;
        $list[$name]->total += ($amount - $discount);
        return $list;
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
