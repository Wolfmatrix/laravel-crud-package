<?php

namespace Wolfmatrix\LaravelCrud\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class PatchResourceEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $action;

    public $entityType;

    public $entity;

    public $oldEntity;

    public $patchedField;

    public $requestedBody;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($action, $entityType, $requestedBody, $patchedField ,$entity, $oldEntity)
    {
        $this->action = $action;
        $this->entityType = $entityType;
        $this->requestedBody = $requestedBody;
        $this->patchedField = $patchedField;
        $this->entity = $entity;
        $this->oldEntity = $oldEntity;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}