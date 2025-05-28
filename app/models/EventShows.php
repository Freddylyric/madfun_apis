<?php

class EventShows extends \Phalcon\Mvc\Model
{
    /**
     *
     * @var integer
     */
    public $event_show_id;
    /**
     *
     * @var integer
     */
    public $eventID;
    /**
     *
     * @var string
     */
    public $show;
    /**
     *
     * @var string
     */
    public $venue;
     /**
     *
     * @var integer
     */
    public $show_status;
    /**
     *
     * @var string
     */
    public $start_date;
     /**
     *
     * @var string
     */
    public $end_date;
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
        return 'event_shows';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return EventShows[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return EventShows
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
