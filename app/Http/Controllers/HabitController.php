<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use Illuminate\Http\Request;

class HabitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return $request->user()->habits()->with('streak')->where('is_active', true)->get();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'frequency' => 'required|in:daily,weekly'
        ]);

        $habit = $request->user()->habits()->create($validated);

        return response()->json($habit, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Habit $habit, Request $request)
    {
        $this->authorizeOwner($request, $habit);

        return $habit->load('streak');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Habit $habit)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Habit $habit)
    {
        $this->authorizeOwner($request, $habit);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'frequency' => 'sometimes|required|in:daily,weekly',
            'is_active' => 'sometimes|boolean'
        ]);

        $habit->update($validated);
        return response()->json($habit);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Habit $habit, Request $request)
    {
        $this->authorizeOwner($request, $habit);
        $habit->delete();
        return response()->json(['messaage' => 'Habit deleted!']);
    }

    public function authorizeOwner(Request $request, Habit $habit){
        if ((int) $habit->user_id !== (int) $request->user()->id) {
        abort(403, 'Unauthorized');
    }
    }
}
