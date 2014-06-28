<?php namespace Responsiv\Pay\Models;

use Model;
use RainLab\User\Models\State;
use RainLab\User\Models\Country;

/**
 * Tax Model
 */
class Tax extends Model
{

    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_pay_taxes';

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [];

    /**
     * @var array List of attribute names which are json encoded and decoded from the database.
     */
    protected $jsonable = ['rates'];

    /**
     * @var array Validation rules
     */
    public $rules = [];

    /**
     * @var array Relations
     */
    public $hasOne = [];
    public $hasMany = [];
    public $belongsTo = [];
    public $belongsToMany = [];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];

    public function getGridAutocompleteValues($field, $value, $data)
    {
        if ($field == 'country')
            return $this->getCountryList($value);

        if ($field == 'state') {
            $countryCode = isset($data['country']) ? $data['country'] : null;
            return $this->getStateList($countryCode, $value);
        }
    }

    protected function getCountryList($term)
    {
        $countries = Country::searchWhere($term, ['name', 'code'])->limit(10)->lists('name', 'code');
        $result = ['*' => '* - Any country'];

        foreach ($countries as $code => $name) {
            $result[$code] = $code .' - ' . $name;
        }

        return $result;
    }

    protected function getStateList($countryCode, $term)
    {
        $states = State::searchWhere($term, ['name', 'code']);

        if ($countryCode) {
            $states->whereHas('country', function($query) use ($countryCode) {
                $query->where('code', $countryCode);
            });
        }

        $states = $states->limit(10)->lists('name', 'code');

        $result = ['*' => '* - Any state'];

        foreach ($states as $code => $name) {
            $result[$code] = $code .' - ' . $name;
        }

        return $result;
    }

}