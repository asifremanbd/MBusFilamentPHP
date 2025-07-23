<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserPermissionsChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;
    public array $changes;
    public User $changedBy;
    public string $changeType;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, array $changes, User $changedBy, string $changeType = 'updated')
    {
        $this->user = $user;
        $this->changes = $changes;
        $this->changedBy = $changedBy;
        $this->changeType = $changeType;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->user->id}.permissions"),
            new PrivateChannel('admin.permission-changes'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
            'change_type' => $this->changeType,
            'changes' => $this->changes,
            'changed_by' => [
                'id' => $this->changedBy->id,
                'name' => $this->changedBy->name,
                'email' => $this->changedBy->email,
            ],
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'permissions.changed';
    }
}