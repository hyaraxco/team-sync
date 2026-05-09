<?php

namespace App\Http\Controllers;

use App\Http\Requests\HolidayCalendar\CreateHolidayRequest;
use App\Http\Requests\HolidayCalendar\UpdateHolidayRequest;
use App\Interfaces\HolidayCalendarRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class HolidayCalendarController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly HolidayCalendarRepositoryInterface $holidayCalendarRepository
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware(PermissionMiddleware::using('attendance-menu'), only: ['store', 'show', 'update', 'destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $holidays = $this->holidayCalendarRepository->getAllPaginated($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $holidays,
        ]);
    }

    public function store(CreateHolidayRequest $request): JsonResponse
    {
        $holiday = $this->holidayCalendarRepository->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil ditambahkan.',
            'data' => $holiday,
        ], 201);
    }

    public function show(int $holidayCalendar): JsonResponse
    {
        $holiday = $this->holidayCalendarRepository->findById($holidayCalendar);

        return response()->json([
            'success' => true,
            'data' => $holiday,
        ]);
    }

    public function update(UpdateHolidayRequest $request, int $holidayCalendar): JsonResponse
    {
        $holiday = $this->holidayCalendarRepository->update($holidayCalendar, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil diperbarui.',
            'data' => $holiday,
        ]);
    }

    public function destroy(int $holidayCalendar): JsonResponse
    {
        $this->holidayCalendarRepository->delete($holidayCalendar);

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil dihapus.',
        ]);
    }
}
