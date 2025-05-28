<?php

class EventShowTicketsType extends \Phalcon\Mvc\Model
{
    /**
     *
     * @var integer
     */
    public $event_ticket_show_id;
    /**
     *
     * @var string
     */
    public $color_code;
    /**
     *
     * @var string
     */
    public $main_color_code;
    /**
     *
     * @var integer
     */
    public $typeId;
    /**
     *
     * @var string
     */
    public $description;
    /**
     *
     * @var integer
     */
    public $event_show_venue_id;
    /**
     *
     * @var integer
     */
    public $amount;
     /**
     *
     * @var integer
     */
    public $total_tickets;
    /**
     *
     * @var integer
     */
    public $discount;
    /**
     *
     * @var integer
     */
    public $group_ticket_quantity;
    /**
     *
     * @var integer
     */
    public $total_complimentary;
    /**
     *
     * @var integer
     */
    public $total_ticket_code;
    /**
     *
     * @var integer
     */
    public $ticket_purchased;
    
    /**
     *
     * @var integer
     */
    public $maxCap;
    /**
     *
     * @var integer
     */
    public $issued_complimentary;
    /**
     *
     * @var integer
     */
    public $issued_ticket_code;
    /**
     *
     * @var integer
     */
    public $ticket_redeemed;
    /**
     *
     * @var integer
     */
    public $isPartialPay;
    /**
     *
     * @var integer
     */
    public $isPublic;
    /**
     *
     * @var integer
     */
    public $hasOption;
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
        return 'event_show_tickets_type';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return EventShowTicketsType[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return EventShowTicketsType
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
