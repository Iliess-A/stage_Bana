<?php

namespace App\User\Event;

use App\User\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class SsoCreationEvent extends Event
{
    public const NAME = 'user_sso_creation_success';

    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}