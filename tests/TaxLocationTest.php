<?php namespace Responsiv\Pay\Tests;

use Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Facade;
use PluginTestCase;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Classes\TaxLocation;
use October\Rain\Exception\ValidationException;

/**
 * TaxLocationTest validates the TaxLocation value object, in particular the
 * tax ID (VAT) number capture and its flow to the invoice (issue #22).
 */
class TaxLocationTest extends PluginTestCase
{
    /**
     * swapPostRequest replaces the active request with a POST carrying the given
     * data, so the post() helper (which requires a POST method) reads it.
     */
    protected function swapPostRequest(array $data): void
    {
        $request = HttpRequest::create('/', 'POST', $data);
        app()->instance('request', $request);
        Facade::clearResolvedInstance('request');
    }

    /**
     * testFillFromPostCapturesTaxIdNumber verifies the tax ID is read from POST.
     */
    public function testFillFromPostCapturesTaxIdNumber()
    {
        $this->swapPostRequest(['tax_id_number' => 'GB123456789']);

        $location = new TaxLocation;
        $location->fillFromPost();

        $this->assertEquals('GB123456789', $location->taxIdNumber);
    }

    /**
     * testFillFromPostCapturesPrefixedTaxIdNumber verifies the prefixed billing
     * field name is honoured (checkout uses the billing_ prefix).
     */
    public function testFillFromPostCapturesPrefixedTaxIdNumber()
    {
        $this->swapPostRequest(['billing_tax_id_number' => 'DE987654321']);

        $location = new TaxLocation;
        $location->fieldPrefix('billing');
        $location->fillFromPost();

        $this->assertEquals('DE987654321', $location->taxIdNumber);
    }

    /**
     * testFillFromPostCapturesAddressBookId verifies the saved address selection
     * round-trips through POST rather than resetting to New address.
     */
    public function testFillFromPostCapturesAddressBookId()
    {
        $this->swapPostRequest(['address_book_id' => '42']);

        $location = new TaxLocation;
        $location->fillFromPost();

        $this->assertEquals('42', $location->addressBookId);
    }

    /**
     * testFillFromPostCapturesPrefixedAddressBookId verifies the billing-prefixed
     * address book field is honoured.
     */
    public function testFillFromPostCapturesPrefixedAddressBookId()
    {
        $this->swapPostRequest(['billing_address_book_id' => '7']);

        $location = new TaxLocation;
        $location->fieldPrefix('billing');
        $location->fillFromPost();

        $this->assertEquals('7', $location->addressBookId);
    }

    /**
     * testFillFromPostBlankAddressBookIdIsNull verifies an empty selection
     * normalises to null rather than an empty string.
     */
    public function testFillFromPostBlankAddressBookIdIsNull()
    {
        $this->swapPostRequest(['address_book_id' => '']);

        $location = new TaxLocation;
        $location->fillFromPost();

        $this->assertNull($location->addressBookId);
    }

    /**
     * testSaveToInvoiceWritesTaxIdNumber verifies the tax ID transfers onto the
     * invoice model.
     */
    public function testSaveToInvoiceWritesTaxIdNumber()
    {
        $location = new TaxLocation;
        $location->taxIdNumber('FR11223344556');

        $invoice = new Invoice;
        $location->saveToInvoice($invoice);

        $this->assertEquals('FR11223344556', $invoice->tax_id_number);
    }

    /**
     * testFillFromInvoiceReadsTaxIdNumber verifies the round-trip back off an
     * invoice restores the tax ID.
     */
    public function testFillFromInvoiceReadsTaxIdNumber()
    {
        $invoice = new Invoice;
        $invoice->tax_id_number = 'IT44556677889';

        $location = new TaxLocation;
        $location->fillFromInvoice($invoice);

        $this->assertEquals('IT44556677889', $location->taxIdNumber);
    }

    /**
     * testValidateThrowsOctoberValidationException verifies a failing validate()
     * throws October's ValidationException (yielding a 422), not the raw Laravel
     * one that October's AJAX handler misses and reports as a 500 (issue #87).
     */
    public function testValidateThrowsOctoberValidationException()
    {
        $location = new TaxLocation;

        $this->expectException(ValidationException::class);

        $location->validate();
    }

    /**
     * testValidatePassesWhenFullyPopulated verifies a fully populated location
     * validates without throwing.
     */
    public function testValidatePassesWhenFullyPopulated()
    {
        $location = new TaxLocation;
        $location->fillFromOptions([
            'city' => 'London',
            'zip' => 'EC1A 1BB',
        ]);
        $location
            ->firstName('Ada')
            ->lastName('Lovelace')
            ->addressLine1('1 Analytical Way')
            ->countryId(1)
            ->stateId(1)
        ;

        $location->validate();

        $this->assertEquals('Ada', $location->firstName);
    }

    /**
     * testValidateFromPostThrowsOctoberValidationException verifies the POST
     * variant likewise throws October's ValidationException on failure (#87).
     */
    public function testValidateFromPostThrowsOctoberValidationException()
    {
        $this->swapPostRequest([]);

        $location = new TaxLocation;

        $this->expectException(ValidationException::class);

        $location->validateFromPost();
    }

    /**
     * testValidateFromPostPassesWhenRequiredFieldsPresent verifies the POST
     * variant accepts a payload carrying all required fields.
     */
    public function testValidateFromPostPassesWhenRequiredFieldsPresent()
    {
        $this->swapPostRequest([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'address_line1' => '1 Analytical Way',
            'city' => 'London',
            'zip' => 'EC1A 1BB',
            'country_id' => 1,
            'state_id' => 1,
        ]);

        $location = new TaxLocation;
        $location->validateFromPost();

        $this->assertTrue(true);
    }
}
