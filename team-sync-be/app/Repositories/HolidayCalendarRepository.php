<?php

namespace App\Repositories;

use App\Interfaces\HolidayCalendarRepositoryInterface;
use App\Models\HolidayCalendar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HolidayCalendarRepository implements HolidayCalendarRepositoryInterface
{
    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return HolidayCalendar::orderBy('date', 'asc')->paginate($perPage);
    }

    public function create(array $data): HolidayCalendar
    {
        return HolidayCalendar::create($data);
    }

    public function findById(int $id): HolidayCalendar
    {
        return HolidayCalendar::findOrFail($id);
    }

    public function update(int $id, array $data): HolidayCalendar
    {
        $holiday = $this->findById($id);
        $holiday->update($data);

        return $holiday->fresh();
    }

    public function delete(int $id): bool
    {
        $holiday = $this->findById($id);

        return $holiday->delete();
    }
}
