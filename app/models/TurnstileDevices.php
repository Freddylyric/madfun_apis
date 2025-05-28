
<?php

class TurnstileDevices extends \Phalcon\Mvc\Model {

    /**
     *
     * @var integer
     */
    public $turnstile_id;
    
    /**
     *
     * @var string
     */
    public $serial;

    /**
     *
     * @var string
     */
    public $status;
    /**
     *
     * @var string
     */
    public $ipAddress;
    /**
     *
     * @var string
     */
    public $macAddress;
    /**
     *
     * @var string
     */
    public $idVal;
    
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
    public function getSource() {
        return 'turnstile_devices';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return TurnstileDevices[]
     */
    public static function find($parameters = null) {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return TurnstileDevices
     */
    public static function findFirst($parameters = null) {
        return parent::findFirst($parameters);
    }

}