<?php

class UserLogin extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $user_login_id;

    /**
     *
     * @var integer
     */
    public $user_id;

    /**
     *
     * @var string
     */
    public $login_code;

    /**
     *
     * @var integer
     */
    public $successful_attempts;

    /**
     *
     * @var integer
     */
    public $failed_attempts;

    /**
     *
     * @var integer
     */
    public $cumlative_failed_attempts;

    /**
     *
     * @var string
     */
    public $last_failed_attempt;

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
        return 'user_login';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return UserLogin[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return UserLogin
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
