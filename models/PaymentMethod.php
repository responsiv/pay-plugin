<?php namespace Responsiv\Pay\Models;

use Model;
use ValidationException;
use Responsiv\Pay\Interfaces\PaymentMethod as PaymentMethodInterface;

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

    public static function listApplicable($countryId = null)
    {
        return self::isEnabled()->get();
    }

    public function renderPaymentForm($controller)
    {
        $this->beforeRenderPaymentForm($this);

        $paymentMethodFile = strtolower(class_basename($this->class_name));
        $partialName = 'pay/'.$paymentMethodFile;

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

}