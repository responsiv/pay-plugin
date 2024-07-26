<?php namespace Responsiv\Pay\Components;

use Auth;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use Responsiv\Pay\Models\PaymentMethod as TypeModel;

/**
 * PayProfiles component
 */
class PayProfiles extends ComponentBase
{
    /**
     * componentDetails
     */
    public function componentDetails()
    {
        return [
            'name' => 'Payment Profiles',
            'description' => 'Displays a list of payment profiles belonging to a user'
        ];
    }

    /**
     * defineProperties
     */
    public function defineProperties()
    {
        return [];
    }

    /**
     * getProfilePageOptions
     */
    public function getProfilePageOptions()
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    /**
     * onRun
     */
    public function onRun()
    {
        $this->page['user'] = $this->user();
        $this->page['paymentMethods'] = $this->paymentMethods();
    }

    /**
     * paymentMethods
     */
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
     * user returns the logged in user, if available, and touches
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
}
