<?php namespace Responsiv\Pay\Controllers;

use Flash;
use Backend;
use Exception;
use BackendMenu;
use Responsiv\Pay\Models\Tax;
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
     * @var mixed invoiceItemCache
     */
    protected $invoiceItemCache;

    /**
     * @var bool hasSetFormValues
     */
    protected $hasSetFormValues = false;

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
     * formBeforeSave
     */
    public function formBeforeSave($model)
    {
        $this->setFormValuesOnce();
        $this->updateInvoiceTotals();
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

    /**
     * onUpdateTotals
     */
    public function onUpdateTotals()
    {
        $this->pageAction();
        $this->updateInvoiceTotals();

        return [
            '#'.$this->getId('scoreboard') => $this->makePartial('scoreboard_edit')
        ];
    }

    /**
     * onUpdateUser
     */
    public function onUpdateUser()
    {
        $response = $this->onUpdateTotals();

        $invoice = $this->formGetModel();
        if ($user = $invoice->user) {
            $this->formGetWidget()->setFormValues([
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'company' => $user->company,
                'phone' => $user->phone,
                'street_address' => $user->street_address,
                'city' => $user->city,
                'zip' => $user->zip,
                'country' => $user->country_id,
                'state' => $user->state_id,
            ]);

            $invoice->unsetRelation('country');
            $invoice->unsetRelation('state');

            $response += $this->formRefreshFields([
                'first_name',
                'last_name',
                'email',
                'company',
                'phone',
                'street_address',
                'city',
                'zip',
                'country',
                'state'
            ]);
        }

        return $response;
    }

    /**
     * updateInvoiceTotals
     */
    protected function updateInvoiceTotals()
    {
        $this->setFormValuesOnce();

        $invoice = $this->formGetModel();

        Tax::setUserContext($invoice->user);
        Tax::setTaxExempt($invoice->is_tax_exempt);

        $invoice->evalInvoiceTotals(['items' => $this->getDeferredInvoiceItems()]);
    }

    /**
     * getDeferredInvoiceItems
     */
    protected function getDeferredInvoiceItems()
    {
        return $this->invoiceItemCache ??= $this->formGetModel()
            ->items()
            ->withDeferred($this->formGetSessionKey())
            ->get()
        ;
    }

    /**
     * setFormValuesOnce
     */
    protected function setFormValuesOnce()
    {
        if ($this->hasSetFormValues) {
            return;
        }

        $this->formGetWidget()->setFormValues();

        $this->hasSetFormValues = true;
    }
}
