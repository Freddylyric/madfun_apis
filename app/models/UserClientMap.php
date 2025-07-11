<?php

use Phalcon\Mvc\Model;

class UserClientMap extends Model
{
    public ?int $user_mapId = null;
    public ?int $client_id = null;
    public ?int $user_id = null;
    public ?int $status = null;
    public ?int $created_by = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public function initialize(): void
    {
        $this->setSource('user_client_map');

        $this->belongsTo(
            'client_id',
            Clients::class,
            'client_id',
            ['alias' => 'Clients']
        );

        $this->belongsTo(
            'user_id',
            User::class,
            'user_id',
            ['alias' => 'User']
        );
    }
}
