<?php namespace Responsiv\Pay\Components;

use Auth;
use Flash;
use Request;
use Redirect;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use Responsiv\Pay\Models\UserProfile as UserProfileModel;
use Responsiv\Pay\Models\PaymentMethod as TypeModel;
use Illuminate\Http\RedirectResponse;
use ApplicationException;

class Profile extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'Payment Profile',
            'description' => 'Allow an owner to view their payment profile by its identifier'
        ];
    }

    public function defineProperties()
    {
        return [
            'id' => [
                'title'       => 'Profile ID',
                'description' => 'The URL route parameter used for looking up the profile by its identifier.',
                'default'     => '{{ :id }}',
                'type'        => 'string'
            ],
            'returnPage' => [
                'title'       => 'Return page',
                'description' => 'Name of the page file to redirect the user after saving or deleting their profile.',
                'type'        => 'dropdown',
            ],
            'isPrimary' => [
                'title'       => 'Primary page',
                'description' => 'Link to this page when sending mail notifications.',
                'type'        => 'checkbox',
                'default'     => true,
                'showExternalParam' => false
            ],
        ];
    }

    public function getReturnPageOptions()
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    public function onRun()
    {
        $this->page['returnPage'] = $this->returnPage();
        $this->page['paymentMethod'] = $method = $this->paymentMethod();
        $this->page['profile'] = $profile = $this->profile();
    }

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

    public function onUpdatePaymentProfile()
    {
        if (!$user = $this->user()) {
            throw new ApplicationException('Please log in to manage payment profiles.');
        }

        if (!$paymentMethod = $this->paymentMethod()) {
            throw new ApplicationException('Payment method not found.');
        }

        $result = $paymentMethod->updateUserProfile($user, post());

        /*
         * Custom response
         */
        if ($result instanceof RedirectResponse) {
            return $result;
        }
        elseif ($result === false) {
            return;
        }

        /*
         * Standard response
         */
        if (!post('no_flash')) {
            Flash::success(post('message', 'Payment profile updated.'));
        }

        return Redirect::to($this->returnPageUrl());
    }

    public function onDeletePaymentProfile()
    {
        if (!$user = $this->user()) {
            throw new ApplicationException('Please log in to manage payment profiles.');
        }

        if (!$paymentMethod = $this->paymentMethod()) {
            throw new ApplicationException('Payment method not found.');
        }

        $paymentMethod->deleteUserProfile($user);

        if (!post('no_flash')) {
            Flash::success(post('message', 'Payment profile deleted.'));
        }

        return Redirect::to($this->returnPageUrl());
    }

    /**
     * Returns the return page name as per configuration.
     * @return string
     */
    protected function returnPage()
    {
        return $this->property('returnPage');
    }

    /**
     * Returns a profile page URL for a payment method
     */
    public function returnPageUrl()
    {
        if ($redirect = post('redirect')) {
            return $redirect;
        }

        return $this->pageUrl($this->returnPage());
    }
}
