<?php

namespace App\Http\Controllers;

use App\Enums\TravelStatus;
use App\Http\Requests\TravelStoreRequest;
use App\Models\Travel;
use App\Models\TravelSpot;
use Illuminate\Support\Facades\Auth;

class TravelController extends Controller
{

    public function view()
    {
    }

    public function store(TravelStoreRequest $request)
    {
        $validated = $request->validated();
//        dd($validated);
        $travel = new Travel();
        $travel->passenger_id = Auth::id();
        $travel->status = TravelStatus::SEARCHING_FOR_DRIVER;
        $travel->save();

        $spots = $validated['spots'];
//        dd($spots);
        foreach ($spots as $spotData) {
//            $travel->spots()->create([
//                'position' => $spot['position'],
//                'latitude' => $spot['latitude'],
//                'longitude' => $spot['longitude'],
//            ]);
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
        function take()
        {
        }
    }
