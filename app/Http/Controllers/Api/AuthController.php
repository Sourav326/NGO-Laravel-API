<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Cat;
use App\Models\Image;
use App\Models\Otp;
use App\Models\Role;
use App\Models\Location;
use App\Models\Status;
use App\Models\Follow;
use Illuminate\Support\Facades\Auth;
use InterImage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Builder;

class AuthController extends Controller
{
    //User Register/Login or NGO Login
    public function login(Request $request){

            $request->validate([
                'username'=> 'required',
             ]);
             $username = $request->username;
             $user = User::where('email',$username)->orWhere('phone',$username)->first();
             $user->otp_verify=0;
             $user->save();
             if (preg_match("^[a-zA-Z0-9+_.-]+@[a-zA-Z0-9.-]+$^",$username)){
                    $otp =  $this->resendOtp($user->id);
                    $details =[
                        'title'=>'Verify your e-mail to finish login for Ngo',
                        'otp'=> $otp->original['otp']
                    ];
                    Mail::to($username)->send(new \App\Mail\OtpMail($details));
                    return response()->json([
                        "status_code"=>1,
                        "message"=>"Email Send Successfully! Please Verify the OTP"
                    ], 201);
                }else if(preg_match('/^[0-9]{10}+$/', $username)){
                    return $this->resendOtp($user->id);
                }else{
                return response()->json([
                    "status_code"=>2,
                    "message"=>"Email or Phone not valid"
                ], 201);
            }
    }

     //NGO Register Here
     public function ngoregister(Request $request){
        $request->validate([
            'latitude'=>'required',
            'longitude'=>'required',
            'category'=>'required'
        ]);
        $user = new User([
            'name'=>$request->name,
            'phone'=>$request->phone,
            'email'=>$request->email,
            'location'=>$request->location,
            'registration_no'=>$request->registration_no,
            'fcra_number'=>$request->fcra_number,
            'otp_verify'=>0
        ]);
        $user->save();
        $usertype = 2;
        $user->roles()->attach($usertype);
        $user->cats()->attach(request('category'));
        $user_id = $user->id;

        $location = new Location([
            'user_id'=>$user_id,
            'latitude'=>$request->latitude,
            'longitude'=>$request->longitude, 
        ]);
        $location->save();

        if($request->hasFile("profile_img")) {
            $profile_image = $request->profile_img;
            $image_folder = 'profile';
            $image_type = 1; 
            $image_id = $this->uploadimage($profile_image,$image_folder,$image_type);
            $user->images()->attach($image_id,['image_type'=>$image_type]);
         }

         if($request->hasFile("license_image")){
             $profile_image = $request->license_image;
             $image_folder = 'license';
             $image_type = 2;
             $image_id = $this->uploadimage($profile_image,$image_folder,$image_type);
             $user->images()->attach($image_id,['image_type'=>$image_type]);
            }

        if($request->hasFile("fcra_image")){
            $profile_image = $request->fcra_image;
            $image_folder = 'fcra';
            $image_type = 3;
            $image_id = $this->uploadimage($profile_image,$image_folder,$image_type);
            $user->images()->attach($image_id,['image_type'=>$image_type]);
            }

        if($request->hasFile("gallery_image")){
            $profile_images = $request->gallery_image;
            $image_folder = 'gallery';
            $image_type = 4;
            foreach($profile_images as $profile_image){
            $image_id = $this->uploadimage($profile_image,$image_folder,$image_type);
            $user->images()->attach($image_id,['image_type'=>$image_type]);
            } 
        }

        $otp = rand(0,1000000);
        if($request->email){
            $details =[
                'title'=>'Verify your e-mail to finish login for Ngo',
                'otp'=> $otp
            ];
            Mail::to($request->email)->send(new \App\Mail\OtpMail($details));
        }
        
        $otp = Otp::updateOrCreate(
            ['user_id' =>  $user_id],
            ['otp' => $otp]
        );
        return response()->json([
            "status_code"=>3,
            "user"=>User::with(['images','cats'])->find($user_id),
            "otp"=>$otp->otp,
            "message"=>"Registerd successfully! Please verify OTP, sent to your mobile"
        ], 200);
    }

    //Upload Image
    public function uploadimage($profile_image,$image_folder,$image_type){
            $profile_image_name = time().'-'.$profile_image->getClientOriginalName();
            InterImage::make($profile_image)
                        ->insert(storage_path("/app/public/comet_logo-214x117.png"), 'bottom-right', 10, 10)
                        ->save(storage_path("/app/public/".$image_folder."/".$profile_image_name));
            $image = new Image([
                'name'=>$profile_image_name, 
            ]);
            $image->save();
            $image_id = $image->id;
            return $image_id;
        }


