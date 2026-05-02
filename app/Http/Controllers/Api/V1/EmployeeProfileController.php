<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmployeeProfile;
use Illuminate\Http\Request;

class EmployeeProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $profiles = EmployeeProfile::with('user')
            ->when($request->has('department'), function ($query) use ($request) {
                $query->where('department', $request->department);
            })
            ->when($request->has('employee_id'), function ($query) use ($request) {
                $query->where('employee_id', 'like', "%{$request->employee_id}%");
            })
            ->paginate($request->get('per_page', 15));

        return response()->json($profiles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|unique:employee_profiles,user_id|exists:users,id',
            'employee_id' => 'required|unique:employee_profiles,employee_id|max:50',
            'department' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'salary' => 'nullable|numeric|min:0',
        ]);

        $profile = EmployeeProfile::create($validated);

        return response()->json([
            'message' => 'Employee profile created successfully',
            'data' => $profile->load('user')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(EmployeeProfile $employeeProfile)
    {
        return response()->json([
            'data' => $employeeProfile->load('user')
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, EmployeeProfile $employeeProfile)
    {
        $validated = $request->validate([
            'employee_id' => 'sometimes|unique:employee_profiles,employee_id,' . $employeeProfile->id . '|max:50',
            'department' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'salary' => 'nullable|numeric|min:0',
        ]);

        $employeeProfile->update($validated);

        return response()->json([
            'message' => 'Employee profile updated successfully',
            'data' => $employeeProfile->load('user')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EmployeeProfile $employeeProfile)
    {
        $employeeProfile->delete();

        return response()->json([
            'message' => 'Employee profile deleted successfully'
        ]);
    }
}