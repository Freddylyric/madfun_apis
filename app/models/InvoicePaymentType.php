<?php

use Phalcon\Mvc\Model;

class InvoicePaymentType extends Model
{
    public ?int $invoice_payment_type_id = null;
    public ?string $invoice_payment_type = null;
    public ?int $invoice_fee = null;
    public ?string $description = null;
    public ?int $status = null;
    public ?string $created = null;
    public ?string $updated = null;

    /**
     * Initialize the model and define the table
     */
    public function initialize(): void
    {
        $this->setSource('invoice_payment_type');

        // Define relationships if needed, e.g.:
        // $this->hasMany('invoice_payment_type_id', Invoice::class, 'invoice_payment_type_id', ['alias' => 'Invoices']);
    }
}
