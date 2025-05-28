<?php

class Clients extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $client_id;

    /**
     *
     * @var string
     */
    public $client_name;
    /**
     *
     * @var string
     */
    public $address;
    /**
     *
     * @var string
     */
    public $msisdn;
    /**
     *
     * @var string
     */
    public $email_address;

    /**
     *
     * @var string
     */
    public $description;

    /**
     *
     * @var integer
     */
    public $created_by;

    /**
     *
     * @var string
     */
    public $created_at;

    /**
     *
     * @var string
     */
    public $updated_at;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->hasMany('client_id', 'UserClientMap', 'client_id', array('alias' => 'UserClientMap'));
    }

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'clients';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Clients[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Clients
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
