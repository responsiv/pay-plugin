<?php namespace Responsiv\Pay\Tests;

use PluginTestCase;
use Responsiv\Pay\Models\Tax;
use Responsiv\Pay\Classes\TaxLocation;
use Responsiv\Pay\Classes\TaxItem;
use October\Rain\Database\Model;

/**
 * TaxRuleTest validates tax calculation with real-world tax rules
 * from the USA, Germany, and Canada. Tests cover single rates,
 * multiple additive rates, compound rates, tax-inclusive pricing,
 * and tax exemption.
 */
class TaxRuleTest extends PluginTestCase
{
    /**
     * setUp
     */
    public function setUp(): void
    {
        parent::setUp();

        // Reset global tax context
        Tax::setLocationContext(null);
        Tax::setPricesIncludeTax(false);
        Tax::setTaxExempt(false);
        Tax::setUserContext(null);
    }

    /**
     * tearDown
     */
    public function tearDown(): void
    {
        Tax::setLocationContext(null);
        Tax::setPricesIncludeTax(false);
        Tax::setTaxExempt(false);
        Tax::setUserContext(null);

        parent::tearDown();
    }

    //
    // Helpers
    //

    /**
     * createTaxClass creates a Tax model with the given rates
     */
    protected function createTaxClass(string $name, array $rates): Tax
    {
        Model::unguard();
        $tax = Tax::create([
            'name' => $name,
            'rates' => $rates,
        ]);
        Model::reguard();

        return $tax;
    }

    /**
     * makeLocation creates a TaxLocation with country and optional state
     */
    protected function makeLocation(string $countryCode, ?string $stateCode = null): TaxLocation
    {
        $location = new TaxLocation;
        $location->countryCode($countryCode);
        if ($stateCode !== null) {
            $location->stateCode($stateCode);
        }
        return $location;
    }

    //
    // USA Tax Tests
    //

    /**
     * testCaliforniaStateSalesTax — California charges a flat 7.25% state
     * sales tax on all purchases. This is the base rate before any local
     * district taxes are added.
     */
    public function testCaliforniaStateSalesTax()
    {
        $tax = $this->createTaxClass('California Sales Tax', [
            [
                'tax_name' => 'CA State Tax',
                'rate' => 7.25,
                'country' => 'US',
                'state' => 'CA',
                'priority' => 1,
                'is_compound' => 0,
            ],
        ]);

        Tax::setLocationContext($this->makeLocation('US', 'CA'));

        // $100.00 item → $7.25 tax
        $this->assertEquals(725, $tax->getTotalTax(10000));

        // $49.99 item → 362.4275 cents → rounds to 362 cents
        $this->assertEquals(362, $tax->getTotalTax(4999));
    }

    /**
     * testNewYorkCitySalesTax — NYC has three additive tax layers:
     * state (4%), city (4.5%), and MCTD surcharge (0.375%), totaling 8.875%.
     * In the USA, all sales tax layers are additive (not compound) — each
     * is calculated independently on the base price and then summed.
     *
     * The system models this as two separate rate rows with priority 1 and 2.
     * Each priority level can match one rate, so we split the NYC tax into
     * two rows: state tax at priority 1 and city+MCTD combined at priority 2.
     */
    public function testNewYorkCitySalesTax()
    {
        $tax = $this->createTaxClass('New York Tax', [
            [
                'tax_name' => 'NY State Tax',
                'rate' => 4,
                'country' => 'US',
                'state' => 'NY',
                'priority' => 1,
                'is_compound' => 0,
            ],
            [
                'tax_name' => 'NYC Local Tax',
                'rate' => 4.875,
                'country' => 'US',
                'state' => 'NY',
                'priority' => 2,
                'is_compound' => 0,
            ],
        ]);

        Tax::setLocationContext($this->makeLocation('US', 'NY'));

        // $100.00 item
        // NY State: 10000 * 0.04 = 400
        // NYC Local: 10000 * 0.04875 = 487.5 → rounds to 488
        // Total tax: 400 + 488 = 888
        $rates = $tax->getTaxRates(10000);
        $this->assertCount(2, $rates);

        $this->assertEquals('NY State Tax', $rates[0]['name']);
        $this->assertEquals(400, $rates[0]['rate']);
        $this->assertFalse($rates[0]['compoundTax']);

        $this->assertEquals('NYC Local Tax', $rates[1]['name']);
        $this->assertEquals(488, $rates[1]['rate']);
        $this->assertFalse($rates[1]['compoundTax']);

        $this->assertEquals(888, $tax->getTotalTax(10000));
    }

