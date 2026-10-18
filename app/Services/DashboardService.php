<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\File;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * Get the 4 top KPI cards stats with monthly comparisons.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $totalTasks = Task::count();
        $pendingTasks = Task::where('status', TaskStatus::Todo->value)->count();
        $inProgressTasks = Task::whereIn('status', [TaskStatus::InProgress->value, TaskStatus::InReview->value])->count();
        $completedTasks = Task::where('status', TaskStatus::Completed->value)->count();

        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        // Month-over-month comparison
        $thisMonthStart = now()->startOfMonth();
        $lastMonthStart = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();

        // Pending delta
        $pendingThisMonth = Task::where('status', TaskStatus::Todo->value)
            ->whereBetween('created_at', [$thisMonthStart, now()])
            ->count();
        $pendingLastMonth = Task::where('status', TaskStatus::Todo->value)
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->count();
        $pendingDelta = $this->calculateDeltaPercentage($pendingThisMonth, $pendingLastMonth);

        // In progress delta
        $inProgressThisMonth = Task::whereIn('status', [TaskStatus::InProgress->value, TaskStatus::InReview->value])
            ->whereBetween('updated_at', [$thisMonthStart, now()])
            ->count();
        $inProgressLastMonth = Task::whereIn('status', [TaskStatus::InProgress->value, TaskStatus::InReview->value])
            ->whereBetween('updated_at', [$lastMonthStart, $lastMonthEnd])
            ->count();
        $inProgressDelta = $this->calculateDeltaPercentage($inProgressThisMonth, $inProgressLastMonth);

        // Completed delta
        $completedThisMonth = Task::where('status', TaskStatus::Completed->value)
            ->where(function ($q) use ($thisMonthStart) {
                $q->whereBetween('completed_at', [$thisMonthStart, now()])
                    ->orWhere(function ($sub) use ($thisMonthStart) {
                        $sub->whereNull('completed_at')->whereBetween('updated_at', [$thisMonthStart, now()]);
                    });
            })
            ->count();
        $completedLastMonth = Task::where('status', TaskStatus::Completed->value)
            ->where(function ($q) use ($lastMonthStart, $lastMonthEnd) {
                $q->whereBetween('completed_at', [$lastMonthStart, $lastMonthEnd])
                    ->orWhere(function ($sub) use ($lastMonthStart, $lastMonthEnd) {
                        $sub->whereNull('completed_at')->whereBetween('updated_at', [$lastMonthStart, $lastMonthEnd]);
                    });
            })
            ->count();
        $completedDelta = $this->calculateDeltaPercentage($completedThisMonth, $completedLastMonth);

        // Monthly completion rate delta (comparing this month's rate vs last month's rate)
        $totalCreatedThisMonth = Task::whereBetween('created_at', [$thisMonthStart, now()])->count();
        $totalCreatedLastMonth = Task::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();

        $rateThisMonth = $totalCreatedThisMonth > 0 ? round(($completedThisMonth / $totalCreatedThisMonth) * 100) : $completionRate;
        $rateLastMonth = $totalCreatedLastMonth > 0 ? round(($completedLastMonth / $totalCreatedLastMonth) * 100) : 0;
        $rateDiff = $rateThisMonth - $rateLastMonth;
        $ratePrefix = $rateDiff >= 0 ? '+' : '';
        $rateDirection = $rateDiff > 0 ? 'up' : ($rateDiff < 0 ? 'down' : 'neutral');

        return [
            'pending_tasks' => [
                'count' => $pendingTasks,
                'total_reference' => $totalTasks,
                'fraction' => "{$pendingTasks}/{$totalTasks}",
                'percentage_change' => $pendingDelta['formatted'],
                'trend' => $pendingDelta['direction'],
            ],
            'in_progress_tasks' => [
                'count' => $inProgressTasks,
                'total_reference' => $totalTasks,
                'fraction' => "{$inProgressTasks}/{$totalTasks}",
                'percentage_change' => $inProgressDelta['formatted'],
                'trend' => $inProgressDelta['direction'],
            ],
            'completed_tasks' => [
                'count' => $completedTasks,
                'total_reference' => $totalTasks,
                'fraction' => "{$completedTasks}/{$totalTasks}",
                'percentage_change' => $completedDelta['formatted'],
                'trend' => $completedDelta['direction'],
            ],
            'completion_rate' => [
                'percentage' => $completionRate,
                'percentage_change' => "{$ratePrefix}{$rateDiff}%",
                'trend' => $rateDirection,
            ],
        ];
    }

    /**
     * Get monthly velocity chart data for the line/area chart.
     *
     * @return array<string, mixed>
     */
    public function getProjectOverviewChart(?int $projectId = null, ?int $year = null): array
    {
        $year = $year ?? now()->year;
        $projectName = 'All Projects';

        if ($projectId) {
            $project = Project::find($projectId);
            if ($project) {
                $projectName = $project->name;
            }
        }

        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $chartData = [];

        for ($month = 1; $month <= 12; $month++) {
            $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $end = Carbon::createFromDate($year, $month, 1)->endOfMonth();

            $taskQuery = Task::whereBetween('created_at', [$start, $end]);
            if ($projectId) {
                $taskQuery->where('project_id', $projectId);
            }

            $created = (clone $taskQuery)->count();
            $completed = (clone $taskQuery)->where('status', TaskStatus::Completed->value)->count();

            // Velocity representation for chart curve
            $velocity = ($completed * 10) + ($created * 5);

            $chartData[] = [
                'month' => $monthNames[$month - 1],
                'month_number' => $month,
                'completed_tasks' => $completed,
                'created_tasks' => $created,
                'velocity_score' => $velocity,
            ];
        }

        return [
            'year' => $year,
            'project_name' => $projectName,
            'project_id' => $projectId,
            'months' => $chartData,
        ];
    }

    /**
     * Get active projects with task progress fractions.
     */
    public function getActiveProjects(int $limit = 5): Collection
    {
        return Project::active()
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn ($q) => $q->where('status', TaskStatus::Completed->value),
            ])
            ->orderBy('priority', 'asc')
            ->take($limit)
            ->get();
    }

    /**
     * Get recent files with uploader and project details.
     */
    public function getRecentFiles(int $limit = 5): Collection
    {
        return File::with(['uploader', 'project'])
            ->orderByDesc('created_at')
            ->take($limit)
            ->get();
    }

    /**
     * Get urgent tasks (high priority, nearest due dates) for the dashboard tasks widget.
     */
    public function getUrgentTasks(int $limit = 5): Collection
    {
        return Task::with(['project', 'assignees'])
            ->where('status', '!=', TaskStatus::Completed->value)
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date ASC')
            ->orderBy('priority', 'asc')
            ->take($limit)
            ->get();
    }

    /**
     * Get team overview members with task completion fractions (e.g. 12/14).
     */
    public function getTeamOverview(?int $teamId = null, int $limit = 6): Collection
    {
        $query = User::withCount([
            'assignedTasks',
            'assignedTasks as completed_assigned_tasks_count' => fn ($q) => $q->where('status', TaskStatus::Completed->value),
        ])
            ->where('status', 'active');

        if ($teamId) {
            $query->whereHas('teams', fn ($q) => $q->where('teams.id', $teamId));
        }

        return $query->orderByDesc('assigned_tasks_count')
            ->take($limit)
            ->get();
    }

    /**
     * Get upcoming meetings with participant details.
     */
    public function getUpcomingMeetings(int $limit = 3): Collection
    {
        return Meeting::with('participants')
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->take($limit)
            ->get();
    }

    /**
     * Calculate delta percentage and trend direction.
     *
     * @return array{percentage: float, formatted: string, direction: string}
     */
    private function calculateDeltaPercentage(int $current, int $previous): array
    {
        if ($previous === 0) {
            return [
                'percentage' => $current > 0 ? 100.0 : 0.0,
                'formatted' => $current > 0 ? '+100%' : '0%',
                'direction' => $current > 0 ? 'up' : 'neutral',
            ];
        }

        $delta = round((($current - $previous) / $previous) * 100, 1);
        $prefix = $delta >= 0 ? '+' : '';

        return [
            'percentage' => $delta,
            'formatted' => "{$prefix}{$delta}%",
            'direction' => $delta >= 0 ? 'up' : 'down',
        ];
    }
}
