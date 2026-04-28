<?php

namespace App\Console\Commands;

use App\Models\PerformanceReview;
use App\Services\Performance\ReviewerResolverService;
use Illuminate\Console\Command;

class FixReviewerAssignments extends Command
{
    protected $signature = 'reviews:fix-reviewers {--dry-run : Show changes without applying}';

    protected $description = 'Fix reviewer assignments for pending reviews';

    public function __construct(
        private ReviewerResolverService $reviewerResolverService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $reviews = PerformanceReview::with(['staffMember.user'])
            ->whereIn('status', ['pending_self', 'pending_manager'])
            ->orderBy('id')
            ->get();

        if ($reviews->isEmpty()) {
            $this->info('No pending reviews found for reviewer fixes.');

            return self::SUCCESS;
        }

        $rows = [];
        $processed = 0;
        $changed = 0;
        $unchanged = 0;
        $clearedSelfAssignments = 0;
        $skipped = 0;

        foreach ($reviews as $review) {
            $processed++;

            if (! $review->staffMember) {
                $skipped++;
                $rows[] = [
                    $review->id,
                    $review->status,
                    $review->staff_member_id,
                    $review->reviewer_id,
                    null,
                    'skipped_missing_staff_member',
                ];

                continue;
            }

            $resolvedReviewerId = $this->reviewerResolverService->resolve($review->staffMember)?->id;

            if ($resolvedReviewerId === $review->staff_member_id) {
                $resolvedReviewerId = null;
            }

            if ($review->reviewer_id === $review->staff_member_id && $resolvedReviewerId === null) {
                $clearedSelfAssignments++;
            }

            if ($resolvedReviewerId === $review->reviewer_id) {
                $unchanged++;

                continue;
            }

            if (! $isDryRun) {
                $review->update([
                    'reviewer_id' => $resolvedReviewerId,
                ]);
            }

            $changed++;
            $rows[] = [
                $review->id,
                $review->status,
                $review->staff_member_id,
                $review->reviewer_id,
                $resolvedReviewerId,
                $isDryRun ? 'would_update' : 'updated',
            ];
        }

        if (empty($rows)) {
            $this->info('No reviewer assignment changes needed.');
        } else {
            $this->table(
                ['Review ID', 'Status', 'Staff Member ID', 'Current Reviewer ID', 'Resolved Reviewer ID', 'Action'],
                $rows
            );
        }

        $this->newLine();
        $this->info('Reviewer assignment fix summary');
        $this->line('Mode: '.($isDryRun ? 'dry-run' : 'apply'));
        $this->line('Processed: '.$processed);
        $this->line('Changed: '.$changed);
        $this->line('Unchanged: '.$unchanged);
        $this->line('Self-assignments cleared to null: '.$clearedSelfAssignments);
        $this->line('Skipped: '.$skipped);

        return self::SUCCESS;
    }
}
