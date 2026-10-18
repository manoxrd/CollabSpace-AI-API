<?php

namespace App\Providers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\ChatChannel;
use App\Models\ChatMessage;
use App\Models\File;
use App\Models\Folder;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Report;
use App\Models\Task;
use App\Policies\AiConversationPolicy;
use App\Policies\AiMessagePolicy;
use App\Policies\ChatChannelPolicy;
use App\Policies\ChatMessagePolicy;
use App\Policies\FilePolicy;
use App\Policies\FolderPolicy;
use App\Policies\MeetingPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ReportPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(ChatChannel::class, ChatChannelPolicy::class);
        Gate::policy(ChatMessage::class, ChatMessagePolicy::class);
        Gate::policy(Meeting::class, MeetingPolicy::class);
        Gate::policy(AiConversation::class, AiConversationPolicy::class);
        Gate::policy(AiMessage::class, AiMessagePolicy::class);
        Gate::policy(Report::class, ReportPolicy::class);
        Gate::policy(File::class, FilePolicy::class);
        Gate::policy(Folder::class, FolderPolicy::class);
    }
}
