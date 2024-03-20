<?php namespace Responsiv\Pay\Models;

use App;
use Auth;
use Model;
use RainLab\Location\Models\State;
use RainLab\Location\Models\Country;
use Responsiv\Pay\Models\InvoiceItem;
use Responsiv\Pay\Classes\TaxItem;

/**
 * Tax Model
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $description
 * @property array $rates
 * @property bool $is_default
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon $created_at
 *
 * @package responsiv\pay
 * @author Alexey Bobkov, Samuel Georges
 */
class Tax extends Model
{
    use \Responsiv\Pay\Models\Tax\HasGlobalContext;
    use \System\Traits\KeyCodeModel;
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Defaultable;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_pay_taxes';

    /**
     * @var array jsonable attribute names that are json encoded and decoded from the database
     */
    protected $jsonable = [
        'rates'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'name' => 'required',
    ];

    /**
     * @var int roundPrecision
     */
    protected $roundPrecision = 2;

    /**
     * getTotalTax adds tax to an untaxed amount. Return value is the tax amount
     * to add to the final price.
     */
    public function getTotalTax($amount)
    {
        $result = 0;

        $taxes = $this->getTaxRates($amount);

        foreach ($taxes as $tax) {
            $result += ($tax['taxRate'] * $amount) / (1 + $tax['taxRate']);
        }

        return round($result, $this->roundPrecision);
    }

    /**
     * getTotalUntax removes the tax from an already taxed amount. Return value
     * is the tax amount to remove from the final price.
     */
    public function getTotalUntax($amount)
    {
        $result = 0;

        $taxes = $this->getTaxRates($amount);

        foreach ($taxes as $tax) {
            $result += ($tax['taxRate'] * $amount) / (1 + $tax['taxRate']);
        }

        return round($result, $this->roundPrecision);
    }

    /**
     * Returns tax rates for a specified amount based on location information.
     * @param  int   $amount
     * @return array
     */
    public function getTaxRates($amount, array $options = [])
    {
        if (static::isTaxExemptContext()) {
            return [];
        }

        extract(array_merge([
            'pricesIncludeTax' => null,
        ], $options));

        if ($pricesIncludeTax === null) {
            $pricesIncludeTax = static::doPricesIncludeTax();
        }

        $maxTaxNum = 2;
        $addedTaxes = [];
        $compoundTaxes = [];
        $ignoredPriorities = [];

        // Loop each rate and compound if necessary
        for ($index = 1; $index <= $maxTaxNum; $index++) {
            $taxInfo = $this->getRate(['ignoredPriorities' => $ignoredPriorities] + $options);
            if (!$taxInfo) {
                break;
            }

            if ($taxInfo['compound']) {
                $compoundTaxes[] = $taxInfo;
            }
            else {
                $addedTaxes[] = $taxInfo;
            }

            $ignoredPriorities[] = $taxInfo['priority'];
        }

        $addedResult = $amount;
        $result = [];

        foreach ($addedTaxes as $addedTax) {
            $taxInfo = [];
            $taxInfo['name'] = $addedTax['name'];
            $taxInfo['taxRate'] = $addedTax['rate'] / 100;
            $taxInfo['addedTax'] = true;
            $taxInfo['compoundTax'] = false;
            $taxInfo['rate'] = $pricesIncludeTax
                ? round(($amount * $taxInfo['taxRate']) / (1 + $taxInfo['taxRate']), $this->roundPrecision)
                : round($amount * $taxInfo['taxRate'], $this->roundPrecision);
            $taxInfo['total'] = $taxInfo['rate'];
            $result[] = $taxInfo;
            $addedResult += $taxInfo['rate'];
        }

        foreach ($compoundTaxes as $compoundTax) {
            $taxInfo = [];
            $taxInfo['name'] = $compoundTax->name;
            $taxInfo['taxRate'] = $compoundTax->rate / 100;
            $taxInfo['compoundTax'] = true;
            $taxInfo['addedTax'] = false;
            $taxInfo['rate'] = $pricesIncludeTax
                ? round(($addedResult * $taxInfo['taxRate']) / (1 + $taxInfo['taxRate']), $this->roundPrecision)
                : round($addedResult * $taxInfo['taxRate'], $this->roundPrecision);
            $taxInfo['total'] = $taxInfo['rate'];
            $result[] = $taxInfo;
        }

        return $result;
    }

