<?php namespace Responsiv\Pay\Interfaces;

/**
 * This contract represents an Payment Object Status
 */
interface InvoiceStatus
{

    /**
     * Return the paid status code.
     * @return string
     */
    public function getPaidStatus();

    /**
     * Return the new status code.
     * @return string
     */
    public function getNewStatus();

    /**
     * Returns an array of all available statuses. Array key should be the status
     * code and value should be the status label. At the minimum these statuses
     * should exist:
     *
     * - new: New
     * - paid: Paid
     * 
     * @return array
     */
    public function listStatuses();

}