    /**
     * testTexasCombinedSalesTax — Texas has a state rate of 6.25% plus
     * up to 2% local tax (city/county/transit), for a maximum combined
     * rate of 8.25%. All layers are additive.
     */
    public function testTexasCombinedSalesTax()
    {
        $tax = $this->createTaxClass('Texas Tax', [
            [
                'tax_name' => 'TX State Tax',
                'rate' => 6.25,
                'country' => 'US',
                'state' => 'TX',
                'priority' => 1,
                'is_compound' => 0,
            ],
            [
                'tax_name' => 'TX Local Tax',
                'rate' => 2,
                'country' => 'US',
                'state' => 'TX',
                'priority' => 2,
                'is_compound' => 0,
            ],
        ]);

        Tax::setLocationContext($this->makeLocation('US', 'TX'));

        // $200.00 item
        // TX State: 20000 * 0.0625 = 1250
        // TX Local: 20000 * 0.02 = 400
        // Total tax: 1650
        $this->assertEquals(1250, $tax->getTaxRates(20000)[0]['rate']);
        $this->assertEquals(400, $tax->getTaxRates(20000)[1]['rate']);
        $this->assertEquals(1650, $tax->getTotalTax(20000));
    }

    /**
     * testUsStateOnlyMatchesCorrectState — A California tax rate should
     * not apply when the buyer is located in Texas.
     */
    public function testUsStateOnlyMatchesCorrectState()
    {
        $tax = $this->createTaxClass('California Sales Tax', [
            [
                'tax_name' => 'CA State Tax',
                'rate' => 7.25,
                'country' => 'US',
                'state' => 'CA',
                'priority' => 1,
                'is_compound' => 0,
            ],
        ]);

        // Buyer in Texas — should NOT match California rate
        Tax::setLocationContext($this->makeLocation('US', 'TX'));
        $this->assertEquals(0, $tax->getTotalTax(10000));

        // Buyer in California — should match
        Tax::setLocationContext($this->makeLocation('US', 'CA'));
        $this->assertEquals(725, $tax->getTotalTax(10000));
    }

    //
    // Germany Tax Tests
    //

    /**
     * testGermanyStandardVat — Germany charges 19% Mehrwertsteuer (MwSt)
     * on most goods and services.
     */
    public function testGermanyStandardVat()
    {
        $tax = $this->createTaxClass('German VAT', [
            [
                'tax_name' => 'MwSt 19%',
                'rate' => 19,
                'country' => 'DE',
                'state' => '*',
                'priority' => 1,
                'is_compound' => 0,
            ],
        ]);

        Tax::setLocationContext($this->makeLocation('DE'));

        // €50.00 → €9.50 tax
        $this->assertEquals(950, $tax->getTotalTax(5000));

        // €119.00 → €22.61 tax
        $this->assertEquals(2261, $tax->getTotalTax(11900));
    }

    /**
     * testGermanyReducedVat — Germany charges a reduced 7% VAT on food,
     * books, newspapers, and some other categories.
     */
    public function testGermanyReducedVat()
    {
        $tax = $this->createTaxClass('German Reduced VAT', [
            [
                'tax_name' => 'MwSt 7%',
                'rate' => 7,
                'country' => 'DE',
                'state' => '*',
                'priority' => 1,
                'is_compound' => 0,
            ],
        ]);

        Tax::setLocationContext($this->makeLocation('DE'));

        // €100.00 → €7.00 tax
        $this->assertEquals(700, $tax->getTotalTax(10000));
    }

    /**
     * testGermanyVatInclusivePricing — In Germany, consumer prices include
     * VAT. Extracting 19% VAT from a €119.00 gross price should yield
     * €19.00 tax and €100.00 net.
     */
    public function testGermanyVatInclusivePricing()
    {
        $tax = $this->createTaxClass('German VAT', [
            [
                'tax_name' => 'MwSt 19%',
                'rate' => 19,
                'country' => 'DE',
                'state' => '*',
                'priority' => 1,
                'is_compound' => 0,
            ],
        ]);

        Tax::setLocationContext($this->makeLocation('DE'));

        // €119.00 gross (inc. 19% VAT)
        // Tax = (11900 * 0.19) / 1.19 = 1900
        $this->assertEquals(1900, $tax->getTotalUntax(11900));

        // €100.00 gross (inc. 19% VAT)
        // Tax = (10000 * 0.19) / 1.19 = 1596.64 → rounds to 1597
        $this->assertEquals(1597, $tax->getTotalUntax(10000));
    }

