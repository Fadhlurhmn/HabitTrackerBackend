<?php

namespace App\Http\Controllers;

use App\Services\HabitPointService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use App\Models\Habit;

class CheckInController extends Controller
{
    public function __construct(private HabitPointService $pointService)
    {
    }

    public function store(Request $request, Habit $habit){
        $this->authorizeOwner($request, $habit);

        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))
            : Carbon::today();
        
        try{
            $log = $this->pointService->checkIn($habit, $date);
            return response()->json([
                'log' => $log,
                'streak' => $habit->fresh('streak')->streak,
                'total_points' => $habit->user->fresh()->total_points, 
            ], 201);
        } catch (Exception $e){
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function destroy(Request $request, Habit $habit){
        $this->authorizeOwner($request, $habit);

        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))
            : Carbon::today();

        try {
            $this->pointService->undoCheckIn($habit, $date);
            return response()->json([
                'message' => 'Check-in dibatalkan'
            ]);
        }catch (Exception $e){
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function logs(Request $request, Habit $habit){
        $this->authorizeOwner($request, $habit);

        return $habit->logs()->orderBy('log_date', 'desc')->get();
    }

    public function authorizeOwner(Request $request, Habit $habit){
        if ((int) $habit->user_id !== (int) $request->user()->id) {
        abort(403, 'Unauthorized');
    }
    }
}
