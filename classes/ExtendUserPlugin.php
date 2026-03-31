<?php namespace Responsiv\Pay\Classes;

use Flash;
use Currency;
use BackendAuth;
use RainLab\User\Models\UserLog;
use Responsiv\Pay\Models\Setting;
use Responsiv\Pay\Models\CreditNote;
use October\Rain\Extension\Container as ExtensionContainer;
use ValidationException;

/**
 * ExtendUserPlugin extends the User plugin with credit note features
 */
class ExtendUserPlugin
{
    /**
     * subscribe
     */
    public function subscribe($events)
    {
        $this->extendUserModel();
        $this->extendUserController();

        $events->listen('rainlab.user.view.extendPreviewTabs', [static::class, 'extendPreviewTabs'], 200);

        $events->listen('rainlab.user.mergeUser', [static::class, 'mergeUser']);

        $events->listen('responsiv.pay.invoicePaid', [static::class, 'logInvoicePaid']);

        $events->listen('rainlab.user.extendLogDetailViewPath', [static::class, 'extendLogDetailViewPath']);

        $events->listen('rainlab.user.extendLogTypeOptions', [static::class, 'extendLogTypeOptions']);
    }

    /**
     * extendUserModel
     */
    public function extendUserModel()
    {
        ExtensionContainer::extendClass(\RainLab\User\Models\User::class, static function($model) {
            $model->hasMany['credit_notes'] = [
                \Responsiv\Pay\Models\CreditNote::class,
                'order' => 'issued_at desc'
            ];
        });
    }

    /**
     * extendUserController
     */
    public function extendUserController()
    {
        ExtensionContainer::extendClass(\RainLab\User\Controllers\Users::class, static function($controller) {
            $controller->addDynamicMethod('onLoadAdjustCreditForm', function() use ($controller) {
                return static::handleLoadAdjustCreditForm($controller);
            });
            $controller->addDynamicMethod('onAdjustCredit', function() use ($controller) {
                return static::handleAdjustCredit($controller);
            });
        });
    }

    /**
     * extendPreviewTabs
     */
    public static function extendPreviewTabs()
    {
        if (!Setting::isCreditEnabled()) {
            return [];
        }

        return [
            "Credit" => '$/responsiv/pay/partials/_user_credit.php',
        ];
    }

    /**
     * mergeUser reassigns pay records from the merged user to the leading user
     */
    public static function mergeUser($leadingUser, $mergedUser)
    {
        \Responsiv\Pay\Models\Invoice::where('user_id', $mergedUser->id)
            ->update(['user_id' => $leadingUser->id]);

        CreditNote::where('user_id', $mergedUser->id)
            ->update(['user_id' => $leadingUser->id]);

        \Responsiv\Pay\Models\UserProfile::where('user_id', $mergedUser->id)
            ->update(['user_id' => $leadingUser->id]);
    }

    /**
     * handleLoadAdjustCreditForm loads the Adjust Credit popup
     */
    public static function handleLoadAdjustCreditForm($controller)
    {
        $controller->vars['userId'] = post('user_id');
        $controller->vars['formWidget'] = static::makeAdjustCreditFormWidget($controller);

        return $controller->makePartial('$/responsiv/pay/partials/_adjust_credit_form.php');
    }

    /**
     * handleAdjustCredit processes the Adjust Credit form submission
     */
    public static function handleAdjustCredit($controller)
    {
        $userId = post('user_id');
        $adjustType = post('CreditNote[adjust_type]', 'credit');
        $amountInput = post('CreditNote[amount]');
        $reason = post('CreditNote[reason]');
        $currencyCode = Currency::getActiveCode();

        if (!$reason) {
            throw new ValidationException(['reason' => __("Please provide a reason.")]);
        }

        if (!$amountInput || (float) $amountInput <= 0) {
            throw new ValidationException(['amount' => __("Please enter a positive amount.")]);
        }

        $amount = Currency::toBaseValue($amountInput);

        $user = \RainLab\User\Models\User::find($userId);
        $adminUser = BackendAuth::getUser();

        if ($adjustType === 'debit') {
            $balance = CreditNote::getBalanceForUser($user, $currencyCode);
            if ($amount > $balance) {
                throw new ValidationException(['amount' => __("Debit amount exceeds the available credit balance.")]);
            }

            CreditNote::issueDebit($user, $amount, $currencyCode, $reason, $adminUser);
            Flash::success(__("Credit has been debited successfully."));
        }
        else {
            CreditNote::issueAdjustment($user, $amount, $currencyCode, $reason, $adminUser);
            Flash::success(__("Credit has been issued successfully."));
        }

        $controller->vars['formModel'] = $user;
        return ['#userCreditTab' => $controller->makePartial('$/responsiv/pay/partials/_user_credit.php')];
    }

    /**
     * logInvoicePaid logs a paid invoice to the user activity timeline
     */
    public static function logInvoicePaid($invoice)
    {
        if (!$invoice->user_id) {
            return;
        }

        UserLog::createRecord($invoice->user_id, 'pay-invoice-paid', [
            'invoice_id' => $invoice->id,
            'invoice_total' => $invoice->total,
            'currency_code' => $invoice->currency_code,
        ]);
    }

    /**
     * extendLogDetailViewPath returns a custom partial for pay log types
     */
    public static function extendLogDetailViewPath($record, $type)
    {
        if ($type === 'pay-invoice-paid') {
            return plugins_path('responsiv/pay/views/userlog/_detail_pay_invoice_paid.php');
        }
    }

    /**
     * extendLogTypeOptions adds pay log types to the filter dropdown
     */
    public static function extendLogTypeOptions()
    {
        return [
            'pay-invoice-paid' => __("Invoice Paid"),
        ];
    }

    /**
     * makeAdjustCreditFormWidget builds the form widget for the Adjust Credit popup
     */
    protected static function makeAdjustCreditFormWidget($controller)
    {
        $config = $controller->makeConfig('$/responsiv/pay/models/creditnote/fields_adjust.yaml');
        $config->arrayName = 'CreditNote';
        $config->model = new CreditNote;
        $widget = $controller->makeWidget(\Backend\Widgets\Form::class, $config);
        $widget->bindToController();

        return $widget;
    }
}
