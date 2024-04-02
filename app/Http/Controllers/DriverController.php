<?php

namespace App\Http\Controllers;

use App\Enums\DriverStatus;
use App\Enums\TravelStatus;
use App\Exceptions\AlreadyDriverException;
use App\Http\Requests\DriverUpdateRequest;
use App\Models\Driver;
use App\Models\Travel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class DriverController extends Controller
{
	public function signup(Request $request)
	{
        $user = Auth::user();
        $existingDriver = Driver::where('id', Auth::id())->first();
        
        if ($existingDriver) {
            throw new AlreadyDriverException('AlreadyDriver');
        }

        $driver = new Driver();
        $driver->id = $user->id;
        $driver->car_plate = $request->input('car_plate');
        $driver->car_model = $request->input('car_model');
        $driver->status = DriverStatus::NOT_WORKING->value;
        $driver->save();

        return response()->json(['driver' => $driver], 200);
    }

	public function update(DriverUpdateRequest $request,Driver $driver)
	{
        $requestData = $request->validated();
        $user = $request->user();
        $existingDriver = Driver::byUser($user);

        $existingDriver->update([
            'latitude' => $requestData['latitude'],
            'longitude' => $requestData['longitude'],
            'status' => $requestData['status'],
        ]);

        $availableTravels = Travel::where('status', TravelStatus::SEARCHING_FOR_DRIVER)
            ->whereDoesntHave('driver')
            ->take(2)
            ->with('spots')
            ->get();


        return response()->json([
            'driver' => $existingDriver,
            'travels' => $availableTravels,
        ]);
    }

}
