<?php

use Phalcon\Mvc\Model;

class Invoice extends Model
{
    public ?int $invoice_id = null;
    public ?string $reference = null;
    public ?int $invoice_type_id = null;
    public ?int $invoice_reference_id = null;
    public ?int $invoice_from_client_id = null;
    public ?int $invoice_to_client_id = null;
    public ?int $invoice_payment_type_id = null;
    public ?int $invoice_billing_reference = null;
    public ?int $invoice_status = null;
    public ?string $invoice_notes = null;
    public ?string $invoice_issued_date = null;
    public ?string $invoice_due_date = null;
    public ?float $invoice_amount = null;
    public ?int $total_items = null;
    public ?float $invoice_amount_paid = null;
    public ?float $invoice_fee = null;
    public ?string $created = null;
    public ?string $updated = null;

    /**
     * Set the database table for this model
     */
    public function initialize(): void
    {
        $this->setSource('invoices');

        // Example relationships (uncomment and adjust if needed):
        // $this->belongsTo('invoice_from_client_id', Clients::class, 'client_id', ['alias' => 'FromClient']);
        // $this->belongsTo('invoice_to_client_id', Clients::class, 'client_id', ['alias' => 'ToClient']);
    }
}
