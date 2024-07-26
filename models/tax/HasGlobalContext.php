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
}
