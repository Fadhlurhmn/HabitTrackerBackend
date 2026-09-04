<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();

        $activeHabits = $user->habits()->where('is_active', true)->count();

        $longestStreakOverall = $user->habits()
            ->join('streaks', 'habits.id', '=', 'streaks.habit_id')
            ->max('streaks.longest_streak') ?? 0;

        $todayCompletedCount = HabitLog::where('user_id', $user->id)
            ->where('log_date', Carbon::today()->toDateString())
            ->count();

        return response()->json([
            'total_points' => $user->total_points,
            'active_habits' => $activeHabits,
            'longest_streak_overall' => (int) $longestStreakOverall,
            'today_completed_count' => $todayCompletedCount,
        ]);
    }

    /**
     * Ringkasan quest dengan status 7 hari terakhir (hari ini + 6 hari sebelumnya),
     * supaya user bisa lihat & toggle check-in bukan cuma hari ini saja.
     */
    public function week(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();

        // Bangun array 7 tanggal, dari 6 hari lalu sampai hari ini
        $days = collect(range(6, 0))->map(fn ($i) => $today->copy()->subDays($i));
        $dayStrings = $days->map->toDateString();

        $habits = $user->habits()
            ->where('is_active', true)
            ->with('streak')
            ->get();

        $result = $habits->map(function (Habit $habit) use ($days, $dayStrings) {
            $logs = HabitLog::where('habit_id', $habit->id)
                ->whereIn('log_date', $dayStrings)
                ->get()
                ->keyBy(fn ($log) => $log->log_date->toDateString());

            $dayEntries = $days->map(function (Carbon $day) use ($logs) {
                $key = $day->toDateString();
                return [
                    'date' => $key,
                    'label' => $day->translatedFormat('D'),
                    'is_today' => $day->isToday(),
                    'completed' => $logs->has($key),
                ];
            });

            return [
                'id' => $habit->id,
                'name' => $habit->name,
                'frequency' => $habit->frequency,
                'current_streak' => $habit->streak->current_streak ?? 0,
                'days' => $dayEntries,
            ];
        });

        return response()->json($result);
    }

    /**
     * Breakdown statistik mingguan untuk satu habit, dalam rentang bulan+tahun tertentu.
     * - Habit daily: persentase = jumlah hari check-in / 7 hari dalam minggu itu
     * - Habit weekly: persentase = 100 kalau minimal 1x check-in di minggu itu, else 0
     */
    public function weeklyStats(Request $request, Habit $habit)
    {
        if ($habit->user_id !== (int) $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $weeks = [];
        $cursor = $monthStart->copy()->startOfWeek();

        while ($cursor->lte($monthEnd)) {
            $weekStart = $cursor->copy();
            $weekEnd = $cursor->copy()->endOfWeek();

            $completedCount = HabitLog::where('habit_id', $habit->id)
                ->whereBetween('log_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->count();

            $percentage = $habit->frequency === 'weekly'
                ? ($completedCount >= 1 ? 100 : 0)
                : round(($completedCount / 7) * 100);

            $weeks[] = [
                'label' => $weekStart->format('d M') . ' - ' . $weekEnd->format('d M'),
                'start_date' => $weekStart->toDateString(),
                'end_date' => $weekEnd->toDateString(),
                'completed_count' => $completedCount,
                'percentage' => $percentage,
            ];

            $cursor->addWeek();
        }

        return response()->json([
            'habit' => [
                'id' => $habit->id,
                'name' => $habit->name,
                'frequency' => $habit->frequency,
            ],
            'year' => $year,
            'month' => $month,
            'weeks' => $weeks,
        ]);
    }

    public function leaderboard(Request $request)
    {
        $users = User::orderByDesc('total_points')
            ->limit(20)
            ->get(['id', 'name', 'total_points']);

        $currentUserId = $request->user()->id;

        $ranked = $users->values()->map(function ($user, $index) use ($currentUserId) {
            return [
                'rank' => $index + 1,
                'id' => $user->id,
                'name' => $user->name,
                'total_points' => $user->total_points,
                'is_current_user' => $user->id === $currentUserId,
            ];
        });

        return response()->json($ranked);
    }
}