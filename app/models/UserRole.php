<?php

class UserRole extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $user_role_id;

    /**
     *
     * @var string
     */
    public $role_name;

    /**
     *
     * @var string
     */
    public $role_description;

    /**
     *
     * @var string
     */
    public $created;

    /**
     *
     * @var string
     */
    public $modified;

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'user_role';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return UserRole[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return UserRole
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
