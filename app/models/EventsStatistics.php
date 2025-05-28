<?php

class EventsStatistics extends \Phalcon\Mvc\Model
{
    /**
     *
     * @var integer
     */
    public $event_stats_id;
    /**
     *
     * @var integer
     */
    public $eventID;

    /**
     *
     * @var integer
     */
    public $total_tickets;
    /**
     *
     * @var integer
     */
    public $total_tickets_collection;
    /**
     *
     * @var double
     */
    public $serivces_fee;
    /**
     *
     * @var double
     */
    public $ticket_refund;
    /**
     *
     * @var double
     */
    public $ticket_purchased;
    /**
     *
     * @var double
     */
    public $ticket_withdraw;
    
    /**
     *
     * @var integer
     */
    public $ticket_redeemed;
    /**
     *
     * @var integer
     */
    public $total_complimentary;
    /**
     *
     * @var integer
     */
    public $issued_complimentary;
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
        return 'event_statistics';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return EventsStatistics[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return EventsStatistics
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
