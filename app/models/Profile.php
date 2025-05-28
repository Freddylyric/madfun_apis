<?php

class Profile extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $profile_id;

    /**
     *
     * @var string
     */
    public $msisdn;
    
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
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'profile';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Profile[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Profile
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
