<?php

use Phalcon\Mvc\Model;

class UserLogin extends Model
{
    public ?int $user_login_id = null;
    public ?int $user_id = null;
    public ?string $login_code = null;
    public ?int $successful_attempts = null;
    public ?int $failed_attempts = null;
    public ?int $cumlative_failed_attempts = null;
    public ?string $last_failed_attempt = null;
    public ?string $created = null;
    public ?string $updated = null;

    public function initialize(): void
    {
        $this->setSource('user_login');

        $this->belongsTo(
            'user_id',
            User::class,
            'user_id',
            ['alias' => 'User']
        );
    }
}
