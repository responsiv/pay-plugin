<?php namespace Responsiv\Pay\Classes;

use RainLab\Location\Models\State;
use RainLab\Location\Models\Country;
use RainLab\Location\Models\Setting as LocationSetting;

/**
 * Represents a location used for tax calculation
 */
class TaxLocation
{
    /**
     * @var string
     */
    public $streetAddr;

    /**
     * @var string
     */
    public $city;

    /**
     * @var string
     */
    public $zip;

    /**
     * @var int
     */
    public $stateId;

    /**
     * @var int
     */
    public $countryId;

    /**
     * Guesses location info based on default country
     * @return self
     */
    public static function makeDefault()
    {
        $location = new self;

        $location->countryId = LocationSetting::get('default_country', 1);

        return $location;
    }

    /**
     * Returns self, built from a standard object.
     * @return self
     */
    public static function makeFromObject($obj)
    {
        $location = new self;

        $location->streetAddr = $obj->street_addr;
        $location->city = $obj->city;
        $location->zip = $obj->zip;
        $location->stateId = $obj->state_id;
        $location->countryId = $obj->country_id;

        return $location;
    }

    /**
     * Looks up a country by the identifier
     * return RainLab\Location\Models\Country
     */
    public function getCountryModel()
    {
        return $this->countryId
            ? Country::find($this->countryId)
            : null;
    }

    /**
     * Looks up a state by the identifier
     * return RainLab\Location\Models\State
     */
    public function getStateModel()
    {
        return $this->stateId
            ? State::find($this->stateId)
            : null;
    }

    /**
     * Returns a city code for use with tax calculation
     * @return string
     */
    public function getCityCode()
    {
        $city = str_replace('-', '',
            str_replace(' ', '',
                trim(mb_strtoupper($this->city))
            )
        );

        if (!strlen($city)) {
            $city = '*';
        }

        return $city;
    }

    /**
     * Returns a zip code for use with tax calculation
     * @return string
     */
    public function getZipCode()
    {
        $zipCode = str_replace(' ', '',
            trim(strtoupper($this->zip))
        );

        if (!strlen($zipCode)) {
            $zipCode = '*';
        }

        return $zipCode;
    }
}
