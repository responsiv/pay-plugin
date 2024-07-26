<?php namespace Responsiv\Pay\Models;

use File;
use Twig;
use Model;

/**
 * InvoiceTemplate Model
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $content_html
 * @property string $content_css
 * @property string $syntax_data
 * @property string $syntax_fields
 * @property bool $is_default
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon $created_at
 *
 * @package responsiv\pay
 * @author Alexey Bobkov, Samuel Georges
 */
class InvoiceTemplate extends Model
{
    use \October\Rain\Database\Traits\Defaultable;
    use \October\Rain\Parse\Syntax\SyntaxModelTrait;

    /**
     * @var string table used by the model
     */
    public $table = 'responsiv_pay_invoice_templates';

    /**
     * @var array jsonable attribute names that are json encoded and decoded from the database
     */
    protected $jsonable = [
        'syntax_data',
        'syntax_fields'
    ];

    /**
     * beforeSave
     */
    public function beforeSave()
    {
        $this->makeSyntaxFields($this->content_html);
    }

    /**
     * getContentCssAttribute
     */
    public function getContentCssAttribute($content)
    {
        if (!$this->exists || !strlen($content)) {
            return File::get(__DIR__ . '/invoicetemplate/default_content.css');
        }

        return $content;
    }

    /**
     * getContentHtmlAttribute
     */
    public function getContentHtmlAttribute($content)
    {
        if (!$this->exists || !strlen($content)) {
            return File::get(__DIR__ . '/invoicetemplate/default_content.htm');
        }

        return $content;
    }

    /**
     * renderInvoice
     */
    public function renderInvoice($invoice)
    {
        $parser = $this->getSyntaxParser($this->content_html);
        $invoiceData = $this->getSyntaxData();
        $invoiceTemplate = $parser->render($invoiceData);

        $twigData = [
            'invoice' => $invoice,
            'css' => $this->content_css
        ];

        $twigTemplate = Twig::parse($invoiceTemplate, $twigData);
        return $twigTemplate;
    }
}