    /**
     * testGermanyVatDoesNotApplyOutsideCountry — German VAT should not
     * apply to a buyer located in the USA.
     */
    public function testGermanyVatDoesNotApplyOutsideCountry()
    {
        $tax = $this->createTaxClass('German VAT', [
            [
                'tax_name' => 'MwSt 19%',
                'rate' => 19,
                'country' => 'DE',
                'state' => '*',
                'priority' => 1,
                'is_compound' => 0,
            ],
        ]);

        Tax::setLocationContext($this->makeLocation('US', 'CA'));

        $this->assertEquals(0, $tax->getTotalTax(10000));
    }

    //
    // Canada Tax Tests
    //

    /**
     * testOntarioHst — Ontario uses a harmonized sales tax (HST) of 13%
     * that combines the 5% federal GST with the 8% provincial portion
     * into a single tax.
     */
    public function testOntarioHst()
    {
        $tax = $this->createTaxClass('Ontario HST', [
            [
                'tax_name' => 'HST',
                'rate' => 13,
                'country' => 'CA',
                'state' => 'ON',
                'priority' => 1,
                'is_compound' => 0,
            ],
        ]);

        Tax::setLocationContext($this->makeLocation('CA', 'ON'));

        // $100.00 → $13.00 HST
        $this->assertEquals(1300, $tax->getTotalTax(10000));

        // $75.00 → $9.75 HST
        $this->assertEquals(975, $tax->getTotalTax(7500));
    }

    /**
     * testBritishColumbiaGstPlusPst — British Columbia charges GST (5%)
     * and PST (7%) separately. Both are additive — each is calculated
     * independently on the base price, for a combined 12%.
     */
    public function testBritishColumbiaGstPlusPst()
    {
        $tax = $this->createTaxClass('BC Tax', [
            [
                'tax_name' => 'GST',
                'rate' => 5,
                'country' => 'CA',
                'state' => 'BC',
                'priority' => 1,
                'is_compound' => 0,
            ],
            [
                'tax_name' => 'BC PST',
                'rate' => 7,
                'country' => 'CA',
                'state' => 'BC',
                'priority' => 2,
                'is_compound' => 0,
            ],
        ]);

        Tax::setLocationContext($this->makeLocation('CA', 'BC'));

        // $100.00 item
        // GST: 10000 * 0.05 = 500
        // PST: 10000 * 0.07 = 700
        // Total tax: 1200
        $rates = $tax->getTaxRates(10000);
        $this->assertCount(2, $rates);

        $this->assertEquals('GST', $rates[0]['name']);
        $this->assertEquals(500, $rates[0]['rate']);

        $this->assertEquals('BC PST', $rates[1]['name']);
        $this->assertEquals(700, $rates[1]['rate']);

        $this->assertEquals(1200, $tax->getTotalTax(10000));
    }

    /**
     * testSaskatchewanGstPlusPst — Saskatchewan charges GST (5%) and
     * PST (6%) separately, both additive, for a combined 11%.
     */
    public function testSaskatchewanGstPlusPst()
    {
        $tax = $this->createTaxClass('SK Tax', [
            [
                'tax_name' => 'GST',
                'rate' => 5,
                'country' => 'CA',
                'state' => 'SK',
                'priority' => 1,
                'is_compound' => 0,
            ],
            [
                'tax_name' => 'SK PST',
                'rate' => 6,
                'country' => 'CA',
                'state' => 'SK',
                'priority' => 2,
                'is_compound' => 0,
            ],
        ]);

        Tax::setLocationContext($this->makeLocation('CA', 'SK'));

        // $200.00 item
        // GST: 20000 * 0.05 = 1000
        // PST: 20000 * 0.06 = 1200
        // Total: 2200
        $this->assertEquals(2200, $tax->getTotalTax(20000));
    }

    /**
     * testAlbertaGstOnly — Alberta has no provincial sales tax. Only the
     * federal 5% GST applies.
     */
    public function testAlbertaGstOnly()
    {
        $tax = $this->createTaxClass('Alberta Tax', [
            [
                'tax_name' => 'GST',
                'rate' => 5,
                'country' => 'CA',
                'state' => 'AB',
                'priority' => 1,
                'is_compound' => 0,
            ],
        ]);

        Tax::setLocationContext($this->makeLocation('CA', 'AB'));

        // $100.00 → $5.00 GST only
        $this->assertEquals(500, $tax->getTotalTax(10000));
    }

