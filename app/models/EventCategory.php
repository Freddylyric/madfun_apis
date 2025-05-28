<?php

class EventCategory extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $id;

    /**
     *
     * @var string
     */
    public $category;
    /**
     *
     * @var string
     */
    public $desciption;
    /**
     *
     * @var string
     */
    public $status;
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
        $this->hasMany('client_id', 'UserClientMap', 'client_id', array('alias' => 'UserClientMap'));
    }

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'event_category';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return EventCategory[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return EventCategory
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
