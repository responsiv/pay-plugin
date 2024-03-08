<?php namespace Responsiv\Pay\Classes;

use App;
use File;
use Response;
use Cms\Classes\Theme;
use Cms\Classes\Partial;
use System\Classes\PluginManager;
use October\Rain\Support\Collection;
use Responsiv\Pay\Models\PaymentMethod;

/**
 * GatewayManager class manages payment gateways
 *
 * @package responsiv/pay
 * @author Alexey Bobkov, Samuel Georges
 */
class GatewayManager
{
    /**
     * @var PluginManager pluginManager
     */
    protected $pluginManager;

    /**
     * @var array gateways of registered payment gateways.
     */
    protected $gateways;

    /**
     * __construct this class
     */
    public function __construct()
    {
        $this->pluginManager = PluginManager::instance();
    }

    /**
     * instance creates a new instance of this singleton
     */
    public static function instance(): static
    {
        return App::make('pay.gateways');
    }

    /**
     * loadGateways registered in the system
     */
    protected function loadGateways()
    {
        if (!$this->gateways) {
            $this->gateways = [];
        }

        $methodValues = $this->pluginManager->getRegistrationMethodValues('registerPaymentGateways');

        foreach ($methodValues as $id => $types) {
            $this->registerGateways($id, $types);
        }
    }

    /**
     * registerGateways registers the payment gateways.
     * The argument is an array of the gateway classes.
     * @param string $owner Specifies the menu items owner plugin or module in the format Author.Plugin.
     * @param array $classes An array of the payment gateway classes.
     * @return void
     */
    public function registerGateways($owner, array $classes)
    {
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
     * listGateways returns a list of the payment gateway classes. As object of a
     * collection with extended information found in the class object.
     */
    public function listGateways(bool $asObject = true)
    {
        if ($this->gateways === null) {
            $this->loadGateways();
        }

        if (!$asObject) {
            return $this->gateways;
        }

        // Bless the collection with gateway objects
        $collection = [];
        foreach ($this->gateways as $gateway) {
            if (!class_exists($gateway->class)) {
                continue;
            }

            $gatewayObj = new $gateway->class;
            $driverDetails = $gatewayObj->driverDetails();
            $collection[$gateway->alias] = (object) [
                'owner' => $gateway->owner,
                'class' => $gateway->class,
                'alias' => $gateway->alias,
                'object' => $gatewayObj,
                'name' => array_get($driverDetails, 'name', 'Undefined'),
                'description' => array_get($driverDetails, 'description', 'Undefined'),
            ];
        }

        return new Collection($collection);
    }

    /**
     * listGatewayObjects returns a list of the payment gateway objects
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
     * findByAlias returns a gateway based on its unique alias.
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
     * runAccessPoint executes an entry point for registered gateways, defined in routes.php file.
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
     * createPartials loops over each payment type and ensures the editing theme has a payment form partial,
     * if the partial does not exist, it will create one.
     * @return void
     */
    public static function createPartials()
    {
        $partials = Partial::lists('baseFileName', 'baseFileName');
        $paymentMethods = PaymentMethod::all();

        foreach ($paymentMethods as $paymentMethod) {
            $class = $paymentMethod->class_name;

            if (!is_a($class, \Responsiv\Pay\Classes\GatewayBase::class, true)) {
                continue;
            }

            $paymentCode = strtolower(class_basename($class));

            $partialNames = [
                'payment_form.htm' => 'pay/'.$paymentCode,
                'profile_form.htm' => 'pay/'.$paymentCode.'-profile'
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
     * createPartialFromFile creates a partial using the contents of a specified file.
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
