<?php namespace Responsiv\Pay\Models;

use Str;
use Model;

/**
 * Payment Type Model
 */
class Type extends Model
{

    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_pay_types';

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
        'countries' => ['RainLab\User\Models\Country', 'table' => 'responsiv_pay_types_countries']
    ];

    /**
     * Extends this class with the gateway class
     * @param  string $class Class name
     * @return boolean
     */
    public function applyGatewayClass($class = null)
    {
        if (!$class)
            $class = $this->class_name;

        if (!$class)
            return false;

        if (!$this->isClassExtendedWith($class))
            $this->extendClassWith($class);

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
        if (!$this->applyGatewayClass())
            return;
    }

    public function beforeCreate()
    {
        $this->hash = $this->createHash();
        while ($this->newQuery()->where('hash', $this->hash)->count() > 0) {
            $this->hash = $this->createHash();
        }
    }

    public function beforeSave()
    {
        $configData = [];
        $fieldConfig = $this->getFieldConfig();
        $fields = isset($fieldConfig->fields) ? $fieldConfig->fields : [];

        foreach ($fields as $name => $config) {
            if (!array_key_exists($name, $this->attributes))
                continue;

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

    protected function createHash()
    {
        return md5(uniqid('invoice', microtime()));
    }

    public function renderPaymentForm($controller)
    {
        $this->beforeRenderPaymentForm($this);

        $paymentTypeFile = strtolower(Str::getRealClass($this->class_name));
        $partialName = 'pay/'.$paymentTypeFile;

        return $controller->renderPartial($partialName);
    }

}