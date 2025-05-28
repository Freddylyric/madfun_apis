<?php

class EventShowVenue extends \Phalcon\Mvc\Model
{
    /**
     *
     * @var integer
     */
    public $event_show_venue_id;
    /**
     *
     * @var integer
     */
    public $event_show_id;
    /**
     *
     * @var string
     */
    public $venue;
     /**
     *
     * @var integer
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
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'event_show_venue';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return EventShowVenue[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return EventShowVenue
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