    /**
     * getRate returns rate information for a given location, optionally ignoring by priority.
     * @return array|null
     */
    protected function getRate(array $options = [])
    {
        extract(array_merge([
            'ignoredPriorities' => [],
        ], $options));

        $location = static::$locationContext;
        if (!$location || !$location->countryCode) {
            return null;
        }

        $rate = null;
        foreach ((array) $this->rates as $row) {
            // Minimum requirements, need to see a country at least
            if (!array_key_exists('country', $row)) {
                continue;
            }

            $taxPriority = isset($row['priority']) ? $row['priority'] : 1;
            if (in_array($taxPriority, $ignoredPriorities)) {
                continue;
            }

            if (!$location->matchesCountry($row['country'] ?? '*')) {
                continue;
            }

            if (!$location->matchesState($row['state'] ?? '*')) {
                continue;
            }

            if (!$location->matchesZip($row['zip'] ?? '*')) {
                continue;
            }

            if (!$location->matchesCity($row['city'] ?? '*')) {
                continue;
            }

            $isCompound = isset($row['compound']) ? $row['compound'] : 0;
            if (preg_match('/^[0-9]+$/', $isCompound)) {
                $isCompound = (int) $isCompound;
            }
            else {
                $isCompound = ($isCompound == 'Y' || $isCompound == 'YES');
            }

            $rate = [
                'rate' => $row['rate'],
                'priority' => $taxPriority,
                'name' => isset($row['tax_name']) ? $row['tax_name'] : 'TAX',
                'compound' => $isCompound
            ];

            break;
        }

        return $rate;
    }

    /**
     * combineTotalTax
     */
    public static function combineTotalTax(array $taxes)
    {
        $result = 0;

        if (!$taxes) {
            return $result;
        }

        foreach ($taxes as $tax) {
            $result += $tax['rate'];
        }

        return $result;
    }

    /**
     * combineTaxes combines two sets of taxes using their name
     */
    public static function combineTaxesByName(array $taxes1, array $taxes2): array
    {
        $result = [];
        $taxes = array_merge(array_values($taxes1), array_values($taxes2));

        foreach ($taxes as $taxInfo) {
            if (!isset($taxInfo['name'])) {
                continue;
            }

            $name = $taxInfo['name'];
            if (!isset($result[$name])) {
                $result[$name] = ['name' => $name, 'total' => 0];
            }

            $result[$name]['total'] += $taxInfo['total'];
        }

        return $result;
    }

    /**
     * calculateInvoiceTaxes
     */
    public static function calculateInvoiceTaxes($invoiceItems)
    {
        $items = [];

        foreach ($invoiceItems as $invoiceItem) {
            if ($invoiceItem instanceof InvoiceItem) {
                $item = new TaxItem;
                $item->taxClassId = $invoiceItem->tax_class_id;
                $item->quantity = $invoiceItem->quantity;
                $item->unitPrice = $invoiceItem->price;
                $item->pricesIncludeTax = $invoiceItem->prices_include_tax;
                $items[] = $item;
            }
        }

        return static::calculateTaxes($items);
    }