    //Verify the OTP
    public function verifyOTP(Request $request,$id){
        $request->validate([
            'otp'=> 'required'
        ]);
        $request_otp = $request->otp;
        $user = User::find($id);
        $fetch_otp = $user->otp['otp'];
        
        if($request_otp == $fetch_otp){
            
            $user->otp_verify=1;
            $user->status=1;
            $user->save();
            $otp_to_delete = Otp::find($user->otp['id']);
            $otp_to_delete->otp = null;
            $otp_to_delete->save();
            Auth::login($user,true);
            $token = $user->createToken('Access Token');
            $user->access_token = $token->accessToken;
            return response()->json([
                "status_code"=>4,
                "user" => $user,
                "message"=>"Login successfully"
            ], 200);
        }else{
            return response()->json([
                "status_code"=>5,
                "request_otp"=>$request_otp,
                "message"=>"Otp Not Matched! Please Input the right Otp"
            ], 401);
        }
    }

     //Resend the OTP
     public function resendOtp($id){
        $otp = rand(10,1000000);
        $user = User::find($id);
        $user_id = User::find($id)->id;
        $otpo = Otp::updateOrCreate(
            ['user_id' =>  $user_id],
            ['otp' => $otp]
        );
        return response()->json([
            "status_code"=>6,
            "user"=>$user,
            "otp"=>$otpo->otp,
            "message"=>"Otp Sent Successfully"
        ], 200);
    }

    //User(NGO or User) Logout
    public function logout(Request $request){
        $user = $request->user();
        $user->otp_verify=0;
        $user->save();
        $request->user()->token()->revoke();
        return response()->json([
            "status_code"=>7,
            "message"=>"You are Log Out Successfully"
        ], 200);
    }

    //User Profile
    public function edit(User $user){
        return response()->json([
            'user'=> $user,
            'role'=> $user->roles[0]->name
        ], 200);
    }

    //User Social Login
    public function socialLogin(Request $request){
        $findUser = User::where('email',request()->email)->first();
        if($findUser){
            $request->validate([
                'email'=> 'required',
             ]);
            if($findUser->status == 1){
                Auth::login($findUser);
                $token = $findUser->createToken('Access Token');
                $findUser->access_token = $token->accessToken;
                return response()->json([
                    "status_code"=>8,
                    "user" => $findUser,
                    "message"=>"Social Login With Old Details"
                ], 200);
            }else{
                return response()->json([
                    "status_code"=>9,
                    "message"=>"Already Registered. Bur Mobile not Verfied! Please Verify"
                ], 200);
            }
            
        }else{
            $request->validate([
                'email'=> 'required',
             ]);
            $user = new User([
                'name'=>$request->name,
                'phone'=>$request->phone,
                'email'=>$request->email,
                'status'=> 1,
                'otp_verify'=>1
            ]);
            $user->save();
            
            if(isset($request->profile_img)) {
                $profile_image = $request->profile_img;
                $image_type = 1;
                $image = new Image([
                    'name'=>$profile_image, 
                ]);
                $image->save();
                $image_id = $image->id;
                $user->images()->sync([$image_id =>['image_type'=>$image_type]]);
             }
            $usertype = 1;
            $user->roles()->attach($usertype);
            Auth::login($user,true);
            $token = $user->createToken('Access Token');
            $user->access_token = $token->accessToken;
            return response()->json([
                "status_code"=>10,
                "user" => $user,
                "message"=>"Social Login With new Details"
            ], 200);
        }
    }


