<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Otp;
use Illuminate\Support\Facades\Mail;

class IsOtpVerified
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
            'username'=> 'required',
        ]);
            $username =$request->username;
            $user = User::where('email',$username)->orWhere('phone',$username)->first();
            if($user){
            $userStatus = $user->status;
            }
            if(!$user){
                $username = $request->username;
                $otp = rand(0,1000000);
                if (preg_match("^[a-zA-Z0-9+_.-]+@[a-zA-Z0-9.-]+$^",$username)){
                    $user = new User([
                        'email'=>$username,
                        'otp_verify'=>0
                    ]);
                    $email_mobile = 'email';
                    $details =[
                        'title'=>'Verify your e-mail to finish login for Ngo',
                        'otp'=>$otp
                    ];
                    Mail::to($username)->send(new \App\Mail\OtpMail($details));
                }else if(preg_match('/^[0-9]{10}+$/', $username)){
                    $user = new User([
                        'phone'=>$username,
                        'otp_verify'=>0
                    ]);
                    $email_mobile = 'mobile'; 
                }else{
                    return response()->json([
                        "status_code"=>2,
                        "message"=>"Email or Phone not valid"
                    ], 201);
                }
                $user->save();
                $usertype = 1;
                $user->roles()->attach($usertype);
                $user_id = $user->id;
                $otp = Otp::updateOrCreate(
                    ['user_id' =>  $user_id],
                    ['otp' => $otp]
                );
                return response()->json([
                    "status_code"=>3,
                    "user"=>$user,
                    "otp"=>$otp->otp,
                    "message"=>"Registerd successfully! Please verify OTP, sent to your $email_mobile"
                ], 201);
            }else if($user && $userStatus != 1 ){
                $otp = rand(0,1000000);
                if($user->email){
                    $details =[
                        'title'=>'Verify your e-mail to finish login for Ngo',
                        'otp'=> $otp
                    ];
                    Mail::to($user->email)->send(new \App\Mail\OtpMail($details));
                }
                $user_id = $user->id;
                $otp = Otp::updateOrCreate(
                    ['user_id' =>  $user_id],
                    ['otp' => $otp]
                );
                $user = User::updateOrCreate(
                    ['id' =>  $user_id],
                    ['otp_verify' => 0]
                );
                return response()->json([  
                    "status_code"=>9,
                    "user"=>$user,
                    "otp"=>$otp->otp,
                    "message"=> "Already registered! But mobile no. not verified! Please Verify"
                ], 401);
            }else if($user && $userStatus == 1 ){
                return $next($request);
            }
    }
}
