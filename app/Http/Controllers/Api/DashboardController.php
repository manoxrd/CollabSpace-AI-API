<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\ActiveProjectResource;
use App\Http\Resources\Dashboard\TeamMemberOverviewResource;
use App\Http\Resources\Dashboard\UpcomingMeetingResource;
use App\Http\Resources\Dashboard\UrgentTaskResource;
use App\Http\Resources\FileResource;
use App\Services\DashboardService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    /**
     * Get the 4 top KPI cards stats.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->dashboardService->getStats();

        return $this->success($stats, 'Dashboard stats retrieved successfully.');
    }

    /**
     * Get monthly data points for the project overview chart.
     */
    public function projectOverviewChart(Request $request): JsonResponse
    {
        $projectId = $request->query('project_id') ? (int) $request->query('project_id') : null;
        $year = $request->query('year') ? (int) $request->query('year') : null;

        $chart = $this->dashboardService->getProjectOverviewChart($projectId, $year);

        return $this->success($chart, 'Project overview chart retrieved successfully.');
    }

    /**
     * Get top active projects with task progress metrics.
     */
    public function activeProjects(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 5);
        $projects = $this->dashboardService->getActiveProjects($limit);

        return $this->success([
            'projects' => ActiveProjectResource::collection($projects),
        ], 'Active projects retrieved successfully.');
    }

    /**
     * Get recent uploaded files across projects.
     */
    public function recentFiles(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 5);
        $files = $this->dashboardService->getRecentFiles($limit);

        return $this->success([
            'files' => FileResource::collection($files),
        ], 'Recent files retrieved successfully.');
    }

    /**
     * Get urgent tasks due today or within the current sprint.
     */
    public function urgentTasks(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 5);
        $tasks = $this->dashboardService->getUrgentTasks($limit);

        return $this->success([
            'tasks' => UrgentTaskResource::collection($tasks),
        ], 'Urgent tasks retrieved successfully.');
    }

    /**
     * Get team members overview with task completion progress.
     */
    public function teamOverview(Request $request): JsonResponse
    {
        $teamId = $request->query('team_id') ? (int) $request->query('team_id') : null;
        $limit = (int) $request->query('limit', 6);
        $members = $this->dashboardService->getTeamOverview($teamId, $limit);

        return $this->success([
            'team_members' => TeamMemberOverviewResource::collection($members),
        ], 'Team overview retrieved successfully.');
    }

    /**
     * Get upcoming scheduled meetings.
     */
    public function upcomingMeetings(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 3);
        $meetings = $this->dashboardService->getUpcomingMeetings($limit);

        return $this->success([
            'meetings' => UpcomingMeetingResource::collection($meetings),
        ], 'Upcoming meetings retrieved successfully.');
    }
}
