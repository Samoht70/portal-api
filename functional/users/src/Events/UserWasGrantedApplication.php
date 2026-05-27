<?php

namespace Functional\Users\Events;

use Functional\Users\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserWasGrantedApplication
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly User            $user,
        public readonly Application     $application,
        public readonly ApplicationRole $role,
    )
    {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
