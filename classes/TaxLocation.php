<?php namespace Responsiv\Pay\Classes;

use RainLab\Location\Models\State;
use RainLab\Location\Models\Country;

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

    public function getCountryModel()
    {
        return $this->countryId
            ? Country::find($this->countryId)
            : null;
    }

    public function getStateModel()
    {
        return $this->stateId
            ? State::find($this->stateId)
            : null;
    }

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
