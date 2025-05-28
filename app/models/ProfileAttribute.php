<?php
/**
 * Description of ProfileAttribute
 *
 * @author kevinkmwando
 */

class ProfileAttribute extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $id;

    /**
     *
     * @var integer
     */
    public $profile_id;
    
    /**
     *
     * @var integer
     */
    public $year_of_birth;

    /**
     *
     * @var string
     */
    public $first_name;
    /**
     *
     * @var string
     */
    public $surname;
    /**
     *
     * @var string
     */
    public $last_name;
    /**
     *
     * @var string
     */
    public $age_bracket;
    /**
     *
     * @var string
     */
    public $gender;
    /**
     *
     * @var string
     */
    public $idNumber;

    /**
     *
     * @var string
     */
    public $network;
    
    /**
     *
     * @var string 
     */
    public $pin;
    
    /**
     *
     * @var string 
     */
    public $country;
    
    /**
     *
     * @var string 
     */
    public $city;

    /**
     *
     * @var string
     */
    public $token;

    /**
     *
     * @var integer
     */
    public $status;

    /**
     *
     * @var integer
     */
    public $frequency;
  
    /**
     *
     * @var string
     */
    public $last_dial_date;
    
     /**
     *
     * @var string
     */
    public $created_by;

    /**
     *
     * @var string
     */
    public $created;

    /**
     *
     * @var string
     */
    public $updated;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->belongsTo('profile_id', 'Profile', 'profile_id', array('alias' => 'Profile'));
    }

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'profile_attribute';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return ProfileAttribute[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return ProfileAttribute
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
