<?php namespace Responsiv\Pay\Models;

use Db;
use Model;
use Cms\Classes\Page;
use ApplicationException;
use ValidationException;

class Settings extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'responsiv_pay_settings';
    public $settingsFields = 'fields.yaml';

    /**
     * Validation rules
     */
    public $rules = [];

    public function initSettingsData()
    {
        $this->invoice_prefix = '';
    }

    public function beforeValidate()
    {
        if ($this->new_invoice_number) {
            $this->setInvoiceNumber($this->new_invoice_number);
            $this->new_invoice_number = null;
        }
    }

    /**
     * Modifies the next auto increment number by creating and deleting a simple record.
     * @param $number int
     * @return void
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
     * Returns the last used invoice identifier.
     * @return integer Returns the last used invoice identifier.
     */
    protected function getLastInvoiceNumber()
    {
        $lastId = Db::table('responsiv_pay_invoices')->orderBy('id', 'desc')->value('id');

        return $lastId ? $lastId : 0;
    }
}
