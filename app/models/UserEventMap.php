<?php

use Phalcon\Mvc\Model;

class UserEventMap extends Model
{
    public ?int $user_event_map_id = null;
    public ?int $user_mapId = null;
    public ?int $eventID = null;
    public ?string $created = null;
    public ?string $updated = null;

    public function initialize(): void
    {
        $this->setSource('user_event_map');

        $this->belongsTo(
            'user_mapId',
            UserClientMap::class,
            'user_mapId',
            ['alias' => 'UserClientMap']
        );

        $this->belongsTo(
            'eventID',
            Events::class,
            'eventID',
            ['alias' => 'Events']
        );
    }
}
