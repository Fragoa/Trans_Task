<?php

namespace App\Http\Controllers;

use App\Enums\TravelEventType;
use App\Enums\TravelStatus;
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
//        dd($spots);

        return response()->json(['travel' => ['status' => $travel->status->value, 'spots' => [['arrived_at' => $spot['arrived_at'], 'position' => $spot['position']]]]], 200);
    }

	public function store()
	{
	}

	public function destroy()
	{
	}
}
