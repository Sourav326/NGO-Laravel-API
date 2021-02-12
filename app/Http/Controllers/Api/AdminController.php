<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Admin;
use App\Models\User;
use App\Models\Post;
use App\Models\Role;

class AdminController extends Controller
{
    public function login(Request $request){
        $request->validate([
            'email'=> 'required',
            'password'=> 'required',
         ]);

         $email = $request->email;
         $password = $request->password;
        $admin = Admin::where('email',$email)->first();
        if(!$admin || ($admin->password != $password)){
            return response()->json([
                "message"=>"Email or Password not valid"
            ], 400);
        }else{
        return $admin;
        }

    }

    public function index(){
        $users  = User::all();
        return response()->json([
            "users"=> $users
        ], 200);

    }
    public function posts(){
        $posts  = Post::with('images')->get();
        return response()->json([
            "posts"=> $posts
        ], 200);

    }

    public function show(User $user)
    {
        return response()->json([
            "user"=> $user
        ], 200);
    }

}
