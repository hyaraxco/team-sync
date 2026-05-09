<?php

namespace App\Interfaces;

use App\Models\HolidayCalendar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface HolidayCalendarRepositoryInterface
{
    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): HolidayCalendar;

    public function findById(int $id): HolidayCalendar;

    public function update(int $id, array $data): HolidayCalendar;

    public function delete(int $id): bool;
}
