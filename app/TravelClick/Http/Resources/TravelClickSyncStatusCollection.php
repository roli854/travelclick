<?php

namespace App\TravelClick\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * TravelClick Sync Status Collection Resource
 *
 * Handles collections of TravelClickSyncStatus resources with additional
 * aggregation and summary data. Think of this as a dashboard summary
 * that provides insights across multiple synchronization statuses.
 */
class TravelClickSyncStatusCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects
     */
    public $collects = TravelClickSyncStatusResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'summary' => $this->getSummaryStatistics(),
            'health_overview' => $this->getHealthOverview(),
            'status_distribution' => $this->getStatusDistribution(),
            'recent_activity' => $this->getRecentActivity(),
            'recommendations' => $this->getRecommendations(),
        ];
    }

    /**
     * Get summary statistics for the collection
     */
    private function getSummaryStatistics(): array
    {
        $collection = $this->collection;
        $total = $collection->count();

        if ($total === 0) {
            return [
                'total_syncs' => 0,
                'message' => 'No synchronization statuses found',
            ];
        }

        $successful = $collection->filter(fn($item) => $item->Status->isSuccess())->count();
        $failed = $collection->filter(fn($item) => $item->Status->isFailure())->count();
        $inProgress = $collection->filter(fn($item) => $item->Status->isInProgress())->count();

        $avgHealthScore = $collection->avg(fn($item) => $item->getSyncHealthScoreAttribute());
        $avgSuccessRate = $collection->where('SuccessRate', '>', 0)->avg('SuccessRate');

        return [
            'total_syncs' => $total,
            'successful_syncs' => $successful,
            'failed_syncs' => $failed,
            'in_progress_syncs' => $inProgress,
            'success_percentage' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            'average_health_score' => round($avgHealthScore, 2),
            'average_success_rate' => round($avgSuccessRate ?? 0, 2),
            'needs_attention_count' => $collection->filter(fn($item) => $item->needsAttention())->count(),
        ];
    }

    /**
     * Get health overview across the collection
     */
    private function getHealthOverview(): array
    {
        $collection = $this->collection;

        $healthCategories = [
            'excellent' => $collection->filter(fn($item) => $item->getSyncHealthScoreAttribute() >= 90)->count(),
            'good' => $collection->filter(fn($item) => $item->getSyncHealthScoreAttribute() >= 80 && $item->getSyncHealthScoreAttribute() < 90)->count(),
            'fair' => $collection->filter(fn($item) => $item->getSyncHealthScoreAttribute() >= 60 && $item->getSyncHealthScoreAttribute() < 80)->count(),
            'poor' => $collection->filter(fn($item) => $item->getSyncHealthScoreAttribute() >= 40 && $item->getSyncHealthScoreAttribute() < 60)->count(),
            'critical' => $collection->filter(fn($item) => $item->getSyncHealthScoreAttribute() < 40)->count(),
        ];

        $overallHealth = $collection->avg(fn($item) => $item->getSyncHealthScoreAttribute());

        $healthStatus = match (true) {
            $overallHealth >= 90 => 'excellent',
            $overallHealth >= 80 => 'good',
            $overallHealth >= 60 => 'fair',
            $overallHealth >= 40 => 'poor',
            default => 'critical',
        };

        return [
            'overall_health_score' => round($overallHealth, 2),
            'overall_health_status' => $healthStatus,
            'distribution' => $healthCategories,
            'percentage_distribution' => [
                'excellent' => $collection->count() > 0 ? round(($healthCategories['excellent'] / $collection->count()) * 100, 1) : 0,
                'good' => $collection->count() > 0 ? round(($healthCategories['good'] / $collection->count()) * 100, 1) : 0,
                'fair' => $collection->count() > 0 ? round(($healthCategories['fair'] / $collection->count()) * 100, 1) : 0,
                'poor' => $collection->count() > 0 ? round(($healthCategories['poor'] / $collection->count()) * 100, 1) : 0,
                'critical' => $collection->count() > 0 ? round(($healthCategories['critical'] / $collection->count()) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * Get status distribution across the collection
     */
    private function getStatusDistribution(): array
    {
        $collection = $this->collection;
        $total = $collection->count();

        $statusCounts = $collection->groupBy(fn($item) => $item->Status->value)
            ->map(fn($group) => $group->count())
            ->toArray();

        $statusPercentages = [];
        foreach ($statusCounts as $status => $count) {
            $statusPercentages[$status] = $total > 0 ? round(($count / $total) * 100, 1) : 0;
        }

        // Group by message type
        $messageTypeDistribution = $collection->groupBy(fn($item) => $item->MessageType->value)
            ->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'avg_health_score' => round($group->avg(fn($item) => $item->getSyncHealthScoreAttribute()), 2),
                    'successful' => $group->filter(fn($item) => $item->Status->isSuccess())->count(),
                    'failed' => $group->filter(fn($item) => $item->Status->isFailure())->count(),
                    'in_progress' => $group->filter(fn($item) => $item->Status->isInProgress())->count(),
                ];
            })
            ->toArray();

        return [
            'by_status' => [
                'counts' => $statusCounts,
                'percentages' => $statusPercentages,
            ],
            'by_message_type' => $messageTypeDistribution,
        ];
    }

    /**
     * Get recent activity summary
     */
    private function getRecentActivity(): array
    {
        $collection = $this->collection;

        // Last 24 hours activity
        $last24Hours = $collection->filter(function ($item) {
            return $item->LastSyncAttempt && $item->LastSyncAttempt->isAfter(now()->subDay());
        });

        // Last successful syncs
        $recentlySuccessful = $collection->filter(function ($item) {
            return $item->LastSuccessfulSync && $item->LastSuccessfulSync->isAfter(now()->subHours(6));
        });

        // Recent failures
        $recentFailures = $collection->filter(function ($item) {
            return $item->hasFailed() && $item->LastSyncAttempt && $item->LastSyncAttempt->isAfter(now()->subHours(2));
        });

        return [
            'last_24_hours' => [
                'total_attempts' => $last24Hours->count(),
                'successful' => $last24Hours->filter(fn($item) => $item->Status->isSuccess())->count(),
                'failed' => $last24Hours->filter(fn($item) => $item->Status->isFailure())->count(),
            ],
            'recent_successes' => $recentlySuccessful->count(),
            'recent_failures' => $recentFailures->count(),
            'most_recent_sync' => $collection->sortByDesc('LastSyncAttempt')->first()?->LastSyncAttempt?->toISOString(),
            'oldest_pending_sync' => $collection->where('Status.value', 'pending')->sortBy('DateCreated')->first()?->DateCreated?->toISOString(),
        ];
    }

    /**
     * Get recommendations based on the collection analysis
     */
    private function getRecommendations(): array
    {
        $collection = $this->collection;
        $recommendations = [];

        // Check for long-running syncs
        $longRunning = $collection->filter(function ($item) {
            return $item->isRunning() && $item->LastSyncAttempt &&
                $item->LastSyncAttempt->diffInMinutes(now()) > 30;
        });

        if ($longRunning->count() > 0) {
            $recommendations[] = [
                'type' => 'warning',
                'priority' => 'high',
                'title' => 'Long Running Syncs Detected',
                'description' => "Found {$longRunning->count()} sync(s) running longer than 30 minutes. Consider investigating or cancelling them.",
                'action' => 'investigate_long_running',
                'affected_count' => $longRunning->count(),
            ];
        }

        // Check for high failure rate
        $failureRate = $collection->count() > 0 ?
            ($collection->filter(fn($item) => $item->Status->isFailure())->count() / $collection->count()) * 100 : 0;

        if ($failureRate > 20) {
            $recommendations[] = [
                'type' => 'error',
                'priority' => 'critical',
                'title' => 'High Failure Rate',
                'description' => sprintf("Current failure rate is %.1f%%. This requires immediate attention.", $failureRate),
                'action' => 'review_failed_syncs',
                'affected_count' => $collection->filter(fn($item) => $item->Status->isFailure())->count(),
            ];
        }

        // Check for disabled auto-retry on failed syncs
        $disabledAutoRetry = $collection->filter(function ($item) {
            return $item->hasFailed() && !$item->AutoRetryEnabled;
        });

        if ($disabledAutoRetry->count() > 0) {
            $recommendations[] = [
                'type' => 'info',
                'priority' => 'medium',
                'title' => 'Auto-Retry Disabled on Failed Syncs',
                'description' => "Found {$disabledAutoRetry->count()} failed sync(s) with auto-retry disabled. Consider enabling auto-retry or manual intervention.",
                'action' => 'enable_auto_retry',
                'affected_count' => $disabledAutoRetry->count(),
            ];
        }

        // Check for low health scores
        $lowHealth = $collection->filter(fn($item) => $item->getSyncHealthScoreAttribute() < 60);

        if ($lowHealth->count() > 0) {
            $recommendations[] = [
                'type' => 'warning',
                'priority' => 'medium',
                'title' => 'Low Health Score Syncs',
                'description' => "Found {$lowHealth->count()} sync(s) with health score below 60. Review these for optimization opportunities.",
                'action' => 'optimize_low_health',
                'affected_count' => $lowHealth->count(),
            ];
        }

        // Check for syncs that haven't run recently
        $staleSync = $collection->filter(function ($item) {
            return !$item->LastSuccessfulSync || $item->LastSuccessfulSync->isBefore(now()->subDays(7));
        });

        if ($staleSync->count() > 0) {
            $recommendations[] = [
                'type' => 'warning',
                'priority' => 'low',
                'title' => 'Stale Synchronizations',
                'description' => "Found {$staleSync->count()} sync(s) that haven't been successful in the last 7 days.",
                'action' => 'review_stale_syncs',
                'affected_count' => $staleSync->count(),
            ];
        }

        // Sort recommendations by priority
        $priorityOrder = ['critical' => 1, 'high' => 2, 'medium' => 3, 'low' => 4];
        usort($recommendations, function ($a, $b) use ($priorityOrder) {
            return $priorityOrder[$a['priority']] <=> $priorityOrder[$b['priority']];
        });

        return $recommendations;
    }

    /**
     * Add additional metadata to the response
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'total_count' => $this->collection->count(),
                'generated_at' => now()->toISOString(),
                'collection_version' => '1.0',
                'filters_applied' => $request->has('filters'),
                'includes_property_data' => $request->has('include') &&
                    str_contains($request->get('include'), 'property'),
            ],
            'links' => [
                'self' => $request->url(),
                'related' => [
                    'logs' => route('travelclick.logs.index'),
                    'properties' => route('properties.index'),
                    'dashboard' => route('travelclick.dashboard'),
                ],
            ],
        ];
    }

    /**
     * Customize the HTTP response for the collection
     */
    public function withResponse(Request $request, $response): void
    {
        // Add custom headers for the collection
        $healthOverview = $this->getHealthOverview();
        $summary = $this->getSummaryStatistics();

        $response->headers->set('X-Collection-Count', $this->collection->count());
        $response->headers->set('X-Overall-Health-Score', $healthOverview['overall_health_score']);
        $response->headers->set('X-Success-Percentage', $summary['success_percentage']);
        $response->headers->set('X-Needs-Attention-Count', $summary['needs_attention_count']);
    }
}
