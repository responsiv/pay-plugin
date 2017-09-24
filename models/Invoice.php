<?php namespace Responsiv\Pay\Models;

use Db;
use Model;
use Event;
use Request;
use Carbon\Carbon;
use Cms\Classes\Controller;
use Responsiv\Currency\Facades\Currency as CurrencyHelper;
use Responsiv\Pay\Classes\TaxLocation;
use Responsiv\Pay\Interfaces\Invoice as InvoiceInterface;
use Responsiv\Pay\Models\PaymentMethod as TypeModel;
use RainLab\Location\Models\State;
use RainLab\Location\Models\Country;
use Exception;

/**
 * Invoice Model
 */
class Invoice extends Model implements InvoiceInterface
{
    use \Responsiv\Pay\Traits\UrlMaker;
    use \October\Rain\Database\Traits\Purgeable;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_pay_invoices';

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array Purgeable fields
     */
    protected $purgeable = ['url'];

    /**
     * @var array List of datetime attributes to convert to an instance of Carbon/DateTime objects.
     */
    public $dates = ['processed_at', 'status_updated_at', 'deleted_at', 'sent_at', 'due_at'];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'user'           => \RainLab\User\Models\User::class,
        'status'         => InvoiceStatus::class,
        'template'       => InvoiceTemplate::class,
        'payment_method' => PaymentMethod::class,
        'country'        => \RainLab\Location\Models\Country::class,
        'state'          => \RainLab\Location\Models\State::class,
    ];

    public $hasMany = [
        'items'       => [InvoiceItem::class, 'delete' => true],
        'status_log'  => [InvoiceStatusLog::class, 'delete' => true],
        'payment_log' => [InvoiceLog::class, 'delete' => true],
    ];

    public $morphTo = [
        'related' => []
    ];

    /**
     * @var string The component to use for generating URLs.
     */
    protected $urlComponentName = 'invoice';

    /**
     * Returns an array of values to use in URL generation.
     * @return @array
     */
    public function getUrlParams()
    {
        return [
            'id' => $this->id,
            'hash' => $this->hash,
        ];
    }

    //
    // Constructors
    //

    public static function makeThrowaway($user = null)
    {
        $invoice = $user === null ? new static : self::makeForUser($user);

        $invoice->is_throwaway = true;

        return $invoice;
    }

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

    //
    // Events
    //

    public function afterFetch()
    {
        if (!$this->payment_method_id) {
            $this->payment_method = TypeModel::getDefault($this->country_id);
        }
    }

    public function beforeSave()
    {
        $this->setDefaults();
        $this->calculateTotals();
    }

    public function beforeCreate()
    {
        $this->generateHash();

        if (!$this->sent_at) {
            $this->sent_at = Carbon::now();
        }

        $this->user_ip = Request::getClientIp();
    }

    public function afterCreate()
    {
        InvoiceStatusLog::createRecord(InvoiceStatus::STATUS_DRAFT, $this);

        Event::fire('responsiv.pay.invoiceNew', [$this]);
    }

    //
    // Scopes
    //

    public function scopeApplyRelated($query, $object)
    {
        return $query
            ->where('related_type', get_class($object))
            ->where('related_id', $object->getKey())
        ;
    }

    public function scopeApplyUser($query, $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeApplyThrowaway($query)
    {
        return $query->where('is_throwaway', 1);
    }

    public function scopeApplyNotThrowaway($query)
    {
        return $query->where(function($q) {
            $q->where('is_throwaway', 0);
            $q->orWhereNull('is_throwaway');
        });
    }

    public function scopeApplyUnpaid($query)
    {
        return $query->whereNull('processed_at');
    }

    //
    // Options
    //

    public function getCountryOptions()
    {
        return Country::getNameList();
    }

    public function getStateOptions()
    {
        return State::getNameList($this->country_id);
    }

    //
    // Accessors
    //

    public function isPaid()
    {
        return $this->isPaymentProcessed();
    }

    public function isPastDueDate()
    {
        if (!$this->due_at) {
            return true;
        }

        return $this->due_at->isPast() || $this->due_at->isToday();
    }

    public function getStatusCodeAttribute()
    {
        return $this->status ? $this->status->code : null;
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
            $discount += $item->discount;
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
     * Internal helper, and set generate a unique hash for this invoice.
     * @return string
     */
    protected function generateHash()
    {
        $this->hash = $this->createHash();
        while ($this->newQuery()->where('hash', $this->hash)->count() > 0) {
            $this->hash = $this->createHash();
        }
    }

    /**
     * Internal helper, create a hash for this invoice.
     * @return string
     */
    protected function createHash()
    {
        return md5(uniqid('invoice', microtime()));
    }

    //
    // InvoiceInterface obligations
    //

    /**
     * {@inheritDoc}
     */
    public function getUniqueId()
    {
        return Settings::get('invoice_prefix') . $this->id;
    }

    /**
     * {@inheritDoc}
     */
    public function findByUniqueId($id = null)
    {
        return static::find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function getUniqueHash()
    {
        return $this->hash;
    }

    /**
     * {@inheritDoc}
     */
    public function findByUniqueHash($hash = null)
    {
        return static::whereHash($hash)->first();
    }

    /**
     * {@inheritDoc}
     */
    public function getReceiptUrl()
    {
        if ($this->return_page) {
            $controller = Controller::getController() ?: new Controller;
            return $controller->pageUrl($this->return_page, $this->getUrlParams());
        }

        return $this->getUrlAttribute();
    }

    /**
     * {@inheritDoc}
     */
    public function getCustomerDetails()
    {
        $this->setDefaults();
        $details = [
            'first_name'  => $this->first_name,
            'last_name'   => $this->last_name,
            'email'       => $this->email,
            'phone'       => $this->phone,
            'street_addr' => $this->street_addr,
            'city'        => $this->city,
            'zip'         => $this->zip,
            'state_id'    => $this->state ? $this->state->code : null,
            'state'       => $this->state ? $this->state->name : null,
            'country_id'  => $this->country ? $this->country->code : null,
            'country'     => $this->country ? $this->country->name : null
        ];

        return $details;
    }

    /**
     * {@inheritDoc}
     */
    public function getLineItemDetails()
    {
        $details = [];

        foreach ($this->items as $item) {
            $details[] = [
                'description' => $item->description,
                'quantity'    => $item->quantity,
                'price'       => $item->price,
                'total'       => $item->total,
            ];
        }

        return $details;
    }

    /**
     * {@inheritDoc}
     */
    public function getTotalDetails()
    {
        $details = [
            'total'    => $this->total,
            'subtotal' => $this->subtotal,
            'tax'      => $this->tax,
            'currency' => CurrencyHelper::primaryCode(),
        ];

        return $details;
    }

    /**
     * {@inheritDoc}
     */
    public function isPaymentProcessed($force = false)
    {
        if ($force) {
            return $this->where('id', $this->id)->value('processed_at');
        }

        return $this->processed_at;
    }

    /**
     * {@inheritDoc}
     */
    public function markAsPaymentProcessed()
    {
        if (!$isPaid = $this->isPaymentProcessed(true)) {
            $now = $this->processed_at = Carbon::now();

            // Instant update here in case a simultaneous request causes invalid data
            $this->newQuery()->where('id', $this->id)->update(['processed_at' => $now]);

            Event::fire('responsiv.pay.invoicePaid', [$this]);

            // Never allow a paid invoice to be thrown away
            $this->is_throwaway = false;

            $this->save();
        }

        return !$isPaid;
    }

    /**
     * {@inheritDoc}
     */
    public function getPaymentMethod()
    {
        return $this->payment_method;
    }

    /**
     * {@inheritDoc}
     */
    public function logPaymentAttempt(
        $message,
        $isSuccess,
        $requestArray,
        $responseArray,
        $responseText
    ) {
        if ($payMethod = $this->getPaymentMethod()) {
            $info = $payMethod->gatewayDetails();
            $methodName = $info['name'];
        }
        else {
            $methodName = 'Unspecified';
        }

        $options = [
            'isSuccess' => $isSuccess,
            'methodName' => $methodName,
            'requestArray' => $requestArray,
            'responseArray' => $responseArray,
            'responseText' => $responseText
        ];

        InvoiceLog::createRecord($this, $message, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function updateInvoiceStatus($statusCode)
    {
        InvoiceStatusLog::createRecord($statusCode, $this);
    }
}