    //
    // Compound Tax Tests
    //

    /**
     * testQuebecPreHarmonizationCompoundTax — Before January 1, 2013,
     * Quebec's QST (9.975%) was compound: it was calculated on the
     * base price PLUS the GST, creating a "tax on tax" effect.
     *
     * This is the most well-known real-world example of compound taxation.
     *
     * $100.00 item:
     *   GST = $100.00 × 5% = $5.00
     *   QST = ($100.00 + $5.00) × 9.975% = $10.47 (rounded)
     *   Total tax = $15.47
     */
    public function testQuebecPreHarmonizationCompoundTax()
    {
        $tax = $this->createTaxClass('Quebec Pre-2013 Tax', [
            [
                'tax_name' => 'GST',
                'rate' => 5,
                'country' => 'CA',
                'state' => 'QC',
                'priority' => 1,
                'is_compound' => 0,
            ],
            [
                'tax_name' => 'QST',
                'rate' => 9.975,
                'country' => 'CA',
                'state' => 'QC',
                'priority' => 2,
                'is_compound' => 1,
            ],
        ]);

        Tax::setLocationContext($this->makeLocation('CA', 'QC'));

        $rates = $tax->getTaxRates(10000);
        $this->assertCount(2, $rates);

        // GST: 10000 * 0.05 = 500
        $this->assertEquals('GST', $rates[0]['name']);
        $this->assertEquals(500, $rates[0]['rate']);
        $this->assertTrue($rates[0]['addedTax']);
        $this->assertFalse($rates[0]['compoundTax']);

        // QST (compound): (10000 + 500) * 0.09975 = 1047.375 → rounds to 1047
        $this->assertEquals('QST', $rates[1]['name']);
        $this->assertEquals(1047, $rates[1]['rate']);
        $this->assertTrue($rates[1]['compoundTax']);
        $this->assertFalse($rates[1]['addedTax']);

        // Total tax: 500 + 1047 = 1547
        $this->assertEquals(1547, $tax->getTotalTax(10000));
    }

    /**
     * testQuebecCurrentNonCompound — Since January 1, 2013, Quebec's QST
     * is no longer compound. Both GST (5%) and QST (9.975%) are calculated
     * independently on the base price, for a combined 14.975%.
     */
    public function testQuebecCurrentNonCompound()
    {
        $tax = $this->createTaxClass('Quebec Current Tax', [
            [
                'tax_name' => 'GST',
                'rate' => 5,
                'country' => 'CA',
                'state' => 'QC',
                'priority' => 1,
                'is_compound' => 0,
            ],
            [
                'tax_name' => 'QST',
                'rate' => 9.975,
                'country' => 'CA',
                'state' => 'QC',
                'priority' => 2,
                'is_compound' => 0,
            ],
        ]);

        Tax::setLocationContext($this->makeLocation('CA', 'QC'));

        // $100.00 item
        // GST: 10000 * 0.05 = 500
        // QST: 10000 * 9.975/100 = 997.5 → round() = 998
        // Total: 1498
        $rates = $tax->getTaxRates(10000);
        $this->assertCount(2, $rates);

        $this->assertEquals(500, $rates[0]['rate']);
        $this->assertFalse($rates[0]['compoundTax']);

        $this->assertEquals(998, $rates[1]['rate']);
        $this->assertFalse($rates[1]['compoundTax']);

        $this->assertEquals(1498, $tax->getTotalTax(10000));
    }

