<?php namespace Responsiv\Pay\Classes;

use File;
use Response;
use Cms\Classes\Theme;
use Cms\Classes\Partial;
use System\Classes\PluginManager;
use October\Rain\Support\Collection;
use Responsiv\Pay\Models\PaymentMethod as TypeModel;

/**
 * Manages payment gateways
 *
 * @package Responsiv.Pay
 * @author Responsiv Internet
 */
class GatewayManager
{
    use \October\Rain\Support\Traits\Singleton;

    /**
     * @var array Cache of registration callbacks.
     */
    protected $callbacks = [];

    /**
     * @var array List of registered gateways.
     */
    protected $gateways;

    /**
     * @var System\Classes\PluginManager
     */
    protected $pluginManager;

    /**
     * Initialize this singleton.
     */
    protected function init()
    {
        $this->pluginManager = PluginManager::instance();
    }

    /**
     * Loads the menu items from modules and plugins
     * @return void
     */
    protected function loadGateways()
    {
        /*
         * Load module items
         */
        foreach ($this->callbacks as $callback) {
            $callback($this);
        }

        /*
         * Load plugin items
         */
        $plugins = $this->pluginManager->getPlugins();

        foreach ($plugins as $id => $plugin) {
            if (!method_exists($plugin, 'registerPaymentGateways')) {
                continue;
            }

            $gateways = $plugin->registerPaymentGateways();
            if (!is_array($gateways)) {
                continue;
            }

            $this->registerGateways($id, $gateways);
        }
    }

    /**
     * Registers a callback function that defines a payment gateway.
     * The callback function should register gateways by calling the manager's
     * registerGateways() function. The manager instance is passed to the
     * callback function as an argument. Usage:
     *
     *     GatewayManager::registerCallback(function($manager) {
     *         $manager->registerGateways([...]);
     *     });
     *
     * @param callable $callback A callable function.
     * @return void
     */
    public function registerCallback(callable $callback)
    {
        $this->callbacks[] = $callback;
    }

    /**
     * Registers the payment gateways.
     * The argument is an array of the gateway classes.
     * @param string $owner Specifies the menu items owner plugin or module in the format Author.Plugin.
     * @param array $classes An array of the payment gateway classes.
     * @return void
     */
    public function registerGateways($owner, array $classes)
    {
        if (!$this->gateways) {
            $this->gateways = [];
        }

        foreach ($classes as $class => $alias) {
            $gateway = (object) [
                'owner' => $owner,
                'class' => $class,
                'alias' => $alias,
            ];

            $this->gateways[$alias] = $gateway;
        }
    }

    /**
     * Returns a list of the payment gateway classes.
     * @param boolean $asObject As a collection with extended information found in the class object.
     * @return array
     */
    public function listGateways($asObject = true)
    {
        if ($this->gateways === null) {
            $this->loadGateways();
        }

        if (!$asObject) {
            return $this->gateways;
        }

        /*
         * Enrich the collection with gateway objects
         */
        $collection = [];
        foreach ($this->gateways as $gateway) {
            if (!class_exists($gateway->class)) {
                continue;
            }

            $gatewayObj = new $gateway->class;
            $gatewayDetails = $gatewayObj->gatewayDetails();
            $collection[$gateway->alias] = (object) [
                'owner'       => $gateway->owner,
                'class'       => $gateway->class,
                'alias'       => $gateway->alias,
                'object'      => $gatewayObj,
                'name'        => array_get($gatewayDetails, 'name', 'Undefined'),
                'description' => array_get($gatewayDetails, 'description', 'Undefined'),
            ];
        }

        return new Collection($collection);
    }

    /**
     * Returns a list of the payment gateway objects
     * @return array
     */
    public function listGatewayObjects()
    {
        $collection = [];
        $gateways = $this->listGateways();

        foreach ($gateways as $gateway) {
            $collection[$gateway->alias] = $gateway->object;
        }

        return $collection;
    }

    /**
     * Returns a gateway based on its unique alias.
     */
    public function findByAlias($alias)
    {
        $gateways = $this->listGateways(false);

        if (!isset($gateways[$alias])) {
            return false;
        }

        return $gateways[$alias];
    }

    /**
     * Executes an entry point for registered gateways, defined in routes.php file.
     * @param  string $code Access point code
     * @param  string $uri  Remaining uri parts
     */
    public static function runAccessPoint($code = null, $uri = null)
    {
        $params = explode('/', $uri);

        $gateways = self::instance()->listGatewayObjects();
        foreach ($gateways as $gateway) {
            $points = $gateway->registerAccessPoints();

            if (isset($points[$code])) {
                return $gateway->{$points[$code]}($params);
            }
        }

        return Response::make('Access Forbidden', '403');
    }

    //
    // Partials
    //

    /**
     * Loops over each payment type and ensures the editing theme has a payment form partial,
     * if the partial does not exist, it will create one.
     * @return void
     */
    public static function createPartials()
    {
        $partials = Partial::lists('baseFileName', 'baseFileName');
        $paymentMethods = TypeModel::all();

        foreach ($paymentMethods as $paymentMethod) {
            $class = $paymentMethod->class_name;

            if (!$class || get_parent_class($class) != 'Responsiv\Pay\Classes\GatewayBase') {
                continue;
            }

            $paymentCode = strtolower(class_basename($class));

            $partialNames = [
                'payment_form.htm' => 'pay-gateway/'.$paymentCode,
                'profile_form.htm' => 'pay-gateway/'.$paymentCode.'-profile'
            ];

            foreach ($partialNames as $sourceFile => $partialName) {
                $partialExists = array_key_exists($partialName, $partials);
                $filePath = dirname(File::fromClass($class)).'/'.$paymentCode.'/'.$sourceFile;

                if (!$partialExists && File::exists($filePath)) {
                    self::createPartialFromFile($partialName, $filePath, Theme::getEditTheme());
                }
            }
        }
    }

    /**
     * Creates a partial using the contents of a specified file.
     * @param  string $name      New Partial name
     * @param  string $filePath  File containing partial contents
     * @param  string $themeCode Theme to create the partial
     * @return void
     */
    protected static function createPartialFromFile($name, $filePath, $themeCode)
    {
        $partial = Partial::inTheme($themeCode);

        $partial->fill([
            'fileName' => $name,
            'markup' => File::get($filePath)
        ]);

        $partial->save();
    }
}
