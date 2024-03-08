<?php namespace Responsiv\Pay\Models;

use October\Rain\Database\ExpandoModel;
use ApplicationException;

/**
 * PaymentMethod Model
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $class_name
 * @property string $description
 * @property array $config_data
 * @property bool $is_enabled
 * @property bool $is_enabled_edit
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon $created_at
 *
 * @package october\shop
 * @author Alexey Bobkov, Samuel Georges
 */
class PaymentMethod extends ExpandoModel
{
    use \System\Traits\KeyCodeModel;
    use \October\Rain\Database\Traits\Purgeable;
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table associated with the model
     */
    public $table = 'responsiv_pay_methods';

    /**
     * @var string expandoColumn name to store the data
     */
    protected $expandoColumn = 'config_data';

    /**
     * @var array expandoPassthru attributes that should not be serialized
     */
    protected $expandoPassthru = [
        'name',
        'code',
        'class_name',
        'description',
        'is_enabled',
        'is_enabled_edit',
        'updated_at',
        'created_at',
    ];

    /**
     * @var array purgeable list of attribute names which should not be saved to the database
     */
    protected $purgeable = ['driver_name'];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'name' => 'required'
    ];

    /**
     * @var array belongsToMany
     */
    public $belongsToMany = [
        'countries' => [
            \RainLab\Location\Models\Country::class,
            'table' => 'shop_payment_methods_countries'
        ],
        'user_groups' => [
            \RainLab\User\Models\UserGroup::class,
            'table' => 'shop_payment_methods_user_groups'
        ]
    ];

    /**
     * applyDriverClass extends this class with the driver class
     * @param  string $class Class name
     * @return boolean
     */
    public function applyDriverClass($class = null)
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
        $this->driver_name = array_get($this->driverDetails(), 'name', 'Unknown');
        return true;
    }

    /**
     * getDriverObject returns the gateway class extension object.
     * @param  string $class Class name
     * @return \Responsiv\Shop\Classes\PaymentTypeBase
     */
    public function getDriverObject($class = null)
    {
        if (!$class) {
            $class = $this->class_name;
        }

        return $this->asExtension($class);
    }

    /**
     * afterFetch
     */
    public function afterFetch()
    {
        $this->applyDriverClass();
    }

    /**
     * beforeValidate
     */
    public function beforeValidate()
    {
        if ($this->applyDriverClass()) {
            $this->getDriverObject()->validateDriverHost($this);
        }
    }

    /**
     * scopeApplyEnabled
     */
    public function scopeApplyEnabled($query, $isEdit = false)
    {
        if ($isEdit) {
            return $query->where('is_enabled_edit', true);
        }

        return $query
            ->where('is_enabled', true)
        ;
    }

    /**
     * scopeApplyCountry
     */
    public function scopeApplyCountry($query, $countryId)
    {
        return $query->where(function($q) use ($countryId) {
            $q->doesntHave('countries');
            $q->orWhereRelation('countries', 'id', $countryId);
        });
    }

    /**
     * scopeApplyUserGroup
     */
    public function scopeApplyUserGroup($query, $userGroupId)
    {
        return $query->where(function($q) use ($userGroupId) {
            $q->doesntHave('user_groups');
            $q->orWhereRelation('user_groups', 'id', $userGroupId);
        });
    }

    /**
     * listApplicable payment methods
     */
    public static function listApplicable(array $options = [])
    {
        extract(array_merge([
            'countryId' => null,
            'totalPrice' => null,
        ], $options));

        $query = self::applyEnabled();

        if ($countryId) {
            $query = $query->applyCountry($countryId);
        }

        return $query->get();
    }

    /**
     * renderPaymentForm
     */
    public function renderPaymentForm($controller)
    {
        $this->beforeRenderPaymentForm();

        $paymentMethodFile = strtolower(class_basename($this->class_name));
        $partialName = 'pay/'.$paymentMethodFile;

        return $controller->renderPartial($partialName);
    }

    /**
     * renderPaymentProfileForm
     */
    public function renderPaymentProfileForm($controller)
    {
        $this->beforeRenderPaymentProfileForm();

        $paymentMethodFile = strtolower(class_basename($this->class_name));
        $partialName = 'pay/'.$paymentMethodFile.'-profile';

        return $controller->renderPartial($partialName);
    }

    /**
     * {@inheritDoc}
     */
    public function getDriverClass()
    {
        return $this->class_name;
    }

    /**
     * getDataTableOptions
     */
    public function getDataTableOptions($attribute, $field, $data)
    {
        return $this->getDriverObject()->getDataTableOptions($attribute, $field, $data);
    }

    //
    // Payment Profiles
    //

    /**
     * findUserProfile finds and returns a user payment profile for this payment method.
     * @param \RainLab\User\Models\User $user Specifies user to find a profile for.
     * @return \Responsiv\Shop\Models\UserProfile Returns the user profile object. Returns NULL if the payment profile doesn't exist.
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
     * initUserProfile initializes a new empty user payment profile.
     * This method should be used by payment methods internally.
     * @param \RainLab\User\Models\User Specifies a user object to initialize a profile for.
     * @return \Responsiv\Shop\Models\UserProfile Returns the user payment profile object.
     */
    public function initUserProfile($user)
    {
        $obj = new UserProfile;
        $obj->user_id = $user->id;
        $obj->payment_method_id = $this->id;
        return $obj;
    }

    /**
     * profileExists checks whether a user profile for this payment method and a given user exists.
     * @param \RainLab\User\Models\User $user A user object to find a profile for.
     * @return boolean Returns TRUE if a profile exists. Returns FALSE otherwise.
     */
    public function profileExists($user)
    {
        return !!$this->findUserProfile($user);
    }

    /**
     * deleteUserProfile deletes a user payment profile.
     * The method deletes the payment profile from the database and from the payment gateway.
     * @param \RainLab\User\Models\User $user Specifies a user object to delete a profile for.
     */
    public function deleteUserProfile($user)
    {
        $gatewayObj = $this->getDriverObject();

        $profile = $this->findUserProfile($user);

        if (!$profile) {
            throw new ApplicationException('User payment profile not found!');
        }

        $gatewayObj->deleteUserProfile($user, $profile);

        $profile->delete();
    }
}
