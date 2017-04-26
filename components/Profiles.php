<?php namespace Responsiv\Pay\Components;

use Auth;
use Redirect;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use Responsiv\Pay\Models\UserProfile as UserProfileModel;
use Responsiv\Pay\Models\PaymentMethod as TypeModel;
use ApplicationException;

class Profiles extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'Payment Profiles',
            'description' => 'Displays a list of payment profiles belonging to a user'
        ];
    }

    public function defineProperties()
    {
        return [
            'profilePage' => [
                'title'       => 'Profile page',
                'description' => 'Name of the profile page file for the payment profile links. This property is used by the default component partial.',
                'type'        => 'dropdown',
            ],
            'autoRedirect' => [
                'title'       => 'Automatic redirection',
                'description' => 'Redirect to profile page when only one method exists.',
                'type'        => 'checkbox',
                'default'     => true,
                'showExternalParam' => false
            ],
        ];
    }

    public function getProfilePageOptions()
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    public function onRun()
    {
        $this->page['user'] = $this->user();
        $this->page['profilePage'] = $this->profilePage();
        $this->page['paymentMethods'] = $methods = $this->paymentMethods();

        if (
            $this->property('autoRedirect') &&
            count($methods) === 1 &&
            ($method = $methods->first())
        ) {
            return Redirect::to($this->profilePageUrl($method));
        }
    }

    protected function paymentMethods()
    {
        $countryId = ($user = $this->user()) ? $user->country_id : null;

        $countryId = post('country', $countryId);

        $methods = TypeModel::listApplicable($countryId);

        $methods = $methods->filter(function($method) {
            return $method->supportsPaymentProfiles();
        });

        return $methods;
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
     * Returns the profile page name as per configuration.
     * @return string
     */
    protected function profilePage()
    {
        return $this->property('profilePage');
    }

    /**
     * Returns a profile page URL for a payment method
     */
    public function profilePageUrl($method)
    {
        return $this->pageUrl($this->profilePage(), ['id' => $method->id]);
    }
}
