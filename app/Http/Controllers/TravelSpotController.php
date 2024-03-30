<?php

namespace App\Http\Controllers;

use App\Enums\TravelEventType;
use App\Enums\TravelStatus;
use App\Exceptions\InvalidTravelStatusForThisActionException;
use App\Exceptions\SpotAlreadyPassedException;
use App\Http\Requests\TravelSpotStoreRequest;
c App\Http\Requests\TravelStoreRequest;
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

        if ($user->id != $travel->driver_id) {
            return response()->json(['code' => 'Unauthorized'], 403);
        }

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
//        $this->authorize('create', $user);


        if ($user->id == $travel->driver_id){
            return response()->json(['code' => 'Unauthorized'],403);
        }
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

        if ($travel->status != TravelStatus::RUNNING) {
                return response()->json(['code' => 'InvalidTravelStatusForThisAction'], 400);
            }

        if (Auth::id() == $travel->driver_id) {
        return response()->json('', 403);
        }
        if (!$travel->driverHasArrivedToOrigin()) {
        return response()->json(['code' => 'ProtectedSpot'], 400);
        }
        if ($travel->allSpotsPassed()) {
        return response()->json(['code' => 'SpotAlreadyPassed'], 400);
        }
        if ($travel->spots()->count() == 2 ) {
            return response()->json(['code' => 'ProtectedSpot'], 400);
        }

        $spot->delete();
        $travel->spots()->where('position','>',$spot->position)->decrement('position');

        return new TravelResource($travel);
    }
}
