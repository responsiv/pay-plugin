<?php namespace Responsiv\Pay\Controllers;

use Flash;
use Backend;
use Exception;
use BackendMenu;
use Backend\Classes\Controller;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceStatusLog;
use Responsiv\Currency\Models\Currency as CurrencyModel;

/**
 * Invoices Back-end Controller
 */
class Invoices extends Controller
{
    use \Responsiv\Pay\Controllers\Invoices\HasInvoiceStatus;

    /**
     * @var array implement extensions
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\RelationController::class,
    ];

    /**
     * @var array formConfig configuration.
     */
    public $formConfig = 'config_form.yaml';

    /**
     * @var array listConfig configuration.
     */
    public $listConfig = 'config_list.yaml';

    /**
     * @var array relationConfig for extensions.
     */
    public $relationConfig = 'config_relation.yaml';

    /**
     * __construct
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Responsiv.Pay', 'pay', 'invoices');
    }

    /**
     * listExtendQuery
     */
    public function listExtendQuery($query)
    {
        return $query->applyNotThrowaway();
    }

    /**
     * preview
     */
    public function preview($recordId = null, $context = null)
    {
        try {
            $invoice = Invoice::find($recordId);
            $this->vars['currency'] = CurrencyModel::findByCode($invoice->currency);
        }
        catch (Exception $ex) {
            $this->handleError($ex);
        }

        return $this->asExtension('FormController')->preview($recordId, $context);
    }

    /**
     * preview_onDelete
     */
    public function preview_onDelete($recordId = null)
    {
        return $this->asExtension('FormController')->update_onDelete($recordId);
    }

    /**
     * formExtendRefreshData
     */
    public function formExtendRefreshData($host, $data)
    {
        if (!isset($data['user'])) {
            return;
        }

        $host->model->user = $data['user'];
        if (!$user = $host->model->user) {
            return;
        }

        $data['first_name'] = $user->first_name;
        $data['last_name'] = $user->last_name;
        $data['email'] = $user->email;
        $data['phone'] = $user->phone;
        $data['company'] = $user->company;
        $data['address_line1'] = $user->address_line1;
        $data['address_line2'] = $user->address_line2;
        $data['city'] = $user->city;
        $data['zip'] = $user->zip;
        $data['country'] = $user->country_id;
        $data['state'] = $user->state_id;
        return $data;
    }

    /**
     * relationBeforeSave
     */
    public function relationBeforeSave($field, $model)
    {
        if ($field === 'items') {
            $model->evalInvoiceItemTotals();
        }
    }
}
