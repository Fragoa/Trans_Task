<?php

namespace App\Http\Controllers;

use App\Enums\TravelStatus;
use App\Http\Requests\TravelStoreRequest;
use App\Models\Driver;
use App\Models\Travel;
use App\Models\TravelSpot;
use http\Env\Request;
use Illuminate\Support\Facades\Auth;

class TravelController extends Controller
{

    public function view()
    {
    }

    public function store(TravelStoreRequest $request)
    {
        $validated = $request->validated();

        if (Travel::userHasActiveTravel(Auth::user())) {
            return response()->json(['code' => 'ActiveTravel'], 400);
        }

//        dd($validated);
        $travel = new Travel();
        $travel->passenger_id = Auth::id();
        $travel->status = TravelStatus::SEARCHING_FOR_DRIVER;
        $travel->save();

        $spots = $validated['spots'];
//        dd($spots);
        foreach ($spots as $spotData) {
            $travelSpot = new TravelSpot();
            $travelSpot->travel_id = $travel->id;
            $travelSpot->position = $spotData['position'];
            $travelSpot->latitude = $spotData['latitude'];
            $travelSpot->longitude = $spotData['longitude'];
            $travelSpot->save();
        }
        $travel->load('spots');
//        dd($travel->load('spots'));
        return response()->json(['travel' => $travel], 201);

        }

        public
        function cancel()
        {
        }

        public
        function passengerOnBoard()
        {
        }

        public
        function done()
        {
        }

        public
        function take($travel)
        {
            $user = Auth::user();
            $travel = Travel::find($travel);

            $driver = Driver::where('id', $user->id)->first();

            if ($travel->status!=TravelStatus::SEARCHING_FOR_DRIVER) {
                return response()->json(['code' => 'InvalidTravelStatusForThisAction']);
            }

            if (Travel::userHasActiveTravel($driver->user)) {
                return response()->json(['code' => 'ActiveTravel']);
            }
            $travel->driver_id = $driver->id;
            $travel->status = TravelStatus::SEARCHING_FOR_DRIVER;
            $travel->save();

            return response()->json(['travel' => $travel]);

        }
    }
