<?php namespace Responsiv\Pay\Models;

use Db;
use System\Models\SettingModel;
use ValidationException;

/**
 * Setting configuration
 *
 * @property string invoice_prefix
 * @property string new_invoice_number
 *
 * @package responsiv\pay
 * @author Alexey Bobkov, Samuel Georges
 */
class Setting extends SettingModel
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string settingsCode is a unique code for this object
     */
    public $settingsCode = 'responsiv_pay_settings';

    /**
     * @var mixed settingsFields definition file
     */
    public $settingsFields = 'fields.yaml';

    /**
     * @var array rules for validation
     */
    public $rules = [];

    /**
     * initSettingsData
     */
    public function initSettingsData()
    {
        $this->invoice_prefix = '';
    }

    /**
     * beforeValidate
     */
    public function beforeValidate()
    {
        if ($this->new_invoice_number) {
            $this->setInvoiceNumber($this->new_invoice_number);
            $this->new_invoice_number = null;
        }
    }

    /**
     * setInvoiceNumber modifies the next auto increment number by creating and deleting a simple record.
     */
    protected function setInvoiceNumber($number)
    {
        $newId = trim($number);

        if (!strlen($newId)) {
            return;
        }

        if (!preg_match('/^[0-9]+$/', $newId)) {
            throw new ValidationException(['new_invoice_number' => 'Invalid invoice number specified']);
        }

        $prevId = $this->getLastInvoiceNumber();

        if ($prevId >= $newId) {
            throw new ValidationException(['new_invoice_number' => 'New invoice number should be more than the last used number ('.$prevId.')']);
        }

        $newId--;

        if ($newId > $prevId) {
            $tempId = Db::table('responsiv_pay_invoices')->insertGetId(['id' => $newId]);

            Db::table('responsiv_pay_invoices')->where('id', $tempId)->delete();
        }
    }

    /**
     * getLastInvoiceNumber returns the last used invoice identifier. Returns the last
     * used invoice identifier.
     */
    protected function getLastInvoiceNumber(): int
    {
        return (int) Db::table('responsiv_pay_invoices')->orderBy('id', 'desc')->value('id');
    }
}
