<?php

namespace App\Http\Controllers;

use App\DTOs\Performance\FeedbackDto;
use App\Helpers\ResponseHelper;
use App\Http\Requests\Performance\CreateFeedbackRequest;
use App\Interfaces\PerformanceFeedbackRepositoryInterface;
use App\Models\StaffMemberProfile;
use App\Notifications\Performance\FeedbackReceived;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\Middleware\PermissionMiddleware;

class PerformanceFeedbackController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            // Viewing feedback (received, given, detail): needs feedback-give as minimum baseline
            new Middleware(
                PermissionMiddleware::using('feedback-give'),
                only: ['getReceivedFeedback', 'getGivenFeedback', 'show', 'acknowledge']
            ),
            // Giving feedback
            new Middleware(
                PermissionMiddleware::using('feedback-give'),
                only: ['store']
            ),
        ];
    }

    public function __construct(
        private PerformanceFeedbackRepositoryInterface $repository
    ) {}

    public function getReceivedFeedback(Request $request)
    {
        $filters = $request->all();
        $feedback = $this->repository->getFeedbackForEmployee(Auth::user()->staffMemberProfile?->id, $filters);

        return ResponseHelper::jsonResponse(true, 'Received feedback retrieved successfully', $feedback);
    }

    public function getGivenFeedback(Request $request)
    {
        $feedback = $this->repository->getFeedbackGivenByUser(Auth::user()->staffMemberProfile?->id, $request->all());

        return ResponseHelper::jsonResponse(true, 'Given feedback retrieved successfully', $feedback);
    }

    public function store(CreateFeedbackRequest $request)
    {
        $dto = FeedbackDto::fromRequest($request->validated());
        $feedback = $this->repository->createFeedback($dto->toArray());

        // Dispatch notification to the feedback recipient
        $recipientProfile = StaffMemberProfile::with('user')->find($feedback->staff_member_id);
        if ($recipientProfile?->user) {
            $giverName = Auth::user()->name ?? 'Someone';
            $recipientProfile->user->notify(new FeedbackReceived(
                feedbackId: $feedback->id,
                giverName: $giverName,
                feedbackType: $feedback->feedback_type,
                contentPreview: Str::limit($feedback->content, 80),
            ));
        }

        return ResponseHelper::jsonResponse(true, 'Feedback created successfully', $feedback, 201);
    }

    public function show(int $id)
    {
        $feedback = $this->repository->getFeedbackById($id);

        // Ownership check: only the recipient or the giver can view a specific feedback
        $user = Auth::user();
        $staffMemberId = $user->staffMemberProfile?->id;
        $isRecipient = (int) $feedback->staff_member_id === (int) $staffMemberId;
        $isGiver = (int) $feedback->given_by === (int) $staffMemberId;

        if (! $isRecipient && ! $isGiver && ! $user->can('performance-analytics-view')) {
            return ResponseHelper::jsonResponse(false, 'Forbidden.', null, 403);
        }

        return ResponseHelper::jsonResponse(true, 'Feedback retrieved successfully', $feedback);
    }

    public function acknowledge(int $id)
    {
        $feedback = $this->repository->getFeedbackById($id);

        // Ownership check: only the feedback recipient can acknowledge it
        $user = Auth::user();
        if ($feedback->staff_member_id !== $user->staffMemberProfile?->id) {
            return ResponseHelper::jsonResponse(false, 'You can only acknowledge feedback addressed to you.', null, 403);
        }

        $feedback = $this->repository->acknowledgeFeedback($id);

        return ResponseHelper::jsonResponse(true, 'Feedback acknowledged successfully', $feedback);
    }
}
