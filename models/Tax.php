<?php namespace Responsiv\Pay\Models;

use Model;
use RainLab\Location\Models\State;
use RainLab\Location\Models\Country;
use Responsiv\Pay\Classes\TaxLocation;

/**
 * Tax Model
 */
class Tax extends Model
{
    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_pay_taxes';

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [];

    /**
     * @var array List of attribute names which are json encoded and decoded from the database.
     */
    protected $jsonable = ['rates'];

    /**
     * @var array Object cache of self.
     */
    protected static $cache = [];

    /**
     * @var self Default tax class cache.
     */
    protected static $defaultCache;

    /**
     * @var Responsiv\Pay\Classes\TaxLocation
     */
    protected $locationInfo;

    /**
     * Returns the default locale defined.
     * @return self
     */
    public static function getDefault()
    {
        if (self::$defaultCache !== null) {
            return self::$defaultCache;
        }

        return self::$defaultCache = self::where('is_default', true)->first();
    }

    /**
     * Locate a tax table by its identifier, cached.
     * @param  int $id
     * @return Model
     */
    public static function findById($id)
    {
        if (isset(self::$cache[$id])) {
            return self::$cache[$id];
        }

        return self::$cache[$id] = self::find($id);
    }

    /**
     * Sets the location information used for calculations
     * @param TaxLocation $locationInfo
     * @return void
     */
    public function setLocationInfo(TaxLocation $locationInfo)
    {
        $this->locationInfo = $locationInfo;
    }

    /**
     * Returns total tax on an amount, based on a location.
     * @param  float  $amount
     * @return float
     */
    public function getTotalTax($amount)
    {
        $result = 0;

        $taxes = $this->getTaxRates($amount);

        foreach ($taxes as $tax) {
            $result += $tax->tax_rate * $amount;
        }

        return $result;
    }

    /**
     * Returns tax rates for a specified amount based on location information.
     * @param  int   $amount
     * @param  array $locationInfo
     * @return array
     */
    public function getTaxRates($amount)
    {
        $maxTaxNum = 2;
        $addedTaxes = [];
        $compoundTaxes = [];
        $ignoredPriorities = [];

        /*
         * Loop each rate and compound if necessary.
         */
        for ($index = 1; $index <= $maxTaxNum; $index++) {

            $taxInfo = $this->getRate($ignoredPriorities);
            if (!$taxInfo) {
                break;
            }

            if (!$taxInfo->compound) {
                $addedTaxes[] = $taxInfo;
            }
            else {
                $compoundTaxes[] = $taxInfo;
            }

            $ignoredPriorities[] = $taxInfo->priority;
        }

        $addedResult = $amount;
        $result = [];

        foreach ($addedTaxes as $addedTax) {
            $taxInfo = [];
            $taxInfo['name'] = $addedTax->name;
            $taxInfo['tax_rate'] = $addedTax->rate / 100;
            $addedResult += $taxInfo['rate'] = round($amount * ($addedTax->rate / 100), 2);
            $taxInfo['total'] = $taxInfo['rate'];
            $taxInfo['added_tax'] = true;
            $taxInfo['compound_tax'] = false;
            $result[] = (object) $taxInfo;
        }

        foreach ($compoundTaxes as $compoundTax) {
            $taxInfo = [];
            $taxInfo['name'] = $compoundTax->name;
            $taxInfo['tax_rate'] = $compoundTax->rate / 100;
            $taxInfo['rate'] = round($addedResult * ($compoundTax->rate / 100), 2);
            $taxInfo['total'] = $taxInfo['rate'];
            $taxInfo['compound_tax'] = true;
            $taxInfo['added_tax'] = false;
            $result[] = (object) $taxInfo;
        }

        return $result;
    }

    /**
     * Returns rate information for a given location, optionally ignoring by priority.
     * @param  array $ignoredPriorities
     * @return object
     */
    protected function getRate($ignoredPriorities = [])
    {
        $location = $this->locationInfo ?: TaxLocation::makeDefault();

        if (!$country = $location->getCountryModel()) {
            return null;
        }

        $state = $location->getStateModel();

        $countryCode = $country->code;
        $stateCode = $state ? mb_strtoupper($state->code) : '*';
        $zipCode = $location->getZipCode();
        $cityCode = $location->getCityCode();

        $rate = null;
        foreach ($this->rates as $row) {

            $taxPriority = isset($row['priority']) ? $row['priority'] : 1;
            if (in_array($taxPriority, $ignoredPriorities)) {
                continue;
            }

            if ($row['country'] != $countryCode && $row['country'] != '*') {
                continue;
            }

            if (mb_strtoupper($row['state']) != $stateCode && $row['state'] != '*') {
                continue;
            }

            $rowZip = isset($row['zip']) && strlen($row['zip']) 
                ? str_replace(' ', '', $row['zip'])
                : '*';

            if ($rowZip != $zipCode && $rowZip != '*') {
                continue;
            }

            $rowCity = isset($row['city']) && strlen($row['city'])
                ? str_replace('-', '', str_replace(' ', '', mb_strtoupper($row['city'])))
                : '*';

            if ($rowCity != $cityCode && $rowCity != '*') {
                continue;
            }

            $compound = isset($row['compound']) ? $row['compound'] : 0;

            if (preg_match('/^[0-9]+$/', $compound)) {
                $compound = (int) $compound;
            }
            else {
                $compound = ($compound == 'Y' || $compound == 'YES');
            }

            $rateObj = [
                'rate'     => $row['rate'],
                'priority' => $taxPriority,
                'name'     => isset($row['tax_name']) ? $row['tax_name'] : 'TAX',
                'compound' => $compound
            ];

            $rate = (object) $rateObj;
            break;
        }

        return $rate;
    }

