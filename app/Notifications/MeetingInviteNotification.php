<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingInviteNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Meeting $meeting,
        public ?string $invitedByName = null
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("CollabSpace: Meeting Invitation - {$this->meeting->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have been invited to a meeting: **{$this->meeting->title}**.")
            ->line("Date & Time: **{$this->meeting->start_time}**");

        if ($this->meeting->meeting_link) {
            $mail->line("Meeting Room Link: {$this->meeting->meeting_link}");
        }

        return $mail
            ->action('RSVP / Join Meeting', url("/meetings/{$this->meeting->id}"))
            ->line('Please confirm your attendance in CollabSpace AI.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Meeting Invitation',
            'message' => "You have been invited to '{$this->meeting->title}'.",
            'type' => 'meeting_invite',
            'meeting_id' => $this->meeting->id,
            'start_time' => (string) $this->meeting->start_time,
            'meeting_link' => $this->meeting->meeting_link,
            'invited_by' => $this->invitedByName,
            'action_url' => "/meetings/{$this->meeting->id}",
        ];
    }
}
