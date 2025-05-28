<?php

class Country extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $country_id;

    /**
     *
     * @var string
     */
    public $country_name;
    /**
     *
     * @var string
     */
    public $country_flag;
    /**
     *
     * @var string
     */
    public $isoCode;
    /**
     *
     * @var string
     */
    public $isoCode2;

    /**
     *
     * @var string
     */
    public $currency;

    /**
     *
     * @var integer
     */
    public $status;
    
    /**
     *
     * @var string
     */
    public $mobile_prefix;

    /**
     *
     * @var string
     */
    public $created_at;

    /**
     *
     * @var string
     */
    public $updated_at;

    

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'country';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Country[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Country
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
