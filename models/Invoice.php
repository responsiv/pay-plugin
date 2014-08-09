<?php namespace Responsiv\Pay\Models;

use Model;
use Request;
use DB as Db;
use Carbon\Carbon;
use RainLab\User\Models\Settings as UserSettings;
use Responsiv\Pay\Models\Settings as InvoiceSettings;
use RainLab\User\Models\State;
use RainLab\User\Models\Country;
use Responsiv\Payd\InvoiceInterface;
use Exception;

/**
 * Invoice Model
 */
class Invoice extends Model implements InvoiceInterface
{
    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_pay_invoices';

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array List of datetime attributes to convert to an instance of Carbon/DateTime objects.
     */
    public $dates = ['processed_at', 'status_updated_at', 'deleted_at', 'sent_at', 'due_at'];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'user'         => ['RainLab\User\Models\User'],
        'status'       => ['Responsiv\Pay\Models\InvoiceStatus'],
        'template'     => ['Responsiv\Pay\Models\InvoiceTemplate'],
        'payment_type' => ['Responsiv\Pay\Models\Type'],
        'country'      => ['RainLab\User\Models\Country'],
        'state'        => ['RainLab\User\Models\State'],
    ];

    public $hasMany = [
        'items'      => ['Responsiv\Pay\Models\InvoiceItem'],
        'status_log' => ['Responsiv\Pay\Models\InvoiceStatusLog'],
        'type_log'   => ['Responsiv\Pay\Models\InvoiceTypeLog'],
    ];

    public function afterFetch()
    {
        if (!$this->payment_type_id)
            $this->payment_type = Type::getDefault($this->country_id);
    }

    public function beforeSave()
    {
        $this->setDefaults();
        $this->calculateTotals();
    }

    public function beforeCreate()
    {
        $this->generateHash();

        if (!$this->sent_at)
            $this->sent_at = Carbon::now();

        $this->user_ip = Request::getClientIp();
    }

    public function afterCreate()
    {
        InvoiceStatusLog::createRecord(InvoiceStatus::getStatusDraft(), $this);
    }

    public function getCountryOptions()
    {
        return Country::getNameList();
    }

    public function getStateOptions()
    {
        return State::getNameList($this->country_id);
    }

    public function setDefaults()
    {
        if (!$this->country_id)
            $this->country_id = UserSettings::get('default_country', 1);

        if (!$this->state_id)
            $this->state_id = UserSettings::get('default_state', 1);

        if (!$this->template_id)
            $this->template_id = InvoiceSettings::get('default_invoice_template', 1);
    }

    /**
     * Calculate totals from invoice items
     * @param  Model $items
     * @return float
     */
    public function calculateTotals($items = null)
    {
        if (!$items)
            $items = $this->items;

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
        $taxInfo = Tax::calculateTaxes($items, $this->getLocationInfo());
        $this->setSalesTaxes($taxInfo->taxes);
        $tax = $taxInfo->tax_total;

        /*
         * Grand total
         */
        $this->discount = $discount;
        $this->subtotal = $subtotal;
        $this->tax = $tax;
        $this->total = ($subtotal - $discount) + $tax;

        return $this->total;
    }

    /**
     * Build a helper object for this invoice's location, used by tax calcuation.
     * @return object
     */
    public function getLocationInfo()
    {
        $this->setDefaults();
        $location = [
            'street_addr' => $this->street_addr,
            'city'        => $this->city,
            'zip'         => $this->zip,
            'state_id'    => $this->state_id,
            'country_id'  => $this->country_id,
        ];

        return (object) $location;
    }

    /**
     * Sets the tax data for the invoice
     * @param array $taxes
     * @return void
     */
    public function setSalesTaxes($taxes)
    {
        if (!is_array($taxes))
            $taxes = [];

        $taxesToSave = $taxes;

        foreach ($taxesToSave as $taxName => &$taxInfo) {
            $taxInfo->total = round($taxInfo->total, 2);
        }

        $this->tax_data = serialize($taxesToSave);
    }

    /**
     * Lists tax breakdown for this invoice.
     * @return array
     */
    public function listSalesTaxes()
    {
        $result = [];

        if (!strlen($this->tax_data))
            return $result;

        try {
            $taxes = unserialize($this->tax_data);
            foreach ($taxes as $taxName => $taxInfo) {
                if ($taxInfo->total <= 0)
                    continue;

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
        if (!$name)
            $name = $defaultName;

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
        return $this->id;
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
        return InvoiceSettings::getDetaultPaymentPage([
            'id' => $this->id,
            'hash' => $this->hash,
        ]);
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
            'state_id'    => $this->state->code,
            'country_id'  => $this->country->code,
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
        $details [
            'total'    => $this->total,
            'subtotal' => $this->subtotal,
            'tax'      => $this->tax,
            'currency' => InvoiceSettings::get('currency_code', 'USD'),
        ];

        return $details;
    }

    /**
     * {@inheritDoc}
     */
    public function isPaymentProcessed($force = false)
    {
        if ($force)
            return $this->where('id', $this->id)->pluck('processed_at');

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
            $this->where('id', $this->id)->update(['processed_at' => $now]);

            $this->save();

            InvoiceStatusLog::createRecord($this->payment_type->invoice_status, $this);
        }

        return !$isPaid;
    }

    /**
     * {@inheritDoc}
     */
    public function getPaymentMethod()
    {
        return $this->payment_type;
    }

    /**
     * {@inheritDoc}
     */
    public function logPaymentAttempt(
        $message,
        $isSuccess,
        $requestArray,
        $responseArray,
        $responseText,
        $ccvResponseCode = null,
        $ccvResponseText = null,
        $avsResponseCode = null,
        $avsResponseText = null
    )
    {

        $info = $this->getPaymentMethod()->gatewayDetails();

        $record = new InvoiceTypeLog;
        $record->message = $message;
        $record->invoice_id = $this->id;
        $record->payment_type_name = $info['name'];
        $record->is_success = $isSuccess;

        $record->raw_response = $responseText;
        $record->request_data = $requestArray;
        $record->response_data = $responseArray;

        $record->ccv_response_code = $ccvResponseCode;
        $record->ccv_response_text = $ccvResponseText;
        $record->avs_response_code = $avsResponseCode;
        $record->avs_response_text = $avsResponseText;

        $record->save();
    }

}
