<?php namespace Responsiv\Pay\Models\Tax;

use Responsiv\Pay\Classes\TaxLocation;

/**
 * HasGlobalContext
 *
 * @package responsiv\pay
 * @author Alexey Bobkov, Samuel Georges
 */
trait HasGlobalContext
{
    /**
     * @var \Responsiv\Pay\Classes\TaxLocation|null locationContext
     */
    protected static $locationContext;

    /**
     * @var \User\Models\User|null userContext
     */
    protected static $userContext = null;

    /**
     * @var bool taxExempt
     */
    protected static $taxExempt = false;

    /**
     * @var bool pricesIncludeTax
     */
    protected static $pricesIncludeTax = false;

    /**
     * setLocationContext
     */
    public static function setLocationContext(?TaxLocation $locationContext)
    {
        static::$locationContext = $locationContext;
    }

    /**
     * setUserContext
     */
    public static function setUserContext($userContext)
    {
        static::$userContext = $userContext;
    }

    /**
     * setTaxExempt
     */
    public static function setTaxExempt($taxExempt)
    {
        static::$taxExempt = (bool) $taxExempt;
    }

    /**
     * setPricesIncludeTax
     */
    public static function setPricesIncludeTax($pricesIncludeTax)
    {
        static::$pricesIncludeTax = (bool) $pricesIncludeTax;
    }

    /**
     * withContext executes a callback with a temporary tax context,
     * restoring the previous context when done.
     */
    public static function withContext(?TaxLocation $location, bool $pricesIncludeTax, callable $callback)
    {
        $prevLocation = static::$locationContext;
        $prevPricesIncludeTax = static::$pricesIncludeTax;
        $prevUserContext = static::$userContext;
        $prevTaxExempt = static::$taxExempt;

        try {
            static::$locationContext = $location;
            static::$pricesIncludeTax = $pricesIncludeTax;
            return $callback();
        }
        finally {
            static::$locationContext = $prevLocation;
            static::$pricesIncludeTax = $prevPricesIncludeTax;
            static::$userContext = $prevUserContext;
            static::$taxExempt = $prevTaxExempt;
        }
    }
}
