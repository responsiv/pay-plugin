<?php namespace Responsiv\Pay\Models;

use Model;
use Responsiv\Pay\Interfaces\PaymentMethod as PaymentMethodInterface;
use ApplicationException;
use ValidationException;

/**
 * Payment Method Model
 */
class PaymentMethod extends Model implements PaymentMethodInterface
{
    use \October\Rain\Database\Traits\Purgeable;
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_pay_methods';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['config_data'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [];

    /**
     * @var array List of attribute names which are json encoded and decoded from the database.
     */
    protected $jsonable = ['config_data'];

    /**
     * @var array List of attribute names which should not be saved to the database.
     */
    protected $purgeable = ['gateway_name'];

    /**
     * @var array Validation rules
     */
    public $rules = [
        'name' => 'required'
    ];

    /**
     * @var array Relations
     */
    public $belongsToMany = [
        'countries' => ['RainLab\Location\Models\Country', 'table' => 'responsiv_pay_methods_countries']
    ];

    /**
     * @var self Default method cache.
     */
    protected static $defaultMethod;

    /**
     * Extends this class with the gateway class
     * @param  string $class Class name
     * @return boolean
     */
    public function applyGatewayClass($class = null)
    {
        if (!$class) {
            $class = $this->class_name;
        }

        if (!$class) {
            return false;
        }

        if (!$this->isClassExtendedWith($class)) {
            $this->extendClassWith($class);
        }

        $this->class_name = $class;
        $this->gateway_name = array_get($this->gatewayDetails(), 'name', 'Unknown');
        return true;
    }

    /**
     * Returns the gateway class extension object.
     * @param  string $class Class name
     * @return \Responsiv\Pay\Classes\GatewayBase
     */
    public function getGatewayObject($class = null)
    {
        if (!$class) {
            $class = $this->class_name;
        }

        return $this->asExtension($class);
    }

    public function afterFetch()
    {
        $this->applyGatewayClass();

        $this->attributes = array_merge($this->config_data, $this->attributes);
    }

    public function beforeValidate()
    {
        if (!$this->applyGatewayClass()) {
            return;
        }
    }

    public function beforeSave()
    {
        $configData = [];
        $fieldConfig = $this->getFieldConfig();
        $fields = isset($fieldConfig->fields) ? $fieldConfig->fields : [];

        foreach ($fields as $name => $config) {
            if (!array_key_exists($name, $this->attributes)) {
                continue;
            }

            $configData[$name] = $this->attributes[$name];
            unset($this->attributes[$name]);
        }

        $this->config_data = $configData;
    }

    public function scopeIsEnabled($query)
    {
        return $query
            ->whereNotNull('is_enabled')
            ->where('is_enabled', 1)
        ;
    }

    public function scopeApplyCountry($query, $countryId = null)
    {
        return $query->where(function($q) use ($countryId) {
            $q->has('countries', '=', 0);
            $q->orWhereHas('countries', function($q) use ($countryId) {
                $q->where('id', $countryId);
            });
        });
    }

    public static function listApplicable($countryId = null)
    {
        $query = self::isEnabled();

        if ($countryId) {
            $query = $query->applyCountry($countryId);
        }

        return $query->get();
    }

    public function renderPaymentForm($controller)
    {
        $this->beforeRenderPaymentForm();

        $paymentMethodFile = strtolower(class_basename($this->class_name));
        $partialName = 'pay-gateway/'.$paymentMethodFile;

        return $controller->renderPartial($partialName);
    }

    public function renderPaymentProfileForm($controller)
    {
        $this->beforeRenderPaymentProfileForm();

        $paymentMethodFile = strtolower(class_basename($this->class_name));
        $partialName = 'pay-gateway/'.$paymentMethodFile.'-profile';

        return $controller->renderPartial($partialName);
    }

    public function makeDefault()
    {
        if (!$this->is_enabled) {
            throw new ValidationException(['is_enabled' => sprintf('"%s" is disabled and cannot be set as default.', $this->name)]);
        }

        $this->newQuery()->where('id', $this->id)->update(['is_default' => true]);
        $this->newQuery()->where('id', '<>', $this->id)->update(['is_default' => false]);
    }

    /**
     * Returns the default payment gateway.
     * @param  int $countryId
     * @return self
     * @todo Apply a filter for country.
     */
    public static function getDefault($countryId = null)
    {
        if (self::$defaultMethod !== null) {
            return self::$defaultMethod;
        }

        $defaultMethod = self::isEnabled()->where('is_default', true)->first();

        /*
         * If no default is found, find the first method and make it the default.
         */
        if (!$defaultMethod) {
            $defaultMethod = self::isEnabled()->first();
            if ($defaultMethod) {
                $defaultMethod->makeDefault();
            }
        }

        return self::$defaultMethod = $defaultMethod;
    }

    /**
     * {@inheritDoc}
     */
    public function getGatewayClass()
    {
        return $this->class_name;
    }

    //
    // Payment Profiles
    //

    /**
     * Finds and returns a user payment profile for this payment method.
     * @param \RainLab\User\Models\User $user Specifies user to find a profile for.
     * @return \RainLab\Pay\Models\UserProfile Returns the user profile object. Returns NULL if the payment profile doesn't exist.
     */
    public function findUserProfile($user)
    {
        if (!$user) {
            return null;
        }

        return UserProfile::where('user_id', $user->id)
            ->where('payment_method_id', $this->id)
            ->first()
        ;
    }
    
    /**
     * Initializes a new empty user payment profile. 
     * This method should be used by payment methods internally. 
     * @param \RainLab\User\Models\User Specifies a user object to initialize a profile for.
     * @return \RainLab\Pay\Models\UserProfile Returns the user payment profile object.
     */
    public function initUserProfile($user)
    {
        $obj = new UserProfile;
        $obj->user_id = $user->id;
        $obj->payment_method_id = $this->id;
        return $obj;
    }

    /**
     * Checks whether a user profile for this payment method and a given user exists.
     * @param \RainLab\User\Models\User $user A user object to find a profile for.
     * @return boolean Returns TRUE if a profile exists. Returns FALSE otherwise.
     */
    public function profileExists($user)
    {
        return !!$this->findUserProfile($user);
    }

    /**
     * Deletes a user payment profile.
     * The method deletes the payment profile from the database and from the payment gateway.
     * @param \RainLab\User\Models\User $user Specifies a user object to delete a profile for.
     */
    public function deleteUserProfile($user)
    {
        $gatewayObj = $this->getGatewayObject();

        $profile = $this->findUserProfile($user);

        if (!$profile) {
            throw new ApplicationException('User payment profile not found!');
        }

        $gatewayObj->deleteUserProfile($user, $profile);

        $profile->delete();
    }
}
