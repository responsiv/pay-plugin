<?php namespace Responsiv\Pay\Classes;

use Responsiv\Pay\Models\Tax as TaxModel;

/**
 * TaxItem represents a taxable item
 */
class TaxItem
{
    /**
     * @var mixed taxClassId
     */
    public $taxClassId;

    /**
     * @var int quantity
     */
    public $quantity;

    /**
     * @var int unitPrice
     */
    public $unitPrice;

    /**
     * getTaxModel returns a tax model for this item
     */
    public function getTaxModel(): ?TaxModel
    {
        return $this->taxClassId ? TaxModel::findByKey($this->taxClassId) : null;
    }
}
