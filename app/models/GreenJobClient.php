<?php

class GreenJobClient extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $green_job_customer_id;

    /**
     *
     * @var integer
     */
    public $profile_id;
    
    /**
     *
     * @var string
     */
    public $full_name;

     /**
     *
     * @var string
     */
    public $email;
    /**
     *
     * @var string
     */
    public $organisation;
    /**
     *
     * @var string
     */
    public $title;
    /**
     *
     * @var string
     */
    public $preferred_workstreams;
    /**
     *
     * @var string
     */
    public $county;
    /**
     *
     * @var string
     */
    public $age;
    /**
     *
     * @var string
     */
    public $gender;
    
     /**
     *
     * @var string
     */
    public $category;
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
        return 'green_job_client';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return GreenJobClient[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return GreenJobClient
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
