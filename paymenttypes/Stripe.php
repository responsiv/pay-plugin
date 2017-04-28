<?php namespace Responsiv\Pay\PaymentTypes;

use Request;
use Backend;
use Redirect;
use Validator;
use Cms\Classes\Page;
use Responsiv\Pay\Classes\GatewayBase;
use Omnipay\Omnipay;
use Omnipay\Common\CreditCard;
use SystemException;
use ApplicationException;
use ValidationException;
use Exception;

class Stripe extends GatewayBase
{

    /**
     * {@inheritDoc}
     */
    public function gatewayDetails()
    {
        return [
            'name'        => 'Stripe',
            'description' => 'Stripe payment method with payment form hosted on your server.'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function defineFormFields()
    {
        return 'fields.yaml';
    }

    /**
     * {@inheritDoc}
     */
    public function defineValidationRules()
    {
        return [
            'secret_key' => 'required',
            'publishable_key' => 'required',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function initConfigData($host)
    {
        $host->invoice_status = $this->createInvoiceStatusModel()->getStatusPaid();
    }

    /**
     * Status field options.
     */
    public function getInvoiceStatusOptions()
    {
        return $this->createInvoiceStatusModel()->listStatuses();
    }

    /**
     * {@inheritDoc}
     */
    public function processPaymentForm($data, $invoice)
    {
        $host = $this->model;

        $validation = $this->makeValidationObject($data);

        try {
            if ($validation->fails()) {
                throw new ValidationException($validation);
            }
        }
        catch (Exception $ex) {
            $invoice->logPaymentAttempt($ex->getMessage(), 0, [], [], null);
            throw $ex;
        }

        if (!$paymentMethod = $invoice->getPaymentMethod()) {
            throw new ApplicationException('Payment method not found');
        }

        /*
         * Send payment request
         */
        $gateway = $this->makeSdk();

        $formData = $this->makeCardData($data);

        $totals = (object) $invoice->getTotalDetails();

        $response = $gateway->purchase([
            'amount'   => $totals->total,
            'currency' => $totals->currency,
            'card'     => $formData
        ])->send();

        // Clean the credit card number
        $formData['number'] = '...'.substr($formData['number'], -4);

        if ($response->isSuccessful()) {
            $invoice->logPaymentAttempt('Successful payment', 1, $formData, null, null);
            $invoice->markAsPaymentProcessed();
            $invoice->updateInvoiceStatus($paymentMethod->invoice_status);
        }
        else {
            $errorMessage = $response->getMessage();
            $invoice->logPaymentAttempt($errorMessage, 0, $formData, null, null);
            throw new ApplicationException($errorMessage);
        }

    }

    //
    // Payment Profiles
    //

    /**
     * {@inheritDoc}
     */
    public function supportsPaymentProfiles()
    {
        return true;
    }

    public function updateUserProfile($user, $data)
    {
        $host = $this->model;
        $validation = $this->makeValidationObject($data);

        if ($validation->fails()) {
            throw new ValidationException($validation);
        }

        $gateway = $this->makeSdk();
        $formData = $this->makeCardData($data);
        $profile = $host->findUserProfile($user);
        $profileData = (array) $profile ? $profile->profile_data : [];

        //
        // Customer
        //

        $newCustomerRequired = !$profile || !isset($profile->profile_data['customer_id']);

        if (!$newCustomerRequired) {
            $customerId = $profile->profile_data['customer_id'];
            $response = $gateway->fetchCustomer(['customerReference' => $customerId])->send();
            $responseData = $response->getData();

            if ($response->isSuccessful()) {
                if (isset($responseData['deleted'])) {
                    $newCustomerRequired = true;
                }
            }
            else {
                $newCustomerRequired = true;
            }
        }

        if ($newCustomerRequired) {
            $response = $gateway->createCustomer([
                'description'  => $user->name . ' ' . $user->surname,
                'email'        => $user->email,
            ])->send();

            if ($response->isSuccessful()) {
                $customerId = $response->getCustomerReference();
                $profileData['customer_id'] = $customerId;
            }
            else {
                throw new ApplicationException('Gateway createCustomer failed');
            }
        }

        //
        // Card
        //

        $newCardRequired = !$profile || !isset($profile->profile_data['card_id']);
        $newCard = new CreditCard($formData);

        if (!$newCardRequired) {
            $cardId = $profile->profile_data['card_id'];

            $response = $gateway->updateCard([
                'card'              => $newCard,
                'cardReference'     => $cardId,
                'customerReference' => $customerId,
            ])->send();
            $responseData = $response->getData();

            if (!$response->isSuccessful()) {
                $newCardRequired = true;
            }
        }

        if ($newCardRequired) {
            $response = $gateway->createCard([
                'card'              => $newCard,
                'customerReference' => $customerId,
            ])->send();

            if ($response->isSuccessful()) {
                $cardId = $response->getCardReference();
                $profileData['card_id'] = $customerId;
            }
            else {
                throw new ApplicationException('Gateway createCard failed');
            }
        }

        if (!$profile) {
            $profile = $host->initUserProfile($user);
        }

        $profile->setProfileData([
            'card_id'     => $cardId,
            'customer_id' => $customerId,
        ], array_get($formData, 'number'));

        return $profile;
    }

    //
    // Helpers
    //

    protected function makeValidationObject($data)
    {
        $rules = [
            'first_name'              => 'required',
            'last_name'               => 'required',
            'expiry_date_month'       => ['required', 'regex:/^[0-9]*$/'],
            'expiry_date_year'        => ['required', 'regex:/^[0-9]*$/'],
            'card_number'             => ['required', 'regex:/^[0-9]*$/'],
            'CVV'                     => ['required', 'regex:/^[0-9]*$/'],
        ];

        return Validator::make($data, $rules);
    }

    protected function makeCardData($data)
    {
        return [
            'firstName'   => array_get($data, 'first_name'),
            'lastName'    => array_get($data, 'last_name'),
            'number'      => array_get($data, 'card_number'),
            'expiryMonth' => array_get($data, 'expiry_date_month'),
            'expiryYear'  => array_get($data, 'expiry_date_year'),
            'cvv'         => array_get($data, 'CVV'),
        ];
    }

    protected function makeSdk()
    {
        $host = $this->model;

        $gateway = Omnipay::create('Stripe');

        $gateway->initialize([
            'apiKey' => $host->secret_key
        ]);

        return $gateway;
    }
}
