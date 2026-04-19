<?php namespace Responsiv\Pay\Models\InvoiceTemplate;

use Twig;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * HasDomPdf adds PDF rendering support to the InvoiceTemplate model.
 */
trait HasDomPdf
{
    /**
     * renderInvoicePdf returns the invoice as raw PDF content
     */
    public function renderInvoicePdf($invoice): string
    {
        $html = $this->renderInvoiceForPdf($invoice);

        $pdf = Pdf::loadHTML($html);

        return $pdf->output();
    }

    /**
     * renderInvoiceForPdf renders the invoice with images embedded as
     * base64 data URIs for reliable PDF generation.
     */
    protected function renderInvoiceForPdf($invoice): string
    {
        $parser = $this->getSyntaxParser($this->content_html);
        $invoiceData = $this->getSyntaxData();

        // Embed file upload fields as base64 data URIs
        $fields = $this->getSyntaxFields();
        if (is_array($fields)) {
            foreach ($fields as $field => $params) {
                if (($params['type'] ?? null) !== 'fileupload') {
                    continue;
                }

                if ($this->hasRelation($field) && ($file = $this->$field)) {
                    $localPath = $file->getLocalPath();
                    $mime = $file->content_type;
                    $invoiceData[$field] = 'data:' . $mime . ';base64,' . base64_encode(
                        file_get_contents($localPath)
                    );
                }
            }
        }

        $invoiceTemplate = $parser->render($invoiceData);

        return Twig::parse($invoiceTemplate, [
            'invoice' => $invoice,
            'css' => $this->content_css
        ]);
    }
}
