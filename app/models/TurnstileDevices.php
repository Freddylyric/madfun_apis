<?php

use Phalcon\Mvc\Model;

class TurnstileDevices extends Model
{
    public ?int $turnstile_id = null;
    public ?string $serial = null;
    public ?string $status = null;
    public ?string $ipAddress = null;
    public ?string $macAddress = null;
    public ?string $idVal = null;
    public ?string $created = null;
    public ?string $updated = null;

    /**
     * Initialize the model and define its table
     */
    public function initialize(): void
    {
        $this->setSource('turnstile_devices');
    }
}
