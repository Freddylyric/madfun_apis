<?php

class UserEventMap extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $user_event_map_id;
    /**
     *
     * @var integer
     */
    public $user_mapId;
    /**
     *
     * @var integer
     */
    public $eventID;

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
        return 'user_event_map';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return UserEventMap[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return UserEventMap
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
