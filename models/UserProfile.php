<?php namespace Responsiv\Pay\Models;

use October\Rain\Database\Model;
use ValidationException;

/**
 * UserProfile Model
 *
 * @property int $id
 * @property int $user_id
 * @property int $payment_method_id
 * @property string $vendor_id
 * @property string $profile_data
 * @property string $card_brand
 * @property string $card_last_four
 * @property string $card_country
 * @property bool $is_primary
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon $created_at
 *
 * @package responsiv\pay
 * @author Alexey Bobkov, Samuel Georges
 */
class UserProfile extends Model
{
    use \October\Rain\Database\Traits\Encryptable;

    /**
     * @var string table used by the model
     */
    public $table = 'responsiv_pay_user_profiles';

    /**
     * @var array encryptable attribute names which should be encrypted
     */
    protected $encryptable = [
        'profile_data',
        'card_brand',
        'card_last_four',
        'card_country'
    ];

    /**
     * @var array jsonable attribute names that are json encoded and decoded from the database
     */
    protected $jsonable = [];

    /**
     * beforeSave
     */
    public function beforeSave()
    {
        $this->card_last_four = substr($this->card_last_four, -4);
    }

    /**
     * afterCreate
     */
    public function afterCreate()
    {
        if ($this->is_primary) {
            $this->makePrimary();
        }
    }

    /**
     * beforeUpdate
     */
    public function beforeUpdate()
    {
        if ($this->isDirty('is_primary')) {
            $this->makePrimary();

            if (!$this->is_primary) {
                throw new ValidationException(['is_primary' => __('":profile" is already default and cannot be unset as default.', ['profile'=>$this->card_last_four])]);
            }
        }
    }

    /**
     * setProfileData sets the gateway specific profile information and 4 last digits of the credit card number (PAN)
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
     * setCardNumber sets the 4 last digits of the credit card number (PAN)
     * and saves the profile to the database
     * @param string $cardDigits Last four digits of the CC number
     */
    public function setCardNumber($cardDigits)
    {
        $this->card_last_four = $cardDigits;
        $this->save();
    }

    /**
     * makePrimary makes this model the default
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
     * getPrimary returns the default profile defined.
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

    /**
     * userHasProfile
     */
    public static function userHasProfile($user)
    {
        return self::applyUser($user)->count() > 0;
    }

    //
    // Scopes
    //

    /**
     * scopeApplyUser
     */
    public function scopeApplyUser($query, $user)
    {
        if ($user instanceof Model) {
            $user = $user->getKey();
        }

        return $query->where('user_id', $user);
    }
}
