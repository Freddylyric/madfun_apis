<?php

class AffiliatorEventMap extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $id;
    /**
     *
     * @var integer
     */
    public $affilator_id;
    /**
     *
     * @var integer
     */
    public $eventId;
    
     /**
     *
     * @var string
     */
    public $code;
    
     /**
     *
     * @var integer
     */
    public $discount;
    
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
        return 'affiliator_event_map';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return AffiliatorEventMap[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return AffiliatorEventMap
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
