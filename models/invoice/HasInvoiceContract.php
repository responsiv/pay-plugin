<?php namespace Responsiv\Pay\Models\Invoice;

use Cms;
use Event;
use Cms\Classes\Controller;
use Responsiv\Pay\Models\Setting;
use Responsiv\Pay\Models\InvoiceStatus;
use Responsiv\Pay\Models\InvoiceStatusLog;
use Responsiv\Pay\Models\InvoicePaymentLog;

/**
 * HasInvoiceContract
 */
trait HasInvoiceContract
{
    /**
     * getUniqueId
     */
    public function getUniqueId()
    {
        return Setting::get('invoice_prefix') . $this->id;
    }

    /**
     * findByUniqueId
     */
    public function findByUniqueId($id = null)
    {
        if (!$id) {
            return null;
        }

        return static::applyInvoiceNumber($id)->first();
    }

    /**
     * getUniqueHash
     */
    public function getUniqueHash()
    {
        return $this->hash;
    }

    /**
     * findByUniqueHash
     */
    public function findByUniqueHash($hash = null)
    {
        return static::whereHash($hash)->first();
    }

    /**
     * getReceiptUrl
     */
    public function getReceiptUrl()
    {
        if ($link = $this->payment_method?->getReceiptPage()) {
            $controller = Controller::getController() ?: new Controller;

            // @todo this is a pagefinder link
            // \Cms\Classes\PageManager::url($link, ...)
            return $controller->pageUrl($link, [
                'id' => $this->id,
                'hash' => $this->hash,
            ]);
        }

        return Cms::entryUrl('payment', ['hash' => $this->hash]);
    }

    /**
     * getCustomPaymentPageUrl
     */
    public function getCustomPaymentPageUrl()
    {
        if ($link = $this->payment_method->getCustomPaymentPage()) {
            $controller = Controller::getController() ?: new Controller;

            // @todo this is a pagefinder link
            // \Cms\Classes\PageManager::url($link, ...)
            return $controller->pageUrl($link, [
                'id' => $this->id,
                'hash' => $this->hash,
            ]);
        }

        return Cms::entryUrl('payment', ['hash' => $this->hash]);
    }

    /**
     * getCustomerDetails
     */
    public function getCustomerDetails()
    {
        $details = [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city' => $this->city,
            'zip' => $this->zip,
            'state_id' => $this->state ? $this->state->code : null,
            'state' => $this->state ? $this->state->name : null,
            'country_id' => $this->country ? $this->country->code : null,
            'country' => $this->country ? $this->country->name : null
        ];

        return $details;
    }

    /**
     * getLineItemDetails
     */
    public function getLineItemDetails()
    {
        $details = [];

        foreach ($this->items as $item) {
            $details[] = [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'total' => $item->total,
            ];
        }

        return $details;
    }

    /**
     * getTotalDetails
     */
    public function getTotalDetails()
    {
        $details = [
            'total' => $this->total,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'currency' => $this->currency?->code,
        ];

        return $details;
    }

    /**
     * isPaymentProcessed
     */
    public function isPaymentProcessed($force = false)
    {
        if ($force) {
            return $this->where('id', $this->id)->value('processed_at');
        }

        return $this->processed_at;
    }

    /**
     * markAsPaymentProcessed
     */
    public function markAsPaymentProcessed($comment = null)
    {
        if (!$isPaid = $this->isPaymentProcessed(true)) {
            $now = $this->processed_at = $this->freshTimestamp();

            // Instant update here in case a simultaneous request causes invalid data
            $this->newQuery()->where('id', $this->id)->update(['processed_at' => $now]);

            Event::fire('responsiv.pay.invoicePaid', [$this]);

            // Never allow a paid invoice to be thrown away
            $this->is_throwaway = false;

            $this->save();

            $this->updateInvoiceStatus(InvoiceStatus::STATUS_PAID, $comment);
        }

        return !$isPaid;
    }

    /**
     * getPaymentMethod
     */
    public function getPaymentMethod()
    {
        return $this->payment_method;
    }

    /**
     * logPaymentAttempt
     */
    public function logPaymentAttempt(
        $message,
        $isSuccess,
        $requestArray,
        $responseArray,
        $responseText
    ) {
        if ($payMethod = $this->getPaymentMethod()) {
            $info = $payMethod->driverDetails();
            $methodName = $info['name'];
        }
        else {
            $methodName = 'Unspecified';
        }

        $options = [
            'isSuccess' => $isSuccess,
            'methodName' => $methodName,
            'requestArray' => $requestArray,
            'responseArray' => $responseArray,
            'responseText' => $responseText
        ];

        InvoicePaymentLog::createRecord($this, $message, $options);
    }

    /**
     * updateInvoiceStatus
     */
    public function updateInvoiceStatus($statusCode, $comment = null)
    {
        return InvoiceStatusLog::createRecord($statusCode, $this, $comment);
    }
}
