<?php

class User extends \Phalcon\Mvc\Model {

    /**
     *
     * @var integer
     */
    public $user_id;
    
    /**
     *
     * @var string
     */
    public $email;

    /**
     *
     * @var integer
     */
    public $profile_id;

    /**
     *
     * @var string
     */
    public $password;

    /**
     *
     * @var string
     */
    public $api_token;

    /**
     *
     * @var string
     */
    public $last_login;

    /**
     *
     * @var string
     */
    public $date_activated;

    /**
     *
     * @var integer
     */
    public $status;

    /**
     *
     * @var integer
     */
    public $role_id;
    
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
     *
     * @var string
     */
    public $deleted;

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource() {
        return 'user';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return User[]
     */
    public static function find($parameters = null) {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return User
     */
    public static function findFirst($parameters = null) {
        return parent::findFirst($parameters);
    }

}
