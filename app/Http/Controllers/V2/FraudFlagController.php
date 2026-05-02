<?php

namespace App\Http\Controllers;

use App\Models\FraudFlag;
use App\Http\Requests\StoreFraudFlagRequest;
use App\Http\Requests\UpdateFraudFlagRequest;

class FraudFlagController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
    public function store(StoreFraudFlagRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(FraudFlag $fraudFlag)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FraudFlag $fraudFlag)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFraudFlagRequest $request, FraudFlag $fraudFlag)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FraudFlag $fraudFlag)
    {
        //
    }
}
