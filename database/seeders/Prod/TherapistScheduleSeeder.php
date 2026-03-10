<?php

namespace Database\Seeders\Prod;

use App\Models\Therapist;
use App\Models\TherapistSchedule;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class TherapistScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $therapists = Therapist::all();

        if ($therapists->isEmpty()) {
            return;
        }

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($therapists as $therapist) {
            // Assign recurring schedule for 5-6 days a week
            $workDays = array_rand(array_flip($days), rand(5, 6));
            if (!is_array($workDays))
                $workDays = [$workDays];

            foreach ($workDays as $day) {
                $shiftType = $therapist->shift ?: 'full_day';
                $startTime = '09:00:00';
                $endTime = '17:00:00';

                if ($shiftType === 'morning') {
                    $startTime = '08:00:00';
                    $endTime = '14:00:00';
                } elseif ($shiftType === 'afternoon') {
                    $startTime = '14:00:00';
                    $endTime = '20:00:00';
                }

                TherapistSchedule::updateOrCreate(
                    [
                        'therapist_id' => $therapist->id,
                        'day_of_week' => $day,
                    ],
                    [
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'shift_type' => $shiftType,
                        'is_active' => true,
                        // Set validity date from 6 months ago to 6 months ahead
                        'start_date' => Carbon::now()->subMonths(6)->toDateString(),
                        'end_date' => Carbon::now()->addMonths(6)->toDateString(),
                    ]
                );
            }

            // Also add some specific date schedules for this week to show in the calendar
            $startOfWeek = Carbon::now()->startOfWeek();
            for ($i = 0; $i < 7; $i++) {
                $currentDate = $startOfWeek->copy()->addDays($i);
                $dayName = strtolower($currentDate->format('l'));

                // If they are supposed to work today (recurrent), we don't need a specific date entry 
                // unless we want to override it. But for seeding, let's just make sure they have entries.

                TherapistSchedule::updateOrCreate(
                    [
                        'therapist_id' => $therapist->id,
                        'date' => $currentDate->toDateString(),
                    ],
                    [
                        'day_of_week' => $dayName,
                        'start_time' => '09:00:00',
                        'end_time' => '17:00:00',
                        'shift_type' => $therapist->shift ?: 'full_day',
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
