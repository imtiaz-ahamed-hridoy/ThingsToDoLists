<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\Auth\AuthFacade;
use Exception;

class SendEmailVerificationOtp
{
    protected AuthFacade $authFacade;

    /**
     * Create the event listener.
     */
    public function __construct(AuthFacade $authFacade)
    {
        $this->authFacade = $authFacade;
    }

    /**
     * Handle the event.
     *
     * @throws Exception
     */
    public function handle(UserRegistered $event): void
    {
        $this->authFacade->sendVerificationOtp($event->user->email);
    }
}
