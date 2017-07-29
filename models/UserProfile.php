<?php namespace Responsiv\Pay\Models;

use October\Rain\Database\Model;

/**
 * User Payment Profile Model
 */
class UserProfile extends Model
{
    use \October\Rain\Database\Traits\Encryptable;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_pay_user_profiles';

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
    protected $jsonable = [];

    public function beforeSave()
    {
        $this->card_last_four = substr($this->card_last_four, -4);
    }

    public function afterCreate()
    {
        if ($this->is_primary) {
            $this->makePrimary();
        }
    }

    public function beforeUpdate()
    {
        if ($this->isDirty('is_primary')) {
            $this->makePrimary();

            if (!$this->is_primary) {
                throw new ValidationException(['is_primary' => Lang::get('responsiv.pay::lang.profile.unset_default', ['profile'=>$this->card_last_four])]);
            }
        }
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

    /**
     * Makes this model the default
     * @return void
     */
    public function makePrimary()
    {
        $this
            ->newQuery()
            ->applyUser($this->user_id)
            ->where('id', $this->id)
            ->update(['is_primary' => true])
        ;

        $this
            ->newQuery()
            ->applyUser($this->user_id)
            ->where('id', '<>', $this->id)
            ->update(['is_primary' => false])
        ;
    }

    /**
     * Returns the default profile defined.
     * @return self
     */
    public static function getPrimary($user)
    {
        $profiles = self::applyUser($user)->get();

        foreach ($profiles as $profile) {
            if ($profile->is_primary) {
                return $profile;
            }
        }

        return $profiles->first();
    }

    public static function userHasProfile($user)
    {
        return self::applyUser($user)->count() > 0;
    }

    //
    // Scopes
    //

    public function scopeApplyUser($query, $user)
    {
        if ($user instanceof Model) {
            $user = $user->getKey();
        }

        return $query->where('user_id', $user);
    }
}
