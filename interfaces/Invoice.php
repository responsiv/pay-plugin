<?php namespace Responsiv\Pay\Interfaces;

/**
 * This contract represents an Payment Object that will be used
 * to record instances where payment attempts are made.
 */
interface Invoice
{

    /**
     * Returns a unique identifier for this payment object.
     * @return string
     */
    public function getUniqueId();

    /**
     * Locates a Payment object by its unique identifier.
     * @param  string $hash
     * @return self
     */
    public function findByUniqueId($id = null);

    /**
     * Returns a hashed identifier for this payment object.
     * @return string
     */
    public function getUniqueHash();

    /**
     * Locates a Payment object by its hashed identifier.
     * @param  string $hash
     * @return self
     */
    public function findByUniqueHash($hash = null);

    /**
     * Should return a URL for viewing this invoice.
     * @return string
     */
    public function getReceiptUrl();

    /**
     * Returns an array with location information about the customer. Must contain:
     * 
     * - first_name: First name
     * - last_name: Last name
     * - email: Email address
     * - street_addr: Street address
     * - city: City / Province
     * - country: Country code (US)
     * - state: State code (FL)
     * - zip: Zip code
     * - phone: Telephone number
     * 
     * @return array
     */
    public function getCustomerDetails();

    /**
     * Returns an array of line items. Each line item is an array and must contain (at minimum):
     *
     * - description: Description of item
     * - quantity: Amount of items
     * - price: Price for a single item
     * - total: Total price for all items
     * 
     * @return array
     */
    public function getLineItemDetails();

    /**
     * Returns an array of totals. Must contain:
     *
     * - total: Total cost amount
     * - subtotal: Total cost without tax
     * - tax: Total tax cost
     * - currency: Currency code (USD)
     * 
     * @return array
     */
    public function getTotalDetails();

    /**
     * Check if the payment has been processed on this Payment.
     * It should be cached to allow multiple calls.
     * @param  boolean $force Ignore the cache.
     * @return boolean        Returns true if the payment is processed.
     */
    public function isPaymentProcessed($force = false);

    /**
     * Flags this Payment as having payment processed. This method should
     * only be callable once.
     * @return boolean Returns false if method has already been called.
     */
    public function markAsPaymentProcessed();

    /**
     * Returns a configured payment method object used for this Payment object.
     * This should return a configured Model that implements the Responsiv\Pay\Classes\GatewayBase behavior.
     * @return Model
     */
    public function getPaymentMethod();

    /**
     * Adds a log record to the invoice payment attempts log.
     * @param string $message           Log message.
     * @param bool   $isSuccess         Indicates that the attempt was successful.
     * @param array  $requestArray      An array containing data posted to the payment gateway.
     * @param array  $responseArray     An array containing data received from the payment gateway.
     * @param string $responseText      Raw gateway response text.
     * @return void
     */
    public function logPaymentAttempt(
        $message,
        $isSuccess,
        $requestArray,
        $responseArray,
        $responseText
    );

    /**
     * Updates the invoice status to the supplied code.
     * @param  string $statusCode
     * @return void
     */
    public function updateInvoiceStatus($statusCode);

}