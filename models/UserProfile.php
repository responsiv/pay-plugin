<?php namespace Responsiv\Pay\Models;

use Model;

/**
 * User Paymeny Profile Model
 */
class UserProfile extends Model
{
    use October\Rain\Database\Traits\Encryptable;

    /**
     * @var array List of attribute names which should be encrypted
     */
    protected $encryptable = [
        'profile_data',
        'card_brand',
        'card_last_four',
        'card_country'
    ];

    /**
     * @var array List of attribute names which are json encoded and decoded from the database.
     */
    protected $jsonable = ['profile_data'];

    public function beforeSave()
    {
        $this->card_last_four = substr($this->card_last_four, -4);
    }

    /**
     * Sets the gateway specific profile information and 4 last digits of the credit card number (PAN)
     * and saves the profile to the database
     * @param array $profileData Profile data
     * @param string $cardDigits Last four digits of the CC number
     */
    public function setProfileData($profileData, $cardDigits)
    {
        $this->profile_data = $profileData;
        $this->card_last_four = $cardDigits;
        $this->save();
    }
    
    /**
     * Sets the 4 last digits of the credit card number (PAN)
     * and saves the profile to the database
     * @param string $cardDigits Last four digits of the CC number
     */
    public function setCardNumber($cardDigits)
    {
        $this->card_last_four = $cardDigits;
        $this->save();
    }
}
