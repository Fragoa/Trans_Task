<?php

namespace App\Http\Controllers;

use App\Enums\TravelEventType;
use App\Enums\TravelStatus;
use App\Http\Requests\TravelStoreRequest;
use App\Models\Driver;
use App\Models\Travel;
use App\Models\TravelEvent;
use App\Models\TravelSpot;
use Exception;
use http\Env\Request;
use http\Env\Response;
use Illuminate\Support\Facades\Auth;

class TravelController extends Controller
{

    public function view($travelId)
    {
            $travel = Travel::findOrFail($travelId);
            return response()->json(['travel' => $travel], 200);

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
        function cancel($travelId)
        {
            $user = Auth::user();
            $travel = Travel::findOrFail($travelId);

            if ($travel->status == TravelStatus::CANCELLED || $travel->status == TravelStatus::DONE) {
                return response()->json(['code' => 'CannotCancelFinishedTravel'], 400);
            }

            if ($travel->passengerIsInCar()) {
                return response()->json(['code' => 'CannotCancelRunningTravel'], 400);
            }

            if ($travel->driverHasArrivedToOrigin() && $travel->passenger_id == $user->id) {
                return response()->json(['code' => 'CannotCancelRunningTravel'], 400);
            }

            $travel->status = TravelStatus::CANCELLED;
            $travel->save();
            return response()->json(['travel' => ['status' => TravelStatus::CANCELLED->value]], 200);

        }



    public function passengerOnBoard($travelId)
    {
        $user = Auth::user();
        $travel = Travel::findOrFail($travelId);

        if ($user->id == $travel->passenger_id) {
            return response()->json([], 403);
        }

        if (!$travel->driverHasArrivedToOrigin()) {
            return response()->json(['code' => 'CarDoesNotArrivedAtOrigin'], 400);
        }

        if ($travel->status != TravelStatus::RUNNING) {
            return response()->json(['code' => 'InvalidTravelStatusForThisAction'], 400);
        }

        if ($travel->passengerIsInCar() == 1) {
            return response()->json(['code' => 'InvalidTravelStatusForThisAction'], 400);
        }
        TravelEvent::where('travel_id', $travel->id)
            ->update([
                'type' => TravelEventType::PASSENGER_ONBOARD->value,
            ]);
        $events = $travel->events()->get()->toArray();

        $eventTypes = [];
        foreach ($events as $event) {
            $eventType = $event['type'];
            $eventTypes[] = ['type' => $eventType];
        }

        return response()->json(['travel' => ['events' => $eventTypes]], 200);
        }





    public
        function done($travelId)
        {
            $travel = Travel::findOrFail($travelId);
            $user = Auth::user();
            if ($user->id == $travel->passenger_id) {
                return response()->json(['code' => 'Unauthorized'], 403);
            }

            if ($travel->status ==(TravelStatus::DONE)) {
                return response()->json(['code' => 'InvalidTravelStatusForThisAction'], 400);
            }

            if (!$travel->allSpotsPassed()) {
                return response()->json(['code' => 'AllSpotsDidNotPass'], 400);
            }

            $travel->status = TravelStatus::DONE;
            $travel->save();

            $travel->events()->create(['type' => TravelEventType::DONE]);
            $travel->refresh();
            $events = $events = $travel->events()->get()->toArray();

            return response()->json(['travel' => [ 'status' => $travel->status->value, 'events' => $events]], 200);
        }


        public
        function take($travelId)
        {
            $user = Auth::user();
            $travel = Travel::find($travelId);
            $driver = Driver::where('id', $user->id)->first();

            if ($travel->status==TravelStatus::CANCELLED) {
                return response()->json(['code' => 'InvalidTravelStatusForThisAction'],400);
            }
            if (Travel::userHasActiveTravel($driver->user)) {
                return response()->json(['code' => 'ActiveTravel'],400);
            }
            $travel->driver_id = $driver->id;
            $travel->status = TravelStatus::SEARCHING_FOR_DRIVER;
            $travel->save();

            return response()->json(['travel' => $travel]);

        }
    }
