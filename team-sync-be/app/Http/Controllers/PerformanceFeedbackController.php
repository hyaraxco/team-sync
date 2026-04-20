<?php

namespace App\Http\Controllers;

use App\DTOs\Performance\FeedbackDto;
use App\Helpers\ResponseHelper;
use App\Interfaces\PerformanceFeedbackRepositoryInterface;
use App\Http\Requests\Performance\CreateFeedbackRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class PerformanceFeedbackController extends Controller
{
    public function __construct(
        private PerformanceFeedbackRepositoryInterface $repository
    ) {}

    public function getReceivedFeedback(Request $request)
    {
        // Don't show private feedback to the employee if they are not the manager/HR (handled by logic inside or around repository depending on rule)
        // For simplicity, the rule states private is visible to employee and manager.
        $filters = $request->all();
        $feedback = $this->repository->getFeedbackForEmployee(Auth::user()->employeeProfile?->id, $filters);
        return ResponseHelper::jsonResponse(true, 'Received feedback retrieved successfully', $feedback);
    }

    public function getGivenFeedback(Request $request)
    {
        $feedback = $this->repository->getFeedbackGivenByUser(Auth::user()->employeeProfile?->id, $request->all());
        return ResponseHelper::jsonResponse(true, 'Given feedback retrieved successfully', $feedback);
    }

    public function store(CreateFeedbackRequest $request)
    {
        $dto = FeedbackDto::fromRequest($request->validated());
        $feedback = $this->repository->createFeedback($dto->toArray());
        return ResponseHelper::jsonResponse(true, 'Feedback created successfully', $feedback, 201);
    }

    public function show(int $id)
    {
        $feedback = $this->repository->getFeedbackById($id);
        return ResponseHelper::jsonResponse(true, 'Feedback retrieved successfully', $feedback);
    }

    public function acknowledge(int $id)
    {
        $feedback = $this->repository->acknowledgeFeedback($id);
        return ResponseHelper::jsonResponse(true, 'Feedback acknowledged successfully', $feedback);
    }
}
