<?php

namespace App\Notifications;

use App\Enums\Priority;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public ?string $assignedByName = null
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
        $priorityLabel = $this->task->priority instanceof Priority
            ? $this->task->priority->value
            : (string) $this->task->priority;

        $dueDate = $this->task->due_date
            ? $this->task->due_date->format('Y-m-d')
            : 'No deadline specified';

        return (new MailMessage)
            ->subject("CollabSpace: New Task Assigned - {$this->task->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have been assigned to a new task: **{$this->task->title}**.")
            ->line('Priority: **'.strtoupper($priorityLabel).'**')
            ->line("Due Date: {$dueDate}")
            ->action('View Task in CollabSpace', url("/tasks/{$this->task->id}"))
            ->line('Thank you for collaborating on CollabSpace AI!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New Task Assigned',
            'message' => "You have been assigned to task '{$this->task->title}'.",
            'type' => 'task_assigned',
            'task_id' => $this->task->id,
            'project_id' => $this->task->project_id,
            'assigned_by' => $this->assignedByName,
            'action_url' => "/tasks/{$this->task->id}",
        ];
    }
}
