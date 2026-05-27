<?php

namespace App\Http\Controllers;

use App\Http\Requests\HolidayCalendar\CreateHolidayRequest;
use App\Http\Requests\HolidayCalendar\UpdateHolidayRequest;
use App\Http\Resources\HolidayCalendarResource;
use App\Interfaces\HolidayCalendarRepositoryInterface;
use App\Models\HolidayCalendar;
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
        $perPage = filter_var($request->input('per_page', 15), FILTER_VALIDATE_INT, [
            'options' => ['default' => 15, 'min_range' => 1],
        ]);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $holidays */
        $holidays = $this->holidayCalendarRepository->getAllPaginated($perPage);
        $holidays->setCollection($holidays->getCollection()->map(
            fn (HolidayCalendar $holiday): array => (new HolidayCalendarResource($holiday))->resolve($request)
        ));

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
            'data' => new HolidayCalendarResource($holiday),
        ], 201);
    }

    public function show(int $holidayCalendar): JsonResponse
    {
        $holiday = $this->holidayCalendarRepository->findById($holidayCalendar);

        return response()->json([
            'success' => true,
            'data' => new HolidayCalendarResource($holiday),
        ]);
    }

    public function update(UpdateHolidayRequest $request, int $holidayCalendar): JsonResponse
    {
        $holiday = $this->holidayCalendarRepository->update($holidayCalendar, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil diperbarui.',
            'data' => new HolidayCalendarResource($holiday),
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