    /**
     * testCompoundVsAdditiveProducesDifferentResults — Demonstrates the
     * mathematical difference between compound and additive tax. With the
     * same rates, compound tax always produces a higher total because it
     * creates a "tax on tax" effect.
     */
    public function testCompoundVsAdditiveProducesDifferentResults()
    {
        // Two taxes: 5% and 10%. Additive version.
        $additive = $this->createTaxClass('Additive', [
            [
                'tax_name' => 'Tax A',
                'rate' => 5,
                'country' => 'US',
                'state' => '*',
                'priority' => 1,
                'is_compound' => 0,
            ],
            [
                'tax_name' => 'Tax B',
                'rate' => 10,
                'country' => 'US',
                'state' => '*',
                'priority' => 2,
                'is_compound' => 0,
            ],
        ]);

        // Same two taxes but Tax B is compound.
        $compound = $this->createTaxClass('Compound', [
            [
                'tax_name' => 'Tax A',
                'rate' => 5,
                'country' => 'US',
                'state' => '*',
                'priority' => 1,
                'is_compound' => 0,
            ],
            [
                'tax_name' => 'Tax B',
                'rate' => 10,
                'country' => 'US',
                'state' => '*',
                'priority' => 2,
                'is_compound' => 1,
            ],
        ]);

        Tax::setLocationContext($this->makeLocation('US'));

        // $100.00 item — additive
        // Tax A: 10000 * 0.05 = 500
        // Tax B: 10000 * 0.10 = 1000
        // Total: 1500
        $this->assertEquals(1500, $additive->getTotalTax(10000));

        // $100.00 item — compound
        // Tax A: 10000 * 0.05 = 500
        // Tax B: (10000 + 500) * 0.10 = 1050
        // Total: 1550
        $this->assertEquals(1550, $compound->getTotalTax(10000));

        // Compound total is higher
        $this->assertGreaterThan(
            $additive->getTotalTax(10000),
            $compound->getTotalTax(10000)
        );
    }

    /**
     * testCompoundTaxInclusivePricing — When prices include compound tax,
     * extracting the tax requires working backwards through the compound
     * calculation. Uses Quebec pre-2013 rates as the real-world example.
     */
    public function testCompoundTaxInclusivePricing()
    {
        $tax = $this->createTaxClass('Quebec Pre-2013', [
            [
                'tax_name' => 'GST',
                'rate' => 5,
                'country' => 'CA',
                'state' => 'QC',
                'priority' => 1,
                'is_compound' => 0,
            ],
            [
                'tax_name' => 'QST',
                'rate' => 9.975,
                'country' => 'CA',
                'state' => 'QC',
                'priority' => 2,
                'is_compound' => 1,
            ],
        ]);

        Tax::setLocationContext($this->makeLocation('CA', 'QC'));

        // Price inclusive of tax: 11547 cents
        // This was calculated as: base=10000, GST=500, QST=1047
        // Extracting tax from 11547:
        //   GST untax: round((11547 * 0.05) / 1.05) = round(549.86) = 550
        //   Compound base: 11547 + 550 = 12097
        //   QST untax: round((12097 * 0.09975) / 1.09975) = round(1097.06) = 1097
        //   Total untax: 550 + 1097 = 1647
        $totalUntax = $tax->getTotalUntax(11547);
        $this->assertEquals(1647, $totalUntax);
    }

    //
    // Edge Cases and Cross-Cutting Tests
    //

    /**
     * testTaxExemptReturnsZero — When the tax-exempt flag is set, no tax
     * should be calculated regardless of the tax class configuration.
     */
    public function testTaxExemptReturnsZero()
    {
        $tax = $this->createTaxClass('German VAT', [
            [
                'tax_name' => 'MwSt 19%',
                'rate' => 19,
                'country' => 'DE',
                'state' => '*',
                'priority' => 1,
                'is_compound' => 0,
            ],
        ]);

        Tax::setLocationContext($this->makeLocation('DE'));
        Tax::setTaxExempt(true);

        $this->assertEquals(0, $tax->getTotalTax(10000));
        $this->assertEmpty($tax->getTaxRates(10000));
    }

    /**
     * testNoLocationContextReturnsZero — When no location context is set,
     * the system cannot determine which tax rates apply and returns zero.
     */
    public function testNoLocationContextReturnsZero()
    {
        $tax = $this->createTaxClass('German VAT', [
            [
                'tax_name' => 'MwSt 19%',
                'rate' => 19,
                'country' => 'DE',
                'state' => '*',
                'priority' => 1,
                'is_compound' => 0,
            ],
        ]);

        // No location context set
        $this->assertEquals(0, $tax->getTotalTax(10000));
    }

