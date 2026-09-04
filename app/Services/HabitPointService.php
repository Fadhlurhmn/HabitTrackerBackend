<?php

namespace App\Services;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\Streak;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class HabitPointService
{
    private const BASE_POINTS = 10;
    private const STREAK_BONUS_PER_UNIT = 2;
    private const MAX_STREAK_BONUS = 50;

    public function checkIn(Habit $habit, Carbon $date): HabitLog
    {
        return DB::transaction(function () use ($habit, $date) {
            $existing = HabitLog::where('habit_id', $habit->id)
                ->where('log_date', $date->toDateString())
                ->first();

            if ($existing) {
                throw new Exception('Habit ini sudah di check-in pada tanggal tersebut.');
            }

            $streak = Streak::firstOrCreate(
                ['habit_id' => $habit->id],
                ['current_streak' => 0, 'longest_streak' => 0]
            );

            $newStreakCount = $this->calculateNewStreak($habit, $streak, $date);
            $pointsEarned = $this->calculatePoints($newStreakCount);

            $log = HabitLog::create([
                'habit_id' => $habit->id,
                'user_id' => $habit->user_id,
                'log_date' => $date->toDateString(),
                'completed' => true,
                'points_earned' => $pointsEarned,
            ]);

            // Untuk weekly, last_completed_date tetap kita update ke tanggal check-in
            // terbaru (bukan awal minggu), supaya histori tetap akurat menampilkan
            // kapan terakhir kali check-in dilakukan.
            $streak->update([
                'current_streak' => $newStreakCount,
                'longest_streak' => max($streak->longest_streak, $newStreakCount),
                'last_completed_date' => $date->toDateString(),
            ]);

            $habit->user->increment('total_points', $pointsEarned);

            return $log;
        });
    }

    public function undoCheckIn(Habit $habit, Carbon $date): void
    {
        DB::transaction(function () use ($habit, $date) {
            $log = HabitLog::where('habit_id', $habit->id)
                ->where('log_date', $date->toDateString())
                ->first();

            if (!$log) {
                throw new Exception('Tidak ada check-in untuk dibatalkan pada tanggal tersebut.');
            }

            $habit->user->decrement('total_points', $log->points_earned);

            $log->delete();

            $this->recalculateStreak($habit);
        });
    }

    /**
     * Hitung streak baru berdasarkan frequency habit.
     * - daily: streak berbasis selisih hari (harus persis 1 hari dari terakhir check-in)
     * - weekly: streak berbasis selisih minggu kalender (Senin-Minggu)
     */
    private function calculateNewStreak(Habit $habit, Streak $streak, Carbon $date): int
    {
        if (!$streak->last_completed_date) {
            return 1;
        }

        if ($habit->frequency === 'weekly') {
            $lastWeekStart = $streak->last_completed_date->copy()->startOfWeek();
            $currentWeekStart = $date->copy()->startOfWeek();
            $weeksDiff = $lastWeekStart->diffInWeeks($currentWeekStart);

            if ($weeksDiff === 0) {
                // Masih minggu yang sama, streak tidak berubah (sudah dihitung minggu ini)
                return $streak->current_streak;
            }
            if ($weeksDiff === 1) {
                return $streak->current_streak + 1;
            }
            return 1; // lompat lebih dari 1 minggu, streak putus
        }

        // Daily
        $daysSinceLastCompleted = $streak->last_completed_date->diffInDays($date);

        if ($daysSinceLastCompleted === 1) {
            return $streak->current_streak + 1;
        }
        if ($daysSinceLastCompleted === 0) {
            return $streak->current_streak;
        }

        return 1;
    }

    private function calculatePoints(int $streakCount): int
    {
        $bonus = min(($streakCount - 1) * self::STREAK_BONUS_PER_UNIT, self::MAX_STREAK_BONUS);
        return self::BASE_POINTS + $bonus;
    }

    /**
     * Recalculate streak dari awal berdasarkan semua log yang ada.
     * Dipanggil setelah undo check-in supaya streak tetap akurat walau
     * yang dihapus bukan check-in paling akhir.
     */
    private function recalculateStreak(Habit $habit): void
    {
        $logs = HabitLog::where('habit_id', $habit->id)
            ->orderBy('log_date')
            ->get();

        if ($habit->frequency === 'weekly') {
            $this->recalculateWeeklyStreak($habit, $logs);
            return;
        }

        $this->recalculateDailyStreak($habit, $logs);
    }

    private function recalculateDailyStreak(Habit $habit, $logs): void
    {
        $currentStreak = 0;
        $longestStreak = 0;
        $lastDate = null;

        foreach ($logs as $log) {
            if ($lastDate === null || $log->log_date->diffInDays($lastDate) === 1) {
                $currentStreak++;
            } else {
                $currentStreak = 1;
            }
            $longestStreak = max($longestStreak, $currentStreak);
            $lastDate = $log->log_date;
        }

        Streak::updateOrCreate(
            ['habit_id' => $habit->id],
            [
                'current_streak' => $logs->isEmpty() ? 0 : $currentStreak,
                'longest_streak' => $longestStreak,
                'last_completed_date' => $lastDate,
            ]
        );
    }

    private function recalculateWeeklyStreak(Habit $habit, $logs): void
    {
        // Ambil daftar minggu unik (berdasarkan awal minggu) yang punya minimal 1 check-in
        $weeks = $logs
            ->map(fn ($log) => $log->log_date->copy()->startOfWeek()->toDateString())
            ->unique()
            ->sort()
            ->values();

        $currentStreak = 0;
        $longestStreak = 0;
        $lastWeek = null;

        foreach ($weeks as $weekString) {
            $week = Carbon::parse($weekString);
            if ($lastWeek === null || $lastWeek->diffInWeeks($week) === 1) {
                $currentStreak++;
            } else {
                $currentStreak = 1;
            }
            $longestStreak = max($longestStreak, $currentStreak);
            $lastWeek = $week;
        }

        Streak::updateOrCreate(
            ['habit_id' => $habit->id],
            [
                'current_streak' => $weeks->isEmpty() ? 0 : $currentStreak,
                'longest_streak' => $longestStreak,
                'last_completed_date' => $logs->isEmpty() ? null : $logs->last()->log_date,
            ]
        );
    }
}