    /**
     * calculateTaxes for an array of TaxItems
     * @see Responsiv\Pay\Classes\TaxItem
     */
    public static function calculateTaxes($items, $options = []): array
    {
        if (static::isTaxExemptContext()) {
            return [
                'taxes' => [],
                'itemTaxes' => [],
                'taxTotal' => 0,
            ];
        }

        extract(array_merge([
            'pricesIncludeTax' => null,
        ], $options));

        if ($pricesIncludeTax === null) {
            $pricesIncludeTax = static::doPricesIncludeTax();
        }

        $taxes = [];
        $itemTaxes = [];
        $taxTotal = 0;

        $findAddedTax = function ($taxes) {
            foreach ($taxes as $tax) {
                if ($tax['addedTax']) {
                    return $tax;
                }
            }
            return null;
        };

        // Process cart items
        foreach ($items as $index => $item) {
            $taxClass = $item->getTaxModel();
            if (!$taxClass) {
                continue;
            }

            $iPrice = $item->unitPrice;
            $iTaxes = $taxClass->getTaxRates($iPrice, $options);
            $itemTaxes[$index] = $iTaxes;
            foreach ($iTaxes as $tax) {
                if (!isset($tax['name'])) {
                    continue;
                }

                $iKey = "{$taxClass->id}||{$tax['name']}";

                if (!isset($taxes[$iKey])) {
                    $effectiveRate = $tax['taxRate'];
                    if ($tax['compoundTax']) {
                        if ($addedTax = $findAddedTax($iTaxes)) {
                            $effectiveRate = $tax['taxRate'] * (1 + $addedTax['taxRate']);
                        }
                    }

                    $taxes[$iKey] = [
                        'name' => $tax['name'],
                        'rate' => $tax['rate'],
                        'effectiveRate' => $effectiveRate,
                        'total' => 0,
                    ];
                }

                $taxes[$iKey]['total'] += $item->quantity * $iPrice;
            }
        }

        // Process compounding taxes
        $compoundTaxes = [];
        foreach ($taxes as $taxInfo) {
            $name = $taxInfo['name'];

            if ($pricesIncludeTax) {
                $taxValue = ($taxInfo['total'] * $taxInfo['effectiveRate']) / (1 + $taxInfo['effectiveRate']);
            }
            else {
                $taxValue = $taxInfo['total'] * $taxInfo['effectiveRate'];
            }

            if (!isset($compoundTaxes[$name])) {
                $compoundTaxes[$name] = ['name' => $name, 'total' => 0];
            }

            $compoundTaxes[$name]['total'] = $taxValue;
            $taxTotal += $taxValue;
        }

        return [
            'taxes' => $compoundTaxes,
            'itemTaxes' => $itemTaxes,
            'taxTotal' => $taxTotal,
        ];
    }

    /**
     * getDataTableOptions
     */
    public function getDataTableOptions($attribute, $field, $data)
    {
        if ($field == 'country') {
            return $this->getCountryList(array_get($data, $field));
        }

        if ($field == 'state') {
            return $this->getStateList(array_get($data, 'country'), array_get($data, $field));
        }
    }

    /**
     * getCountryList
     */
    protected function getCountryList($term)
    {
        $result = ['*' => "* - Any Country"];

        // The search term functionality is disabled as it's not supported
        // by the Table widget's drop-down processor -ab 2015-01-03
        //$countries = Country::searchWhere($term, ['name', 'code'])

        $countries = Country::applyEnabled()->lists('name', 'code');

        foreach ($countries as $code => $name) {
            $result[$code] = $code .' - ' . $name;
        }

        return $result;
    }

    /**
     * getStateList
     */
    protected function getStateList($countryCode, $term)
    {
        $result = ['*' => "* - Any State"];

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

    /**
     * isTaxExemptContext
     */
    protected static function isTaxExemptContext(): bool
    {
        if (static::$taxExempt) {
            return true;
        }

        $user = static::$userContext;

        if (!$user && App::runningInFrontend()) {
            $user = Auth::getUser();
        }

        // @todo swap for event
        if ($user) {
            return (bool) $user->primary_group?->is_tax_exempt;
        }

        return false;
    }

    /**
     * doPricesIncludeTax
     */
    protected static function doPricesIncludeTax(): bool
    {
        return static::$pricesIncludeTax;
    }
}
