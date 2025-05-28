<?php

class UserClientMap extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $user_mapId;

    /**
     *
     * @var integer
     */
    public $client_id;

    /**
     *
     * @var integer
     */
    public $user_id;

    /**
     *
     * @var integer
     */
    public $status;

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
        $this->belongsTo('client_id', 'Clients', 'client_id', array('alias' => 'Clients'));
        $this->belongsTo('user_id', 'User', 'user_id', array('alias' => 'User'));
    }

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'user_client_map';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return UserClientMap[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return UserClientMap
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
