<?php

namespace App\Http\Controllers;

use App\Enums\TravelEventType;
use App\Enums\TravelStatus;
use App\Exceptions\InvalidTravelStatusForThisActionException;
use App\Exceptions\ProtectedSpotException;
use App\Exceptions\SpotAlreadyPassedException;
use App\Http\Requests\TravelSpotStoreRequest;
use App\Http\Requests\TravelStoreRequest;
use App\Models\Driver;
use App\Models\Travel;
use App\Models\TravelSpot;
use http\Client\Request;
use App\Http\Resources\TravelResource;
use Illuminate\Support\Facades\Auth;
use App\Policies\TravelSpotPolicy;
use mysql_xdevapi\SqlStatementResult;

class TravelSpotController extends Controller
{
    public function arrived($travelId,$spotId)
    {
        $user = Auth::user();
        $travel = Travel::findOrFail($travelId);
        $spot = TravelSpot::findOrFail($spotId);
        $this->authorize('markAsArrived', $spot);


        if ($travel->status != TravelStatus::RUNNING) {
            throw new InvalidTravelStatusForThisActionException('InvalidTravelStatusForThisAction');
        }

        if ($spot->arrived_at != null) {
            throw new SpotAlreadyPassedException('SpotAlreadyPassed');
        }

        $spot->arrived_at = now();
        $spot->travel_id  = $travel->id;
        $spot->position = 0;
        $spot->save();
        $spots = $travel->spots()->get()->toArray();

        return new TravelResource($travel);
    }

    public function store(TravelSpotStoreRequest $request, $travelId)
    {
        $requestData = $request->validated();
        $travel = Travel::findOrFail($travelId);
        $user = Auth::user();
        $this->authorize('create', [TravelSpot::class, $travel]);


        if ($requestData['position'] < 1 || $requestData['position'] > $travel->spots()->get()->count()){
            return response()->json(['errors'=>['position'=>'The position is out of range']],422);
        }

        if ($travel->status!=TravelStatus::RUNNING) {
            throw new InvalidTravelStatusForThisActionException('InvalidTravelStatusForThisAction');
        }
        if ($travel->allSpotsPassed()) {
            throw new SpotAlreadyPassedException('SpotAlreadyPassed');
        }

        TravelSpot::where('travel_id', $travelId)
            ->where('position', 1)
            ->update([
                'position' => 2,
            ]);

        $spot = new TravelSpot();
        $spot->travel_id = $travel->id;
        $spot->latitude  = $requestData['latitude'];
        $spot->longitude = $requestData['longitude'];
        $spot->position = $requestData['position'];
        $spot->save();


        return new TravelResource($travel);
    }


    public function destroy($travelId,$spotId){
        $travel = Travel::findOrFail($travelId);
        $spot = TravelSpot::findOrFail($spotId);
        $this->authorize('destroy', $spot);


        if ($travel->status != TravelStatus::RUNNING) {
            throw new InvalidTravelStatusForThisActionException('InvalidTravelStatusForThisAction');
        }


        if (!$travel->driverHasArrivedToOrigin()) {
             return response()->json(['code' => 'ProtectedSpot'], 400);
        }
        if ($travel->allSpotsPassed()) {
            throw new SpotAlreadyPassedException('SpotAlreadyPassed');
        }
        if ($travel->spots()->count() == 2 ) {
            throw new ProtectedSpotException('ProtectedSpot');
        }

        $spot->delete();
        $travel->spots()->where('position','>',$spot->position)->decrement('position');

        return new TravelResource($travel);
    }
}
