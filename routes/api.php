<?php

use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\GoogleController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function () {

    // Authentication Routes (Protected by Auth Rate Limiter)
    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/refresh', [AuthController::class, 'refresh']);

        // Password Reset Workflow
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);

        // Google OAuth Authentication
        Route::post('/google', [GoogleController::class, 'callback']);
        Route::post('/google/callback', [GoogleController::class, 'callback']);

        Route::middleware('auth:api')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    // Protected Routes with Role Authorization
    Route::middleware('auth:api')->group(function () {

        // Dashboard & Analytics Overview
        Route::prefix('dashboard')->group(function () {
            Route::get('/stats', [DashboardController::class, 'stats']);
            Route::get('/project-overview-chart', [DashboardController::class, 'projectOverviewChart']);
            Route::get('/active-projects', [DashboardController::class, 'activeProjects']);
            Route::get('/recent-files', [DashboardController::class, 'recentFiles']);
            Route::get('/urgent-tasks', [DashboardController::class, 'urgentTasks']);
            Route::get('/team-overview', [DashboardController::class, 'teamOverview']);
            Route::get('/upcoming-meetings', [DashboardController::class, 'upcomingMeetings']);
        });

        // User Profile & Account Settings
        Route::prefix('profile')->group(function () {
            Route::get('/', [ProfileController::class, 'show']);
            Route::put('/', [ProfileController::class, 'update']);
            Route::get('/tasks', [ProfileController::class, 'tasks']);
            Route::get('/files', [ProfileController::class, 'files']);
            Route::get('/activities', [ProfileController::class, 'activities']);
        });

        // Project Management & Deep Dive API
        Route::prefix('projects')->group(function () {
            Route::get('/', [ProjectController::class, 'index']);
            Route::post('/', [ProjectController::class, 'store']);
            Route::get('/{project}', [ProjectController::class, 'show']);
            Route::put('/{project}', [ProjectController::class, 'update']);
            Route::delete('/{project}', [ProjectController::class, 'destroy']);

            // Project Sub-modules (Overview, Teams, Files, Tasks Kanban)
            Route::get('/{project}/overview', [ProjectController::class, 'overview']);
            Route::get('/{project}/teams', [ProjectController::class, 'teams']);
            Route::post('/{project}/teams', [ProjectController::class, 'assignTeams']);
            Route::post('/{project}/members', [ProjectController::class, 'assignMember']);
            Route::delete('/{project}/members/{user}', [ProjectController::class, 'removeMember']);
            Route::get('/{project}/files', [ProjectController::class, 'files']);
            Route::post('/{project}/files', [ProjectController::class, 'uploadFile']);
            Route::post('/{project}/folders', [FileController::class, 'storeFolder']);
            Route::get('/{project}/tasks/kanban', [TaskController::class, 'projectKanban']);
        });

        // Files & Document Management
        Route::prefix('files')->group(function () {
            Route::get('/{file}', [FileController::class, 'show']);
            Route::get('/{file}/download', [FileController::class, 'download']);
            Route::delete('/{file}', [FileController::class, 'destroy']);
        });

        // Folders Management
        Route::prefix('folders')->group(function () {
            Route::get('/{folder}', [FileController::class, 'showFolder']);
            Route::delete('/{folder}', [FileController::class, 'destroyFolder']);
        });

        // Task Management & Kanban Board
        Route::prefix('tasks')->group(function () {
            Route::get('/', [TaskController::class, 'index']);
            Route::post('/', [TaskController::class, 'store']);
            Route::get('/{task}', [TaskController::class, 'show']);
            Route::put('/{task}', [TaskController::class, 'update']);
            Route::delete('/{task}', [TaskController::class, 'destroy']);

            // Drag-and-drop status update, assignees sync, reviewer assignment & comments
            Route::patch('/{task}/status', [TaskController::class, 'updateStatus']);
            Route::post('/{task}/assignees', [TaskController::class, 'syncAssignees']);
            Route::post('/{task}/reviewer', [TaskController::class, 'assignReviewer']);
            Route::post('/{task}/comments', [TaskController::class, 'addComment']);
        });

        // Real-Time Project Chat & Direct Inbox
        Route::prefix('chat')->group(function () {
            // Direct 1-on-1 Inbox
            Route::get('/inbox', [ChatController::class, 'inbox']);
            Route::post('/direct', [ChatController::class, 'startDirect']);

            // Project Channels
            Route::get('/projects/{project}/channels', [ChatController::class, 'projectChannels']);
            Route::post('/channels', [ChatController::class, 'createChannel']);
            Route::get('/channels/{channel}', [ChatController::class, 'showChannel']);
            Route::post('/channels/{channel}/read', [ChatController::class, 'markAsRead']);
            Route::post('/channels/{channel}/mute', [ChatController::class, 'toggleMute']);

            // Channel Messages
            Route::get('/channels/{channel}/messages', [ChatController::class, 'messages']);
            Route::post('/channels/{channel}/messages', [ChatController::class, 'sendMessage']);

            // Thread Replies
            Route::get('/messages/{message}/thread', [ChatController::class, 'thread']);
            Route::post('/messages/{message}/reply', [ChatController::class, 'reply']);

            // Message Actions (Edit / Delete)
            Route::patch('/messages/{message}', [ChatController::class, 'updateMessage']);
            Route::delete('/messages/{message}', [ChatController::class, 'deleteMessage']);
        });

        // Meetings Management, Transcripts & AI Summaries
        Route::prefix('meetings')->group(function () {
            Route::get('/', [MeetingController::class, 'index']);
            Route::post('/', [MeetingController::class, 'store']);
            Route::post('/google-meet-space', [MeetingController::class, 'generateGoogleMeetSpace']);
            Route::get('/google-meet/diagnostics', [MeetingController::class, 'googleMeetDiagnostics']);
            Route::get('/{meeting}', [MeetingController::class, 'show']);
            Route::put('/{meeting}', [MeetingController::class, 'update']);
            Route::delete('/{meeting}', [MeetingController::class, 'destroy']);
            Route::patch('/{meeting}/rsvp', [MeetingController::class, 'rsvp']);

            // Live Transcripts
            Route::get('/{meeting}/transcripts', [MeetingController::class, 'transcripts']);
            Route::post('/{meeting}/transcripts', [MeetingController::class, 'addTranscript']);

            // AI Summaries
            Route::get('/{meeting}/ai-summary', [MeetingController::class, 'aiSummary']);
            Route::post('/{meeting}/ai-summary', [MeetingController::class, 'generateAiSummary']);
        });

        // AI Assistant (Insights, Chat & Workspace Context Analysis)
        Route::prefix('ai')->group(function () {
            Route::get('/conversations', [AiController::class, 'index']);
            Route::post('/conversations', [AiController::class, 'store']);
            Route::get('/conversations/{conversation}', [AiController::class, 'show']);
            Route::delete('/conversations/{conversation}', [AiController::class, 'destroy']);
            Route::post('/conversations/{conversation}/messages', [AiController::class, 'sendMessage']);
            Route::patch('/messages/{message}/feedback', [AiController::class, 'feedback']);
        });

        // Reports & Analytics (Executive KPIs & Trend Charts)
        Route::prefix('reports')->group(function () {
            Route::get('/summary', [ReportController::class, 'summary']);
            Route::get('/charts/deadline-comparison', [ReportController::class, 'deadlineComparison']);
            Route::get('/charts/project-progress', [ReportController::class, 'projectProgress']);
            Route::get('/charts/worker-activity', [ReportController::class, 'workerActivity']);
            Route::get('/', [ReportController::class, 'index']);
            Route::post('/', [ReportController::class, 'store']);
            Route::get('/{report}', [ReportController::class, 'show']);
            Route::delete('/{report}', [ReportController::class, 'destroy']);
        });

        // Notifications (In-App Notification Bell & Status)
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
            Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
            Route::delete('/{id}', [NotificationController::class, 'destroy']);
        });
    });
});
