<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\GenerateReportRequest;
use App\Http\Resources\Reports\ReportResource;
use App\Http\Resources\Reports\ReportSummaryResource;
use App\Models\Report;
use App\Services\ReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * Get top performance KPI summary cards.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $projectId = $request->filled('project_id') ? (int) $request->query('project_id') : null;

        $summary = $this->reportService->getSummary($user, $projectId);

        return $this->success(
            new ReportSummaryResource($summary),
            'Report summary metrics retrieved successfully.'
        );
    }

    /**
     * Get Task Completion Time vs Deadline bar chart data.
     */
    public function deadlineComparison(Request $request): JsonResponse
    {
        $projectId = $request->filled('project_id') ? (int) $request->query('project_id') : null;
        $days = (int) $request->query('days', 7);

        $data = $this->reportService->getTaskDeadlineComparison($projectId, $days);

        return $this->success($data, 'Task deadline comparison chart data retrieved successfully.');
    }

    /**
     * Get Projects Progress area chart data.
     */
    public function projectProgress(Request $request): JsonResponse
    {
        $projectId = $request->filled('project_id') ? (int) $request->query('project_id') : null;

        $data = $this->reportService->getProjectsProgress($projectId);

        return $this->success($data, 'Projects progress chart data retrieved successfully.');
    }

    /**
     * Get Worker Activity Rate line chart data.
     */
    public function workerActivity(Request $request): JsonResponse
    {
        $userId = $request->filled('user_id') ? (int) $request->query('user_id') : null;
        $interval = (string) $request->query('interval', 'monthly');

        $data = $this->reportService->getWorkerActivity($userId, $interval);

        return $this->success($data, 'Worker activity chart data retrieved successfully.');
    }

    /**
     * List all generated reports with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('viewAny', Report::class)) {
            return $this->error([], 'Forbidden. You do not have permission to view reports.', 403);
        }

        $reports = $this->reportService->listReports($user, $request->all());

        return $this->success(
            ReportResource::collection($reports),
            'Reports retrieved successfully.'
        );
    }

    /**
     * Generate a new detailed report.
     */
    public function store(GenerateReportRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('create', Report::class)) {
            return $this->error([], 'Forbidden. You do not have permission to generate reports.', 403);
        }

        $report = $this->reportService->generateReport($user, $request->validated());

        return $this->success(
            new ReportResource($report),
            'Report generated successfully.',
            201
        );
    }

    /**
     * Get single report details.
     */
    public function show(Report $report): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('view', $report)) {
            return $this->error([], 'Forbidden. You do not have permission to view this report.', 403);
        }

        $loaded = $this->reportService->getReport($report);

        return $this->success(
            new ReportResource($loaded),
            'Report details retrieved successfully.'
        );
    }

    /**
     * Delete a report.
     */
    public function destroy(Report $report): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('delete', $report)) {
            return $this->error([], 'Forbidden. You do not have permission to delete this report.', 403);
        }

        $this->reportService->deleteReport($report);

        return $this->success([], 'Report deleted successfully.');
    }
}
