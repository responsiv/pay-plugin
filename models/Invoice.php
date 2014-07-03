<?php namespace Responsiv\Pay\Models;

use Model;
use Request;
use DB as Db;
use Carbon\Carbon;
use RainLab\User\Models\Settings as UserSettings;
use Responsiv\Pay\Models\Settings as InvoiceSettings;
use RainLab\User\Models\State;
use RainLab\User\Models\Country;
use Exception;

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
    protected $guarded = [];

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
        'user'         => ['RainLab\User\Models\User'],
        'status'       => ['Responsiv\Pay\Models\InvoiceStatus'],
        'template'     => ['Responsiv\Pay\Models\InvoiceTemplate'],
        'payment_type' => ['Responsiv\Pay\Models\Type'],
        'country'      => ['RainLab\User\Models\Country'],
        'state'        => ['RainLab\User\Models\State'],
    ];

    public $hasMany = [
        'items' => ['Responsiv\Pay\Models\InvoiceItem'],
        'status_log' => ['Responsiv\Pay\Models\InvoiceStatusLog'],
    ];

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

    public function isPaymentProcessed($force = false)
    {
        if ($force)
            return $this->where('id', $this->id)->pluck('processed_at');

        return $this->processed_at;
    }

    /**
     * Flags this invoice as having payment processed
     * @return boolean
     */
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

}