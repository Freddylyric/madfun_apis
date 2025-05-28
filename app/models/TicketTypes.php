<?php

class TicketTypes extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $typeId;

    /**
     *
     * @var string
     */
    public $ticket_type;
    /**
     *
     * @var integer
     */
    public $status;
    /**
     *
     * @var string
     */
    public $caption;
    
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
        return 'ticket_types';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return  TicketTypes[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return TicketTypes
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
