<?php

class StreamProfileRequest extends \Phalcon\Mvc\Model
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
    public $profile_id;
    /**
     *
     * @var string
     */
    public $order_key;
    /**
     *
     * @var string
     */
    public $currency;
     /**
     *
     * @var integer
     */
    public $amount;
     /**
     *
     * @var string
     */
    public $reference_id;
    /**
     *
     * @var string
     */
    public $item_name;
    /**
     *
     * @var string
     */
    public $returnURL;
    /**
     *
     * @var string
     */
    public $cancelURL;
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
        return 'stream_profile_request';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return StreamProfileRequest[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return StreamProfileRequest
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
