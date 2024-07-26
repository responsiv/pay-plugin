<?php namespace Responsiv\Pay\Components;

use Cms;
use Auth;
use Flash;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use Responsiv\Pay\Models\PaymentMethod as TypeModel;
use Illuminate\Http\RedirectResponse;
use ApplicationException;

/**
 * PayProfile component
 */
class PayProfile extends ComponentBase
{
    /**
     * componentDetails
     */
    public function componentDetails()
    {
        return [
            'name' => 'Payment Profile',
            'description' => 'Allow an owner to view their payment profile by its identifier'
        ];
    }

    /**
     * defineProperties
     */
    public function defineProperties()
    {
        return [
            'id' => [
                'title' => 'Profile ID',
                'description' => 'The URL route parameter used for looking up the profile by its identifier.',
                'default' => '{{ :id }}',
                'type' => 'string'
            ],
            'isPrimary' => [
                'title' => 'Primary page',
                'description' => 'Link to this page when sending mail notifications.',
                'type' => 'checkbox',
                'default' => true,
                'showExternalParam' => false
            ],
        ];
    }

    /**
     * getReturnPageOptions
     */
    public function getReturnPageOptions()
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    /**
     * onRun
     */
    public function onRun()
    {
        $this->page['paymentMethod'] = $this->paymentMethod();
        $this->page['profile'] = $this->profile();
    }

    /**
     * profile
     */
    protected function profile()
    {
        if (!$user = $this->user()) {
            return null;
        }

        if (!$method = $this->paymentMethod()) {
            return null;
        }

        return $method->findUserProfile($user);
    }

    /**
     * paymentMethod
     */
    protected function paymentMethod()
    {
        if (!$id = $this->property('id')) {
            return null;
        }

        return TypeModel::where('id', $id)->first();
    }

    /**
     * Returns the logged in user, if available, and touches
     * the last seen timestamp.
     * @return RainLab\User\Models\User
     */
    public function user()
    {
        if (!$user = Auth::getUser()) {
            return null;
        }

        return $user;
    }

    /**
     * onUpdatePaymentProfile
     */
    public function onUpdatePaymentProfile()
    {
        if (!$user = $this->user()) {
            throw new ApplicationException('Please log in to manage payment profiles.');
        }

        if (!$paymentMethod = $this->paymentMethod()) {
            throw new ApplicationException('Payment method not found.');
        }

        $result = $paymentMethod->updateUserProfile($user, post());

        // Custom response
        if ($result instanceof RedirectResponse) {
            return $result;
        }
        elseif ($result === false) {
            return;
        }

        // Standard response
        if ($flash = Cms::flashFromPost(__("Payment profile updated."))) {
            Flash::success($flash);
        }

        if ($redirect = Cms::redirectFromPost()) {
            return $redirect;
        }
    }

    /**
     * onDeletePaymentProfile
     */
    public function onDeletePaymentProfile()
    {
        if (!$user = $this->user()) {
            throw new ApplicationException('Please log in to manage payment profiles.');
        }

        if (!$paymentMethod = $this->paymentMethod()) {
            throw new ApplicationException('Payment method not found.');
        }

        $paymentMethod->deleteUserProfile($user);

        // Standard response
        if ($flash = Cms::flashFromPost(__("Payment profile deleted."))) {
            Flash::success($flash);
        }

        if ($redirect = Cms::redirectFromPost()) {
            return $redirect;
        }
    }
}