    //
    // Invoice specific
    //

    /**
     * Calculates taxes for invoice line items based on location information.
     * @param  Invoice $invoice
     * @param  array $items
     * @return array
     */
    public static function calculateInvoiceTaxes($invoice, $items)
    {
        $result = (object)[
            'tax_total'  => 0,
            'taxes'      => [],
            'item_taxes' => []
        ];

        $taxes = [];
        $itemTaxes = [];
        $taxTotal = 0;

        foreach ($items as $itemIndex => $item) {
            $taxClass = static::findById($item->tax_class_id);
            if (!$taxClass) {
                continue;
            }

            $taxClass->setLocationInfo($invoice->getLocationInfo());

            $itemDiscount = $item->price * $item->discount;
            $itemPrice = $item->price - $itemDiscount;
            $itemTaxes[$itemIndex] = $_itemTaxes = $taxClass->getTaxRates($itemPrice);

            foreach ($_itemTaxes as $tax) {

                $key = $tax->name.'.'.$taxClass->id;
                if (!array_key_exists($key, $taxes)) {

                    $effectiveRate = $tax->tax_rate;

                    if ($tax->compound_tax) {
                        $addedTax = self::findAddedTax($_itemTaxes);
                        if ($addedTax) {
                            $effectiveRate = $tax->tax_rate * (1 + $addedTax->tax_rate);
                        }
                    }

                    $taxes[$key] = [
                        'total'          => 0,
                        'rate'           => $tax->rate,
                        'effective_rate' => $effectiveRate,
                        'name'           => $tax->name,
                    ];
                }

                $itemTaxValue = $itemPrice * $item->quantity;
                $taxes[$key]['total'] += $itemTaxValue;
            }
        }

        $compoundTaxes = [];

        foreach ($taxes as $taxTotalInfo) {
            if (!array_key_exists($taxTotalInfo['name'], $compoundTaxes)) {
                $taxData = ['name' => $taxTotalInfo['name'], 'total' => 0];
                $compoundTaxes[$taxTotalInfo['name']] = (object) $taxData;
            }

            $taxValue = $taxTotalInfo['total'] * $taxTotalInfo['effective_rate'];
            $compoundTaxes[$taxTotalInfo['name']]->total += $taxValue;

            $taxTotal += $taxValue;
        }

        foreach ($compoundTaxes as $name => &$taxData) {
            $taxData->total = round($taxData->total, 2);
        }

        $result->tax_total = round($taxTotal, 2);
        $result->taxes = $compoundTaxes;
        $result->item_taxes = $itemTaxes;

        return $result;
    }

    /**
     * Internal helper, find the nearest added tax item in the collection.
     * @param  array $taxList
     * @return mixed
     */
    protected static function findAddedTax($taxList)
    {
        foreach ($taxList as $tax) {
            if ($tax->added_tax) {
                return $tax;
            }
        }

        return null;
    }

    //
    // Options
    //

    public function getDataTableOptions($attribute, $field, $data)
    {
        if ($field == 'country') {
            return $this->getCountryList(array_get($data, $field));
        }

        if ($field == 'state') {
            return $this->getStateList(array_get($data, 'country'), array_get($data, $field));
        }
    }

    protected function getCountryList($term)
    {
        $result = ['*' => 'responsiv.pay::lang.options.any_country'];

        // The search term functionality is disabled as it's not supported
        // by the Table widget's drop-down processor -ab 2015-01-03
        //$countries = Country::searchWhere($term, ['name', 'code'])

        $countries = Country::isEnabled()->lists('name', 'code');

        foreach ($countries as $code => $name) {
            $result[$code] = $code .' - ' . $name;
        }

        return $result;
    }

    protected function getStateList($countryCode, $term)
    {
        $result = ['*' => 'responsiv.pay::lang.options.any_state'];

        if (!$countryCode || $countryCode == '*') {
            return $result;
        }

        // The search term functionality is disabled as it's not supported
        // by the Table widget's drop-down processor -ab 2015-01-03
        // $states = State::searchWhere($term, ['name', 'code']);

        if ($countryCode) {
            $states = State::whereHas('country', function($query) use ($countryCode) {
                $query->where('code', $countryCode);
            });
        }

        $states = $states->limit(10)->lists('name', 'code');

        foreach ($states as $code => $name) {
            $result[$code] = $code .' - ' . $name;
        }

        return $result;
    }
}
