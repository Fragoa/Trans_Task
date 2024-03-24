<?php
namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller {
	public function register(RegisterRequest $request ) {

        $validated = $request->validated();

        $user = new User();
        $user->cellphone = $validated['cellphone'];
        $user->name = $validated['name'];
        $user->lastname = $validated['lastname'];
        $user->password = Hash::make($validated['password']);
        $user->save();


        return response()->json(['user' => $user]);

	}

	public function user() {
        $user = Auth::user();

        if ($user) {
            return response()->json(['user' => $user]);
        }
	}
}
