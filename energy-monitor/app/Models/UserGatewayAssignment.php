<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGatewayAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'gateway_id',
        'assigned_at',
        'assigned_by'
    ];

    protected $casts = [
        'assigned_at' => 'datetime'
    ];

    /**
     * Get the user that owns the assignment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the gateway that is assigned
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    /**
     * Get the user who made the assignment
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Scope to get assignments for a specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get assignments for a specific gateway
     */
    public function scopeForGateway($query, int $gatewayId)
    {
        return $query->where('gateway_id', $gatewayId);
    }

    /**
     * Get assignment details with related models
     */
    public function getAssignmentDetails(): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email
            ],
            'gateway' => [
                'id' => $this->gateway->id,
                'name' => $this->gateway->name,
                'location' => $this->gateway->location
            ],
            'assigned_at' => $this->assigned_at,
            'assigned_by' => $this->assignedBy ? [
                'id' => $this->assignedBy->id,
                'name' => $this->assignedBy->name
            ] : null
        ];
    }
}