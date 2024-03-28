<?php

namespace App\Http\Controllers;

use App\Enums\TravelEventType;
use App\Enums\TravelStatus;
use App\Http\Requests\TravelSpotStoreRequest;
use App\Http\Requests\TravelStoreRequest;
use App\Models\Driver;
use App\Models\Travel;
use App\Models\TravelSpot;
use Illuminate\Support\Facades\Auth;

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
            return response()->json(['code' => 'InvalidTravelStatusForThisAction'], 400);
        }

        if ($spot->arrived_at != null) {
            return response()->json(['code' => 'SpotAlreadyPassed'], 400);
        }

        $spot->arrived_at = now();
        $spot->travel_id  = $travel->id;
        $spot->position = 0;
        $spot->save();
        $spots = $travel->spots()->get()->toArray();

        return response()->json(['travel' => ['status' => $travel->status->value, 'spots' => [['arrived_at' => $spot['arrived_at'], 'position' => $spot['position']]]]], 200);
    }

    public function store(TravelSpotStoreRequest $request, $travelId)
    {
        $requestData = $request->validated();
//        dd($requestData);
        $travel = Travel::findOrFail($travelId);
        $user = Auth::user();
        if ($user->id == $travel->driver_id){
            return response()->json(['code' => 'Unauthorized'],403);
        }
//        dd($travel->spots()->get());
        if ($requestData['position'] < 1 || $requestData['position'] > $travel->spots()->get()->count()){
            return response()->json(['errors'=>['position'=>'The position is out of range']],422);
        }
        if ($travel->status!=TravelStatus::RUNNING) {
            return response()->json(['code' => 'InvalidTravelStatusForThisAction'],400);
        }
        if($travel->allSpotsPassed()){
            return response()->json(['code'=>'SpotAlreadyPassed'],400);
        }
//        dd($requestData['latitude']);
        $spot = new TravelSpot();
        $spot->travel_id = $travel->id;
        $spot->latitude  = round($requestData['latitude'],5);
        $spot->longitude = round($requestData['longitude'],5);
        $spot->position = $requestData['position'];
        $spot->save();
        $spots = [];
//        dd($travel->spots()->get());
        foreach ($travel->spots()->get() as $spot) {
            $spots[] = [
                'position' => $spot->position,
                'latitude' => $spot->latitude,
                'longitude' => $spot->longitude,
            ];
        }
//        dd($spots);
//        dd(['travel'=>['spots'=>$spots]]);
        return response()->json(['travel' => ['spots' => $spots]], 200);
    }

	public function destroy()
	{

	}



}
