<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProfileUpdated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $user;
    public $avatarUpdated;

    /**
     * Create a new event instance.
     *
     * @param User $user
     * @param bool $avatarUpdated
     */
    public function __construct(User $user, bool $avatarUpdated)
    {
        $this->user = $user;
        $this->avatarUpdated = $avatarUpdated;
    }
}
