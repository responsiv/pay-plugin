<?php namespace Responsiv\Pay\Classes;

use Validator;
use RainLab\User\Models\User;
use RainLab\UserPlus\Models\UserAddress;
use RainLab\Location\Models\State;
use RainLab\Location\Models\Country;
use October\Rain\Element\ElementBase;

/**
 * TaxLocation represents a location used for tax calculation
 *
 * @method TaxLocation fieldPrefix(string $fieldPrefix) fieldPrefix
 * @method TaxLocation firstName(string $firstName) firstName
 * @method TaxLocation lastName(string $lastName) lastName
 * @method TaxLocation email(string $email) email
 * @method TaxLocation company(string $company) company
 * @method TaxLocation phone(string $phone) phone
 * @method TaxLocation city(string $city) city
 * @method TaxLocation zip(string $zip) zip
 * @method TaxLocation addressLine1(string $addressLine1) addressLine1
 * @method TaxLocation addressLine2(string $addressLine2) addressLine2
 * @method TaxLocation stateId(string $stateId) stateId
 * @method TaxLocation countryId(string $countryId) countryId
 * @method TaxLocation countryCode(string $countryCode) countryCode
 * @method TaxLocation stateCode(string $stateCode) stateCode
 * @method TaxLocation isBusiness(bool $isBusiness) isBusiness
 * @method TaxLocation addressBookId(string $addressBookId) addressBookId
 *
 * @package responsiv\pay
 * @author Alexey Bobkov, Samuel Georges
 */
class TaxLocation extends ElementBase
{
    /**
     * validate this object is fully populated
     */
    public function validate()
    {
        $rules = [];

        if (!$this->isAccountOptional()) {
            $rules += [
                'email' => 'required|email',
            ];
        }

        $rules += [
            'firstName' => 'required',
            'lastName' => 'required',
            'addressLine1' => 'required',
            'city' => 'required',
            'zip' => 'required',
            'countryId' => 'required',
        ];

        Validator::validate($this->config, $rules, [
            'addressLine1' => __("The address field is required")
        ]);
    }

    /**
     * validateFromPost this object based on POST parameters
     */
    public function validateFromPost(User $user = null)
    {
        $data = post();
        $rules = [];
        $prefix = $this->fieldPrefix ? $this->fieldPrefix . '_' : '';

        if ($this->isAccountOptional()) {
            $rules += [
                $prefix.'first_name' => 'required',
                $prefix.'last_name' => 'required',
            ];
        }
        elseif (!$user) {
            $rules += [
                $prefix.'email' => 'required|email',
                $prefix.'first_name' => 'required',
                $prefix.'last_name' => 'required',
            ];
        }

        $rules += [
            $prefix.'address_line1' => 'required',
            $prefix.'city' => 'required',
            $prefix.'zip' => 'required',
            $prefix.'country_id' => 'required',
        ];

        Validator::validate($data, $rules, [
            $prefix.'address_line1' => __("The address field is required")
        ]);
    }

    /**
     * getCountryCode
     */
    public function getCountryCode()
    {
        return $this->countryId ? Country::findByKey($this->countryId)?->code : null;
    }

    /**
     * getStateCode
     */
    public function getStateCode()
    {
        return $this->stateId ? State::findByKey($this->stateId)?->code : null;
    }

    /**
     * fillFromPost loads object properties from the POST parameters
     */
    public function fillFromPost()
    {
        $prefix = $this->fieldPrefix ? $this->fieldPrefix . '_' : '';

        $this
            ->firstName(post($prefix.'first_name', $this->firstName))
            ->lastName(post($prefix.'last_name', $this->lastName))
            ->email(post($prefix.'email', $this->email))
            ->company(post($prefix.'company', $this->company))
            ->phone(post($prefix.'phone', $this->phone))
            ->addressLine1(post($prefix.'address_line1', $this->addressLine1))
            ->city(post($prefix.'city', $this->city))
            ->zip(post($prefix.'zip', $this->zip))
            ->stateId(post($prefix.'state_id', $this->stateId))
            ->countryId(post($prefix.'country_id', $this->countryId))
            ->isBusiness(post($prefix.'is_business', $this->isBusiness))
            ->countryCode(null)
            ->stateCode(null)
        ;

        $this->loadInternals();
    }

