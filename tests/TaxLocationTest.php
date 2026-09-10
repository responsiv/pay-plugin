<?php namespace Responsiv\Pay\Tests;

use Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Facade;
use PluginTestCase;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Classes\TaxLocation;

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
}
