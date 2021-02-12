<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\User;
use App\Models\Image;
use App\Models\Like;
use App\Models\Accept;
use Illuminate\Support\Facades\Auth;
use InterImage;

class PostController extends Controller
{

    //show posts when user in not login
    public function defaultposts(){
        $posts  = Post::where('verify',1)->whereNotIn('post_type',[3])->with('images') ->simplePaginate(10);
        return response()->json([
            "posts"=> $posts
        ], 200);
    }

    //Show posts when user in loged in according to user type
    public function index(Request $request){
        $login_user = Auth::user()->roles[0]->id;
        if($login_user == 1){
            $posts  = Post::where('verify',1)
            //  ->where('post_type' => 1,'post_type' => 2])
            //  ->whereIn('post_type', [1,2])
                ->whereNotIn('post_type',[3])
                ->with('images')
                ->simplePaginate(10);

            }else if($login_user == 2){
                $posts  = Post::where('verify',1) ->whereNotIn('post_type',[2])->with('images')->simplePaginate(10);
            }
        
        return response()->json([
            "posts"=> $posts
        ], 200);        

    }

    //Show posts when user in loged in according to user type
    public function needs(Request $request){
        $login_user = Auth::user()->roles[0]->id;
        if($login_user == 1){
                if($request->filter_post =="mypost"){
                    $posts  = Post::where('verify',1)->where('post_type',2)->where('user_id',Auth::id())->with('images')->simplePaginate(10);
                }else{
                    $posts  = Post::where('verify',1)->where('post_type',2)->with('images')->simplePaginate(10);
                }
            }else if($login_user == 2){
                if($request->filter_post =="mypost"){
                    $posts  = Post::where('verify',1)->where('post_type',3)->where('user_id',Auth::id())->with('images')->simplePaginate(10);
                }else
                $posts  = Post::where('verify',1) ->where('post_type',3)->with('images')->simplePaginate(10);
            }
        return response()->json([
            "posts"=> $posts
        ], 200);        

    }

    //Create Post
    public function store(Request $request){
        $request->validate([
            'title'=> 'required|string',
            'post_type'=>'required'
        ]);
        $post = new Post([
            'title'=>$request->title,
            'description'=>$request->description,
            'post_type'=>$request->post_type,
            'user_id'=>Auth::id(),
            'verify'=>0
        ]);
        
        $post->save();
        if($request->hasFile("image")){
            $post_images = $request->image;
            foreach($post_images as $post_image){
                $post_image_name = time().'-'.$post_image->getClientOriginalName();
                InterImage::make($post_image)
                            ->insert(storage_path("/app/public/comet_logo-214x117.png"), 'bottom-right', 10, 10)
                            ->save(storage_path("/app/public/posts/".$post_image_name));
                $image = new Image([
                    'name'=>$post_image_name, 
                ]);
                $image->save();
                $image_id = $image->id;
            $post->images()->attach($image_id);
            } 
        }
        
        $post->images;
        return response()->json([
            "post" => $post,
            "message"=>"Post Created Successfully"
        ], 200);
    }

    //View Single Post
    public function show($id)
    {
        return response()->json([
            'post' => Post::findOrFail($id)
        ], 200);
    }

    //Update Post
    public function update(Post $post){
        $data = request()->validate([
            'title'=> 'required|string',
            'post_type'=>'required'
        ]);
        $post->update($data);
        if(request()->hasFile("image")){
            $images = request()->image;
            $all_images = $post->images()->get();
            foreach($all_images as $all_image){
             if(isset($all_image)){
                 $oldimage = $all_image->name;
                 if($oldimage){
                     unlink(storage_path("/app/public/posts/".$oldimage));
                     Image::where('name', $oldimage)->delete();
                 }
               }
             }
             
            foreach($images as $image){
             $post_image_name = time().'-'.$image->getClientOriginalName();
             InterImage::make($image)
                         ->insert(storage_path("/app/public/comet_logo-214x117.png"), 'bottom-right', 10, 10)
                         ->save(storage_path("/app/public/posts/".$post_image_name));
             $image = new Image([
                 'name'=>$post_image_name, 
             ]);
             $image->save();
             $image_id = $image->id;
             $image_id_array[] = $image_id;  
         } 
         $post->images()->sync($image_id_array);
        }
        return response()->json([
            "post"=> $post,
            "message"=>"Post Updated Successfully"
        ], 200);

    }

    public function like(){
        request()->validate([
            'post_id'=> 'required'
        ]);
        $user_id = Auth::id();
        $post_id = request()->post_id;
        $like_status = Like::where('user_id',$user_id)->where('post_id',$post_id)->count();
        if($like_status > 0){
            Like::where('user_id',$user_id)->where('post_id',$post_id)->delete();
            $message = "DisLike the post Successfully";
            
        }else{
            $like = new Like([
                'status'=>1,
                'user_id'=>$user_id,
                'post_id'=>$post_id
            ]);
            $like->save();
            $message = "Like the post Successfully";
        }
        $like_count = Like::where('post_id',$post_id)->count(); 
        return response()->json([
            "like_count"=> $like_count,
            "message"=> $message
        ],200);
    }

    public function accept(){
        request()->validate([
            'post_id'=> 'required'
        ]);
        $post_id = request()->post_id;
        $accepter_id = Auth::id();
        $creater_id = Post::where('id',$post_id)->first()->user_id;
        $isAccepted = Accept::where('post_id',$post_id)->where('accepter_id',$accepter_id)->where('creater_id',$creater_id)->first();
        
        if(isset($isAccepted)){
            return response()->json([
                "status_code"=>16,
                "accept"=>$isAccepted,
                "message"=>"Already Accepted the Post"
            ],401);
        }else{
            $secret_code = rand(9,999999);
            $accept = new Accept([
                "post_id"=> $post_id,
                "accepter_id"=> $accepter_id,
                "creater_id"=> $creater_id,
                "secret_code"=> $secret_code,
            ]);
            $accept->save();
            return response()->json([
                "status_code"=>15,
                "accept"=>$accept,
                "message"=>"Accepted Successfully the Post"
            ],200);
        }
       
    }
}
