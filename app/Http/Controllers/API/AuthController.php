<?php

namespace App\Http\Controllers\API;

use Illuminate\Foundation\Auth\AuthenticatesUsers;

use Illuminate\Support\Str;

use Illuminate\Http\Request;
use App\Http\Controllers\API\ResponseController as ResponseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
// use App\Seller;
// use App\Admin;
// use App\Courier;
use Validator;

// use Illuminate\Support\Facades\Crypt;

use App\Notifications\SignupActivate;
// use App\Notifications\sellerSignupActivate;
// use App\Notifications\adminSignupActivate;
// use App\Notifications\courierSignupActivate;

use Stevebauman\Location\Facades\Location;

class AuthController extends ResponseController
{

    use AuthenticatesUsers;

    //create user
    public function signup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['string', 'max:20', 'min:2'],
            // 'last_name' => ['string', 'max:20', 'min:2'],
            'email' => ['required', 'string', 'email', 'max:40', 'unique:users'],
            'password' => ['required'],
            'confirm_password' => ['required', 'same:password'],
        ]);

        if($validator->fails()){
            return $this->sendError($validator->errors());
        }

        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = '';

        $location = \Location::get($ipaddress);

        $input = $request->all();
        $input['password'] = bcrypt($input['password']);
        // $input['activation_token'] = str_random(60);
        $input['activation_token'] = sha1(time());
        $pinName = substr($input['name'], -4);
        $input['pin'] = $pinName.Str::random(8);
        $input['ip'] = $ipaddress;
        if($location){
            $input['latitude'] = $location->latitude;
            $input['longitude'] = $location->longitude;
            $input['countryName'] = $location->countryName;
            $input['countryCode'] = $location->countryCode;
            $input['cityName'] = $location->cityName;
        }

        $user = User::create($input);
        if($user){
            $success['token'] =  $user->createToken('token')->accessToken;

            // $user->notify(new SignupActivate($user));

            $success['message'] = "Registration successfull...";
            return $this->sendResponse($success);
        }
        else{
            $error = "Sorry! Registration is not successfull...";
            return $this->sendError($error, 401);
        }

    }

    //login
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required'
        ]);

        if($validator->fails()){
            return $this->sendError($validator->errors());
        }

        $credentials = request(['email', 'password']);

        // $credentials['email'] = Crypt::encryptString($credentials['email']);

        // $credentials['active'] = true;
        // $credentials['deleted_at'] = null;

        if(!Auth::attempt($credentials)){
            $error = "Unauthorized";
            return $this->sendError($error, 401);
        }
        $user = $request->user();
        if($user){
            // $user->active = true;
            $success['token'] =  $user->createToken('token')->accessToken;
            return $this->sendResponse($success);
        }

    }

    //logout
    public function logout(Request $request)
    {
        $user = $request->user();
        // $user->active = false;
        $isUser = $request->user()->token()->revoke();
        if($isUser){
            $success['message'] = "Successfully logged out.";
            return $this->sendResponse($success);
        }
        else{
            // $user->active = true;
            $error = "Something went wrong.";
            return $this->sendResponse($error);
        }


    }

    //getuser
    public function getUser(Request $request)
    {
        //$id = $request->user()->id;
        $user = $request->user();
        if($user){
            return $this->sendResponse($user);
        }
        else{
            $error = "user not found";
            return $this->sendResponse($error);
        }
    }

    // make yourself visible
    public function makeView(Request $request)
    {
        //$id = $request->user()->id;
        $user = $request->user();
        if($user){
            $user->view = true;

            // if($user->view == 0){
            //     $user->view = true;
            // }else{
            //     $user->view = 0;
            // }

            return $this->sendResponse($user);
        }
        else{
            $error = "user not found";
            return $this->sendResponse($error);
        }
    }

    // make yourself invisible
    public function noView(Request $request)
    {
        //$id = $request->user()->id;
        $user = $request->user();
        if($user){
            $user->view = false;
            return $this->sendResponse($user);
        }
        else{
            $error = "user not found";
            return $this->sendResponse($error);
        }
    }

    // change pin
    public function pin(Request $request)
    {
        //$id = $request->user()->id;
        $user = $request->user();
        $pinName = substr($user->name, -4);
        if($user){
            $user->pin = $pinName.Str::random(8);
            return $this->sendResponse($user);
        }
        else{
            $error = "user not found";
            return $this->sendResponse($error);
        }
    }

    //getfriend
    public function getFriend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string'
        ]);

        if($validator->fails()){
            return $this->sendError($validator->errors());
        }

        $q = $request->input('q');
        $friend = User::where([
            ['pin', 'LIKE', $q],
            ['view', '=', 'true']])
        // ->orWhere ( 'email', 'LIKE', '%' . $q . '%' )
        // ->where ( 'view', '=', true )
        ->get();

        $found_data = [
            'q' => $q,
            'friend' => $friend,
        ];

        // if($q != null){
        //     if (count($goods)>0){
        //         return response()->json($find_data);
        //     }
        // }
        return response()->json($found_data);
    }

    //Activate user account
    public function signupActivate($token)
    {
        $user = User::where('activation_token', $token)->first();
        if (!$user) {
            return response()->json([
                'message' => 'This activation token is invalid.'
            ], 404);
        }
        $user->active = true;
        $user->activation_token = '';
        $user->save();
        return $user;
    }

}
