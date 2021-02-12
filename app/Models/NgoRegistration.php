<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Otp;
use Illuminate\Support\Facades\Mail;

class NgoRegistration
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
        $request->validate([
            'name'=> 'required|string',
            'phone'=> 'required|unique:users',
            'email'=> 'required|string|email|unique:users',
            'location'=> 'required',
            'registration_no'=> 'required',
            'fcra_number'=> 'required',
            'category'=> 'required',
        ]);
        $email =$request->email;
        $phone =$request->phone;
        $user = User::where('email',$email)
                ->orWhere('phone',$phone)
                ->first();
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
            if($email){
                $details =[
                    'title'=>'Verify your e-mail to finish login for Ngo',
                    'otp'=> $otp
                ];
                Mail::to($email)->send(new \App\Mail\OtpMail($details));
            }
            $user_id = $user->id;
            $otp = Otp::updateOrCreate(
                ['user_id' =>  $user_id],
                ['otp' => $otp]
            );
            return response()->json([  
                "user"=> $user,
                "user_role"=> $user->roles,
                "message"=> "Already registered! But mobile no. not verified! Please Verify"
            ], 401);
        }

    }
}