    /**
     * fillFromOptions
     */
    public function fillFromOptions(array $options)
    {
        extract(array_merge([
            'city' => null,
            'zip' => null,
            'countryId' => null,
            'countryCode' => null,
            'stateId' => null,
            'stateCode' => null,
        ], $options));

        $this
            ->city($city)
            ->zip($zip)
            ->stateId($stateId)
            ->countryId($countryId)
            ->countryCode($countryCode)
            ->stateCode($stateCode)
        ;

        $this->loadInternals();
    }

    /**
     * fillFromUser
     */
    public function fillFromUser(User $user, UserAddress $address = null)
    {
        $this
            ->email($user->email)
            ->firstName($user->first_name)
            ->lastName($user->last_name)
            ->company($user->company)
            ->phone($user->phone)
            ->city($user->city)
            ->zip($user->zip)
            ->stateId($user->state_id)
            ->countryId($user->country_id)
        ;

        if ($address === null) {
            $address = $user->primary_address;
        }

        if ($address) {
            $this
                ->firstName($address->first_name)
                ->lastName($address->last_name)
                ->company($address->company)
                ->phone($address->phone)
                ->addressLine1($address->address_line1)
                ->city($address->city)
                ->zip($address->zip)
                ->stateId($address->state_id)
                ->countryId($address->country_id)
                ->isBusiness($address->is_business)
            ;
        }

        $this->loadInternals();
    }

    /**
     * saveToUser transfers this address to a user
     */
    public function saveToUser(User $user)
    {
        $user->first_name = $this->firstName;
        $user->last_name = $this->lastName;
        $user->company = $this->company;
        $user->email = $this->email;
        $user->phone = $this->phone;
    }

    /**
     * matchesCountry
     */
    public function matchesCountry($country): bool
    {
        return $this->checkValuesMatch($country, $this->countryCode);
    }

    /**
     * matchesState
     */
    public function matchesState($state): bool
    {
        return $this->checkValuesMatch($state, $this->stateCode);
    }

    /**
     * matchesZip
     */
    public function matchesZip($zip): bool
    {
        return $this->checkValuesMatch($zip, $this->zip, true);
    }

    /**
     * matchesCity
     */
    public function matchesCity($city): bool
    {
        return $this->checkValuesMatch($city, $this->city, true);
    }

    /**
     * get an attribute from the element instance, includes camel case conversion.
     * @param  string  $key
     */
    public function get($key, $default = null)
    {
        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }

        $key = camel_case($key);
        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }

        return value($default);
    }

    /**
     * offsetExists helps Twig find values instead of returning self
     * @param  string  $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return true;
    }

    /**
     * isAccountOptional returns false if an email is required by validation
     */
    protected function isAccountOptional(): bool
    {
        return true;
    }

    /**
     * loadInternals
     */
    protected function loadInternals()
    {
        if (!$this->countryCode && $this->countryId) {
            $this->countryCode(Country::findByKey($this->countryId)?->code);
        }

        if (!$this->stateCode && $this->stateId) {
            $this->stateCode(State::findByKey($this->stateId)?->code);
        }
    }

    /**
     * checkValuesMatch checks if two values match
     */
    protected function checkValuesMatch($value, $thisValue, $wildcard = false): bool
    {
        $value = strlen($value)
            ? str_replace(['-', ' '], '', $value)
            : '*';

        $value = strlen($value) ? str_replace(' ', '', $value) : '*';

        if ($value === '*' || !$thisValue) {
            return true;
        }

        $value = mb_strtoupper($value);
        $thisValue = mb_strtoupper($thisValue);

        if ($wildcard) {
            // Check wildcard*
            if (
                str_ends_with($value, '*') &&
                str_starts_with($thisValue, rtrim($value, '*'))
            ) {
                return true;
            }

            // Check *wildcard
            if (
                str_starts_with($value, '*') &&
                str_ends_with($thisValue, ltrim($value, '*'))
            ) {
                return true;
            }
        }

        return $value === $thisValue;
    }
}