    //User Update
    public function update(User $user)
    {
         $user_type = $user->roles[0]->pivot->role_id;
         if($user_type == 2){
            $data = request()->validate([
                'name'=> 'required|string',
                'email'=> 'required',
                'phone'=> 'required',
                'registration_no'=> 'required',
                'fcra_number'=> 'required',
             ]);
             $cats = request()->validate([
                'category'=> 'required',
             ]); 
         }else if($user_type == 1){
            $data = request()->validate([
                'name'=> 'required|string',
                'email'=> 'required',
                'phone'=> 'required',
                'bio'=> 'required',
                'occupation'=> 'required',
             ]);
         }
         $user->update($data);
         $user->cats()->sync($cats['category']);
         if(request()->hasFile("profile_img")) {
            $profile_image = request()->profile_img;
            $image_folder = 'profile';
            $image_type = 1;
            $all_images = $user->images()->where('image_type',$image_type)->get();
            if(isset($all_images[0])){
                $oldimage = $all_images[0]->name;
                if($oldimage){
                    unlink(storage_path("/app/public/".$image_folder."/".$oldimage));
                    Image::where('name', $oldimage)->delete();
                }
            }
            $image_id = $this->uploadimage($profile_image,$image_folder,$image_type);
            $user->images()->sync([$image_id =>['image_type'=>$image_type]]);
         }

         if(request()->hasFile("license_image")){
            $profile_image = request()->license_image;
            $image_folder = 'license';
            $image_type = 2;
            $all_images = $user->images()->where('image_type',$image_type)->get();
            if(isset($all_images[0])){
                $oldimage = $all_images[0]->name;
                if($oldimage){
                    unlink(storage_path("/app/public/".$image_folder."/".$oldimage));
                    Image::where('name', $oldimage)->delete();
                }
            }
            $image_id = $this->uploadimage($profile_image,$image_folder,$image_type);
            $user->images()->sync([$image_id =>['image_type'=>$image_type]]);
           }

       if(request()->hasFile("fcra_image")){
           $profile_image = request()->fcra_image;
           $image_folder = 'fcra';
           $image_type = 3;
           $all_images = $user->images()->where('image_type',$image_type)->get();
           if(isset($all_images[0])){
            $oldimage = $all_images[0]->name;
            if($oldimage){
                unlink(storage_path("/app/public/".$image_folder."/".$oldimage));
                Image::where('name', $oldimage)->delete();
            }
        }
           $image_id = $this->uploadimage($profile_image,$image_folder,$image_type);
           $user->images()->sync([$image_id =>['image_type'=>$image_type]]);
           }

       if(request()->hasFile("gallery_image")){
           $profile_images = request()->gallery_image;
           $image_folder = 'gallery';
           $image_type = 4;
           $all_images = $user->images()->where('image_type',$image_type)->get();
           foreach($all_images as $all_image){
            if(isset($all_image)){
                $oldimage = $all_image->name;
                if($oldimage){
                    unlink(storage_path("/app/public/".$image_folder."/".$oldimage));
                    Image::where('name', $oldimage)->delete();
                }
              }
            }
            
           foreach($profile_images as $profile_image){
            $image_id = $this->uploadimage($profile_image,$image_folder,$image_type);
            $image_id_array[$image_id] = ['image_type'=>$image_type];  
        } 
        $user->images()->sync($image_id_array);
       }
        return response()->json([
            "status_code"=>11,
            "user"=>User::with(['images','cats'])->find($user->id),
            "message"=>"Details are Updated Successfully"
        ], 200);
    }

    //Get all Categories
    public function category(){
        $categorries  = Cat::all();
        return response()->json([
            "Categorries"=> $categorries
        ], 200);

    }

    //Get all status
    public function status(){
        $statuses  = Status::all();
        return response()->json([
            "Statuses"=> $statuses
        ], 200);

    }


    //Update the location of user as longitude and latitude
    public function updatelocation(Request $request){
        if(Auth::user()){
            $request->validate([
                'latitude'=> 'required',
                'longitude'=> 'required',
             ]);
             $location = Location::updateOrCreate(
                ['user_id' =>  Auth::id()],
                ['latitude' => $request->latitude,'longitude'=>$request->longitude]
            );
        }else{
            dd("user not logged In");
        }
        return response()->json([
            "status_code"=>12,
            "message"=>"User Location Updated Successfully"
        ], 200);
    }


    //Get all verified NGO's here
    public function allNgo(Request $request){
        $roles = Role::find(2);
        if(isset($request->category)){
            $ngo = $roles->users()->where('status',1)->with(['images','cats'])
                ->whereHas('cats', function (Builder $query){
                    $category = request()->category;
                    $query->whereIn('cat_id',$category);
                })->get(); 
        }else{
            $ngo = $roles->users()->where('status',1)->with(['images','cats'])->get();
        }
        return response()->json([
            "ngo"=>$ngo
        ], 200);   
    }


    Public function searchNgo(Request $request){
        $roles = Role::find(2);
        $verify_user = $roles->users()->where('status',1);
        $search = request()->search;
        if(isset($search)){
            $result = $verify_user->Where('name','like','%'.$search.'%')
            ->orWhere('location','like','%'.$search.'%')->with(['images','cats'])
            ->whereHas('cats', function (Builder $query) use($search){
                $query->orWhere('name','like','%'.$search.'%');
            })->get();
            return response()->json([
                "result"=>$result
            ], 200); 
        }else{
            return response()->json([
                "status_code"=>14,
                "message"=>"No Records Found"
            ], 200);
        }
        
    }

    public function follow(Request $request){
        request()->validate([
            'following_id'=> 'required'
        ]);
        $follower_id = Auth::id();
        $following_id = $request->following_id;
        $isFollowed = Follow::where('follower_id',$follower_id)->where('following_id',$following_id)->first();
        if(isset($isFollowed)){
            $isFollowed->delete();
            return response()->json([
                "status_code"=>17,
                "message"=>"Unfollow the NGO successfully"
            ],200);
        }else{
            $follow = new Follow([
                "follower_id"=> $follower_id,
                "following_id"=> $following_id
            ]);
            $follow->save();
            return response()->json([
                "status_code"=>18,
                "follow"=> $follow,
                "message"=>"Follow the NGO Successfully"
            ], 200);
        } 
    }

    
  
}
