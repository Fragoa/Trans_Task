<?php

namespace App\Http\Controllers;

use App\Enums\TravelEventType;
use App\Enums\TravelStatus;
use App\Exceptions\ActiveTravelException;
use App\Exceptions\AllSpotsDidNotPassException;
use App\Exceptions\CannotCancelFinishedTravelException;
use App\Exceptions\CannotCancelRunningTravelException;
use App\Exceptions\CarDoesNotArrivedAtOriginException;
use App\Exceptions\InvalidTravelStatusForThisActionException;
use App\Http\Requests\TravelStoreRequest;
use App\Models\Driver;
use App\Models\Travel;
use App\Models\TravelEvent;
use App\Models\TravelSpot;
use Exception;
use http\Env\Request;
use http\Env\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class TravelController extends Controller
{


    public function view($travelId)
    {
        $travel = Travel::findOrFail($travelId);
        $this->authorize('view', $travel);

        return response()->json(['travel' => $travel], 200);
    }

    public function store(TravelStoreRequest $request)
    {
        $validated = $request->validated();
        $this->authorize('create', Travel::class);


        if (Travel::userHasActiveTravel(Auth::user())) {
            throw new ActiveTravelException('ActiveTravel');
        }

        $travel = new Travel();
        $travel->passenger_id = Auth::id();
        $travel->status = TravelStatus::SEARCHING_FOR_DRIVER;
        $travel->save();

        $spots = $validated['spots'];
        foreach ($spots as $spotData) {
            $travelSpot = new TravelSpot();
            $travelSpot->travel_id = $travel->id;
            $travelSpot->position = $spotData['position'];
            $travelSpot->latitude = $spotData['latitude'];
            $travelSpot->longitude = $spotData['longitude'];
            $travelSpot->save();
        }
        $travel->load('spots');

        return response()->json(['travel' => $travel], 201);

        }
    public function cancel($travelId)
        {
            $user = Auth::user();
            $travel = Travel::findOrFail($travelId);
            $this->authorize('cancel', $travel);



            if ($travel->status == TravelStatus::CANCELLED || $travel->status == TravelStatus::DONE) {
                throw new CannotCancelFinishedTravelException('CannotCancelFinishedTravel');

            }

            if ($travel->passengerIsInCar()) {
                throw new CannotCancelRunningTravelException('CannotCancelRunningTravel');
            }

            if ($travel->driverHasArrivedToOrigin() && $travel->passenger_id == $user->id) {
                throw new CannotCancelRunningTravelException('CannotCancelRunningTravel');
            }

            $travel->status = TravelStatus::CANCELLED;
            $travel->save();
            return response()->json(['travel' => ['status' => TravelStatus::CANCELLED->value]], 200);

        }


    public function passengerOnBoard($travelId)
    {
        $user = Auth::user();
        $travel = Travel::findOrFail($travelId);
        $this->authorize('markAsPassengerOnBoard', $travel);


        if (!$travel->driverHasArrivedToOrigin()) {
            throw new CarDoesNotArrivedAtOriginException('CarDoesNotArrivedAtOrigin');
        }

        if ($travel->status != TravelStatus::RUNNING) {
            throw new InvalidTravelStatusForThisActionException('InvalidTravelStatusForThisAction');
        }

        if ($travel->passengerIsInCar() == 1) {
            throw new InvalidTravelStatusForThisActionException('InvalidTravelStatusForThisAction');
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
            $this->authorize('markAsDone', $travel);


            if ($travel->status ==(TravelStatus::DONE)) {
                throw new InvalidTravelStatusForThisActionException('InvalidTravelStatusForThisAction');
            }

            if (!$travel->allSpotsPassed()) {
                throw new AllSpotsDidNotPassException('AllSpotsDidNotPass');
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
            $driver = Driver::byUser($user);
            $this->authorize('take', $travel);


            if ($travel->status==TravelStatus::CANCELLED) {
                throw new InvalidTravelStatusForThisActionException('InvalidTravelStatusForThisAction');
            }

            if (Travel::userHasActiveTravel($driver->user)) {
                throw new ActiveTravelException('ActiveTravel');
            }

            $travel->driver_id = $driver->id;
            $travel->status = TravelStatus::SEARCHING_FOR_DRIVER;
            $travel->save();

            return response()->json(['travel' => $travel]);

        }
    }
