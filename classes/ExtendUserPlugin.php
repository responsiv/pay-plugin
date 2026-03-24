<?php namespace Responsiv\Pay\Classes;

use Flash;
use Currency;
use BackendAuth;
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
        return [
            "Credit" => '$/responsiv/pay/partials/_user_credit.php',
        ];
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
