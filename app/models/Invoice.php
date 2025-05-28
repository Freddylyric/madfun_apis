<?php

class Invoice extends \Phalcon\Mvc\Model {

    /**
     *
     * @var integer
     */
    public $invoice_id;
    
    /**
     *
     * @var string
     */
    public $reference;

    /**
     *
     * @var integer
     */
    public $invoice_type_id;
    /**
     *
     * @var integer
     */
    public $invoice_reference_id;
    /**
     *
     * @var integer
     */
    public $invoice_from_client_id;
    /**
     *
     * @var integer
     */
    public $invoice_to_client_id;
    
    /**
     *
     * @var integer
     */
    public $invoice_payment_type_id;
    /**
     *
     * @var integer
     */
    public $invoice_billing_reference;
    /**
     *
     * @var integer
     */
    public $invoice_status;

    /**
     *
     * @var string
     */
    public $invoice_notes;

    /**
     *
     * @var string
     */
    public $invoice_issued_date;

    /**
     *
     * @var string
     */
    public $invoice_due_date;

    /**
     *
     * @var float
     */
    public $invoice_amount;

    /**
     *
     * @var integer
     */
    public $total_items;
    
     /**
     *
     * @var float
     */
    public $invoice_amount_paid;
    
     /**
     *
     * @var float
     */
    public $invoice_fee;
    
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
        return 'invoices';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Invoice[]
     */
    public static function find($parameters = null) {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Invoice
     */
    public static function findFirst($parameters = null) {
        return parent::findFirst($parameters);
    }

}