    /**
     * testWildcardStateMatchesAllStates — When a tax rate uses '*' for the
     * state, it should apply to all states within that country.
     */
    public function testWildcardStateMatchesAllStates()
    {
        $tax = $this->createTaxClass('Country-wide Tax', [
            [
                'tax_name' => 'National Tax',
                'rate' => 10,
                'country' => 'AU',
                'state' => '*',
                'priority' => 1,
                'is_compound' => 0,
            ],
        ]);

        // Should match any Australian state
        Tax::setLocationContext($this->makeLocation('AU', 'NSW'));
        $this->assertEquals(1000, $tax->getTotalTax(10000));

        Tax::setLocationContext($this->makeLocation('AU', 'VIC'));
        $this->assertEquals(1000, $tax->getTotalTax(10000));

        Tax::setLocationContext($this->makeLocation('AU', 'QLD'));
        $this->assertEquals(1000, $tax->getTotalTax(10000));
    }

    /**
     * testCalculateTaxesAcrossMultipleItems — Verifies the static
     * calculateTaxes method correctly sums taxes across multiple cart
     * items with different quantities. Uses Ontario HST as example.
     */
    public function testCalculateTaxesAcrossMultipleItems()
    {
        $tax = $this->createTaxClass('Ontario HST', [
            [
                'tax_name' => 'HST',
                'rate' => 13,
                'country' => 'CA',
                'state' => 'ON',
                'priority' => 1,
                'is_compound' => 0,
            ],
        ]);

        Tax::setLocationContext($this->makeLocation('CA', 'ON'));

        // Item 1: $50.00 × 2 = $100.00
        $item1 = new TaxItem;
        $item1->taxClassId = $tax->id;
        $item1->quantity = 2;
        $item1->unitPrice = 5000;

        // Item 2: $25.00 × 4 = $100.00
        $item2 = new TaxItem;
        $item2->taxClassId = $tax->id;
        $item2->quantity = 4;
        $item2->unitPrice = 2500;

        $result = Tax::calculateTaxes([$item1, $item2]);

        // Total base: $200.00
        // HST 13%: $200.00 × 0.13 = $26.00 = 2600
        $this->assertEquals(2600, $result['taxTotal']);
        $this->assertArrayHasKey('HST', $result['taxes']);
    }

    /**
     * testWithContextRestoresPreviousState — The withContext method should
     * temporarily change the tax context and restore it afterwards.
     */
    public function testWithContextRestoresPreviousState()
    {
        $tax = $this->createTaxClass('German VAT', [
            [
                'tax_name' => 'MwSt 19%',
                'rate' => 19,
                'country' => 'DE',
                'state' => '*',
                'priority' => 1,
                'is_compound' => 0,
            ],
        ]);

        $usLocation = $this->makeLocation('US', 'CA');
        $deLocation = $this->makeLocation('DE');

        // Set initial context to US
        Tax::setLocationContext($usLocation);
        $this->assertEquals(0, $tax->getTotalTax(10000));

        // Temporarily switch to DE context
        $result = Tax::withContext($deLocation, false, function() use ($tax) {
            return $tax->getTotalTax(10000);
        });
        $this->assertEquals(1900, $result);

        // Context should be restored to US
        $this->assertEquals(0, $tax->getTotalTax(10000));
    }

    /**
     * testMultipleRatesSameCountryDifferentStates — A single tax class
     * can have different rates for different states. The system should
     * select the matching rate based on the buyer's location.
     */
    public function testMultipleRatesSameCountryDifferentStates()
    {
        $tax = $this->createTaxClass('US State Tax', [
            [
                'tax_name' => 'CA State Tax',
                'rate' => 7.25,
                'country' => 'US',
                'state' => 'CA',
                'priority' => 1,
                'is_compound' => 0,
            ],
            [
                'tax_name' => 'TX State Tax',
                'rate' => 6.25,
                'country' => 'US',
                'state' => 'TX',
                'priority' => 1,
                'is_compound' => 0,
            ],
            [
                'tax_name' => 'NY State Tax',
                'rate' => 4,
                'country' => 'US',
                'state' => 'NY',
                'priority' => 1,
                'is_compound' => 0,
            ],
        ]);

        // California buyer
        Tax::setLocationContext($this->makeLocation('US', 'CA'));
        $this->assertEquals(725, $tax->getTotalTax(10000));

        // Texas buyer
        Tax::setLocationContext($this->makeLocation('US', 'TX'));
        $this->assertEquals(625, $tax->getTotalTax(10000));

        // New York buyer
        Tax::setLocationContext($this->makeLocation('US', 'NY'));
        $this->assertEquals(400, $tax->getTotalTax(10000));

        // Oregon buyer (no matching rate → no tax)
        Tax::setLocationContext($this->makeLocation('US', 'OR'));
        $this->assertEquals(0, $tax->getTotalTax(10000));
    }
}
