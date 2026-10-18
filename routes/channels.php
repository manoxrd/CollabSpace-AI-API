<?php

use App\Models\ChatChannel;
use App\Models\Meeting;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['api']]);

Broadcast::channel('chat.channel.{channelId}', function ($user, $channelId) {
    if (! $user) {
        return false;
    }

    $channel = ChatChannel::with(['members', 'project.members'])->find($channelId);

    if (! $channel) {
        return false;
    }

    // Direct channel: user must be one of the channel members
    if ($channel->isDirect()) {
        return $channel->members->contains('id', $user->id);
    }

    // Project channel: user must be an admin, or member of the project
    if ($user->isAdmin()) {
        return true;
    }

    if ($channel->project) {
        return $channel->project->created_by === $user->id
            || $channel->project->members->contains('id', $user->id);
    }

    return false;
}, ['guards' => ['api']]);

Broadcast::channel('meeting.{meetingId}', function ($user, $meetingId) {
    if (! $user) {
        return false;
    }

    $meeting = Meeting::with(['participants', 'project.members'])->find($meetingId);

    if (! $meeting) {
        return false;
    }

    if ($user->isAdmin() || $meeting->created_by === $user->id) {
        return true;
    }

    return $meeting->participants->contains('id', $user->id)
        || ($meeting->project && $meeting->project->hasMember($user->id));
}, ['guards' => ['api']]);
