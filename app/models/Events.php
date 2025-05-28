<?php

class Events extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $eventID;

    /**
     *
     * @var string
     */
    public $eventName;
    /**
     *
     * @var string
     */
    public $company;
    /**
     *
     * @var string
     */
    public $venue;
    /**
     *
     * @var string
     */
    public $eventTag;
    
    /**
     *
     * @var integer
     */
    public $ageLimit;
    
    /**
     *
     * @var integer
     */
    public $isFeeOnOrganizer;
    
    /**
     *
     * @var integer
     */
    public $target;
    
    /**
     *
     * @var integer
     */
    public $category_id;
    
    /**
     *
     * @var integer
     */
    public $isPublic;
    
    /**
     *
     * @var integer
     */
    public $soldOut;
    
    /**
     *
     * @var integer
     */
    public $isFeatured;
    
    /**
     *
     * @var integer
     */
    public $showOnSlide;
    /**
     *
     * @var integer
     */
    public $isFree;
    /**
     *
     * @var string
     */
    public $aboutEvent;
    /**
     *
     * @var string
     */
    public $eventType;
    /**
     *
     * @var string
     */
    public $posterURL;
    /**
     *
     * @var string
     */
    public $bannerURL;
    
    /**
     *
     * @var double
     */
    public $revenueShare;
    /**
     *
     * @var string
     */
    public $currency;
    
    /**
     *
     * @var integer
     */
    public $hasMultipleShow;
     /**
     *
     * @var integer
     */
    public $hasAffiliator;
    /**
     *
     * @var integer
     */
    public $min_price;
    
    /**
     *
     * @var integer
     */
    public $hasLinkingTag;
    /**
     *
     * @var string
     */
    public $dateInfo;
    /**
     *
     * @var integer
     */
    public $status;
    
    /**
     *
     * @var integer
     */
    public $accept_mpesa_payment;
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
        return 'events';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Events[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Events
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
