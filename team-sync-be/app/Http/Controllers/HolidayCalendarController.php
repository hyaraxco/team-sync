<?php

namespace App\Http\Controllers;

use App\Http\Requests\HolidayCalendar\CreateHolidayRequest;
use App\Http\Requests\HolidayCalendar\UpdateHolidayRequest;
use App\Models\HolidayCalendar;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HolidayCalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $holidays = HolidayCalendar::orderBy('date', 'asc')->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $holidays,
        ]);
    }

    public function store(CreateHolidayRequest $request): JsonResponse
    {
        $holiday = HolidayCalendar::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil ditambahkan.',
            'data' => $holiday,
        ], 201);
    }

    public function show(HolidayCalendar $holidayCalendar): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $holidayCalendar,
        ]);
    }

    public function update(UpdateHolidayRequest $request, HolidayCalendar $holidayCalendar): JsonResponse
    {
        $holidayCalendar->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil diperbarui.',
            'data' => $holidayCalendar,
        ]);
    }

    public function destroy(HolidayCalendar $holidayCalendar): JsonResponse
    {
        $holidayCalendar->delete();

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil dihapus.',
        ]);
    }
}
