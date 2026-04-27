<?php

namespace Database\Seeders;

use App\Models\HolidayCalendar;
use Illuminate\Database\Seeder;

class HolidayCalendarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $holidays = [
            ['date' => '2026-01-01', 'name' => 'Tahun Baru 2026 Masehi', 'type' => 'national_holiday'],
            ['date' => '2026-01-16', 'name' => "Isra Mi'raj Nabi Muhammad SAW", 'type' => 'national_holiday'],
            ['date' => '2026-02-16', 'name' => 'Cuti Bersama Tahun Baru Imlek', 'type' => 'collective_leave'],
            ['date' => '2026-02-17', 'name' => 'Tahun Baru Imlek 2577 Kongzili', 'type' => 'national_holiday'],
            ['date' => '2026-03-18', 'name' => 'Cuti Bersama Hari Raya Nyepi', 'type' => 'collective_leave'],
            ['date' => '2026-03-19', 'name' => 'Hari Suci Nyepi Tahun Baru Saka 1948', 'type' => 'national_holiday'],
            ['date' => '2026-03-20', 'name' => 'Hari Raya Idul Fitri 1447 Hijriah', 'type' => 'national_holiday'],
            ['date' => '2026-03-21', 'name' => 'Hari Raya Idul Fitri 1447 Hijriah', 'type' => 'national_holiday'],
            ['date' => '2026-04-03', 'name' => 'Wafat Isa Al Masih', 'type' => 'national_holiday'],
            ['date' => '2026-05-01', 'name' => 'Hari Buruh Internasional', 'type' => 'national_holiday'],
            ['date' => '2026-05-14', 'name' => 'Kenaikan Isa Al Masih', 'type' => 'national_holiday'],
            ['date' => '2026-05-31', 'name' => 'Hari Raya Waisak 2570 BE', 'type' => 'national_holiday'],
            ['date' => '2026-06-01', 'name' => 'Hari Lahir Pancasila', 'type' => 'national_holiday'],
            ['date' => '2026-06-27', 'name' => 'Hari Raya Idul Adha 1447 Hijriah', 'type' => 'national_holiday'],
            ['date' => '2026-07-17', 'name' => 'Tahun Baru Islam 1448 Hijriah', 'type' => 'national_holiday'],
            ['date' => '2026-08-17', 'name' => 'Hari Kemerdekaan Republik Indonesia', 'type' => 'national_holiday'],
            ['date' => '2026-09-25', 'name' => 'Maulid Nabi Muhammad SAW', 'type' => 'national_holiday'],
            ['date' => '2026-12-25', 'name' => 'Hari Raya Natal', 'type' => 'national_holiday'],
            ['date' => '2026-12-26', 'name' => 'Cuti Bersama Hari Raya Natal', 'type' => 'collective_leave'],
        ];

        foreach ($holidays as $holiday) {
            HolidayCalendar::updateOrCreate(
                ['date' => $holiday['date'], 'name' => $holiday['name']],
                ['type' => $holiday['type'], 'applies_to' => ['full_time', 'contract', 'intern', 'part_time']]
            );
        }
    }
}
