<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Otp;

class SignupCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $email =$request->email;
        $user = User::where('email',$email)->first();
        if($user){
        $userStatus = $user->status;
        }
        if(!$user){
            return $next($request);
        }else if($user && $userStatus == 1){
            return response()->json([  
                "user"=> $user,
                "message"=> "Already registered! Please login"
            ], 401);
        }else if($user && $userStatus != 1){
            $otp = rand(0,1000000);
            $user_id = $user->id;
            $otp = Otp::updateOrCreate(
                ['user_id' =>  $user_id],
                ['otp' => $otp]
            );
            return response()->json([  
                "user"=> $user,
                "message"=> "Already registered! But mobile no. not verified! Please Verify"
            ], 401);
        }


    }
}
