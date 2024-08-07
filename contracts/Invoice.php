<?php namespace Responsiv\Pay\Contracts;

/**
 * Invoice contract represents an Payment Object that will be used
 * to record instances where payment attempts are made.
 */
interface Invoice
{
    /**
     * getUniqueId returns a unique identifier for this payment object.
     * @return string
     */
    public function getUniqueId();

    /**
     * findByUniqueId locates a Payment object by its unique identifier.
     * @param  string $hash
     * @return self
     */
    public function findByUniqueId($id = null);

    /**
     * getUniqueHash returns a hashed identifier for this payment object.
     * @return string
     */
    public function getUniqueHash();

    /**
     * findByUniqueHash locates a Payment object by its hashed identifier.
     * @param  string $hash
     * @return self
     */
    public function findByUniqueHash($hash = null);

    /**
     * getReceiptUrl should return a URL for viewing this invoice.
     * @return string
     */
    public function getReceiptUrl();

    /**
     * getCustomPaymentPageUrl should return a URL if a custom payment page is used.
     * @return string
     */
    public function getCustomPaymentPageUrl();

    /**
     * getCustomerDetails returns an array with location information about the customer. Must contain:
     *
     * - first_name: First Name
     * - last_name: Last Name
     * - email: Email Address
     * - address_line1: Street Address (Line 1)
     * - address_line2: Street Address (Line 2)
     * - city: City / Province
     * - country: Country Code (US)
     * - state: State Code (FL)
     * - zip: Zip Code
     * - phone: Telephone Number
     *
     * @return array
     */
    public function getCustomerDetails();

    /**
     * getLineItemDetails returns an array of line items. Each line item is an array and must contain (at minimum):
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
     * getTotalDetails returns an array of totals. Must contain:
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
     * isPaymentProcessed checks if the payment has been processed on this Payment.
     * It should be cached to allow multiple calls.
     * @param  boolean $force Ignore the cache.
     * @return boolean        Returns true if the payment is processed.
     */
    public function isPaymentProcessed($force = false);

    /**
     * markAsPaymentProcessed flags this Payment as having payment processed. This method should
     * only be callable once.
     * @return boolean Returns false if method has already been called.
     */
    public function markAsPaymentProcessed();

    /**
     * getPaymentMethod returns a configured payment method object used for this Payment object.
     * This should return a configured Model that implements the Responsiv\Pay\Classes\GatewayBase behavior.
     * @return Model
     */
    public function getPaymentMethod();

    /**
     * logPaymentAttempt adds a log record to the invoice payment attempts log.
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
     * updateInvoiceStatus updates the invoice status to the supplied code.
     * @param  string $statusCode
     * @param  string $comment
     * @return bool
     */
    public function updateInvoiceStatus($statusCode, $comment = null);
}
