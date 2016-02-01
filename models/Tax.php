<?php namespace Responsiv\Pay\Models;

use Model;
use RainLab\Location\Models\State;
use RainLab\Location\Models\Country;

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
        $result = ['*' => '* - Any country'];

        // The search term functionality is disabled as it's not supported
        // by the Table widget's drop-down processor -ab 2015-01-03
        //$countries = Country::searchWhere($term, ['name', 'code'])

        $countries = Country::limit(10)->lists('name', 'code');

        foreach ($countries as $code => $name) {
            $result[$code] = $code .' - ' . $name;
        }

        return $result;
    }

    protected function getStateList($countryCode, $term)
    {
        $result = ['*' => '* - Any state'];

        if (!$countryCode || $countryCode == '*')
            return $result;

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
        if (isset(self::$cache[$id]))
            return self::$cache[$id];

        return self::$cache[$id] = self::find($id);
    }

    /**
     * Calculates taxes for invoice line items based on location information.
     * @param  array $items
     * @param  array $locationInfo
     * @return array
     */
    public static function calculateTaxes($items, $locationInfo)
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
            if (!$taxClass)
                continue;

            $itemDiscount = $item->price * $item->discount;
            $itemPrice = $item->price - $itemDiscount;
            $itemTaxes[$itemIndex] = $_itemTaxes = $taxClass->getTaxRates($itemPrice, $locationInfo);

            foreach ($_itemTaxes as $tax) {

                $key = $tax->name.'.'.$taxClass->id;
                if (!array_key_exists($key, $taxes)) {

                    $effectiveRate = $tax->tax_rate;

                    if ($tax->compound_tax) {
                        $addedTax = self::findAddedTax($_itemTaxes);
                        if ($addedTax)
                            $effectiveRate = $tax->tax_rate * (1 + $addedTax->tax_rate);
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
            if ($tax->added_tax)
                return $tax;
        }

        return null;
    }

    /**
     * Returns total tax value for a specific tax class and amount based on location.
     * @param  self   $taxClassId
     * @param  float  $amount
     * @param  array  $locationInfo
     * @return float
     */
    public static function getTotalTax($taxClassId, $amount, $locationInfo)
    {
        $result = 0;

        if (!isset(self::$cache[$taxClassId]))
            self::$cache[$taxClassId] = self::find($taxClassId);

        if (!$taxClass = self::$cache[$taxClassId])
            return $result;

        $taxes = $taxClass->getTaxRates($amount, $locationInfo);

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
    public function getTaxRates($amount, $locationInfo)
    {
        $maxTaxNum = 2;
        $addedTaxes = [];
        $compoundTaxes = [];
        $ignoredPriorities = [];

        /*
         * Loop each rate and compound if necessary.
         */
        for ($index = 1; $index <= $maxTaxNum; $index++) {

            $taxInfo = $this->getRate($locationInfo, $ignoredPriorities);
            if (!$taxInfo)
                break;

            if (!$taxInfo->compound)
                $addedTaxes[] = $taxInfo;
            else
                $compoundTaxes[] = $taxInfo;

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
     * @param  array $locationInfo
     * @param  array $ignoredPriorities
     * @return object
     */
    protected function getRate($locationInfo, $ignoredPriorities = [])
    {
        $country = Country::find($locationInfo->country_id);
        if (!$country)
            return null;

        $state = null;
        if (strlen($locationInfo->state_id))
            $state = State::find($locationInfo->state_id);

        $countryCode = $country->code;
        $stateCode = $state ? mb_strtoupper($state->code) : '*';

        $zipCode = str_replace(' ', '', trim(strtoupper($locationInfo->zip)));
        if (!strlen($zipCode))
            $zipCode = '*';

        $city = str_replace('-', '', str_replace(' ', '', trim(mb_strtoupper($locationInfo->city))));
        if (!strlen($city))
            $city = '*';

        $rate = null;
        foreach ($this->rates as $row) {

            $taxPriority = isset($row['priority']) ? $row['priority'] : 1;
            if (in_array($taxPriority, $ignoredPriorities))
                continue;

            if ($row['country'] != $countryCode && $row['country'] != '*')
                continue;

            if (mb_strtoupper($row['state']) != $stateCode && $row['state'] != '*')
                continue;

            $rowZip = isset($row['zip']) && strlen($row['zip']) 
                ? str_replace(' ', '', $row['zip'])
                : '*';

            if ($rowZip != $zipCode && $rowZip != '*')
                continue;

            $rowCity = isset($row['city']) && strlen($row['city'])
                ? str_replace('-', '', str_replace(' ', '', mb_strtoupper($row['city'])))
                : '*';

            if ($rowCity != $city && $rowCity != '*')
                continue;

            $compound = isset($row['compound']) ? $row['compound'] : 0;

            if (preg_match('/^[0-9]+$/', $compound))
                $compound = (int) $compound;
            else
                $compound = ($compound == 'Y' || $compound == 'YES');

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

}