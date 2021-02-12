<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::namespace('Api')->group(function(){
    Route::prefix('auth')->group(function(){
        Route::post('login','AuthController@login')->middleware('IsOtpVerified');
        Route::get('category','AuthController@category');
        Route::get('status','AuthController@status');
        Route::post('ngoregister','AuthController@ngoregister')->middleware('NgoRegistration');
        Route::put('verifyOTP/{id}','AuthController@verifyOTP');
        Route::put('resendOtp/{id}','AuthController@resendOtp');
        Route::post('socialLogin','AuthController@socialLogin');
        Route::post('adminlogin','AdminController@login');
        Route::get('users','AdminController@index');
        Route::get('allposts','AdminController@posts');
        Route::get('users/{user}', 'AdminController@show')->name('users.show');
        Route::get('defaultposts','PostController@defaultposts');
        
    });
    
    Route::group([
        'middleware'=>'auth:api'
    ], function(){
        Route::post('logout','AuthController@logout');
        Route::post('updatelocation','AuthController@updatelocation');
        Route::get('/users/{user}/edit', 'AuthController@edit')->name('users.edit');
        Route::put('/users/{user}', 'AuthController@update')->name('users.update');
        Route::post('follow','AuthController@follow');
        Route::get('posts','PostController@index')->name('posts.index');
        Route::post('posts','PostController@store')->name('posts.store');
        Route::post('needs','PostController@needs')->name('posts.needs');
        Route::post('like','PostController@like')->name('posts.like');
        Route::post('accept','PostController@accept')->name('posts.accept');
        Route::post('allNgo','AuthController@allNgo');
        Route::post('searchNgo','AuthController@searchNgo');
    });

});
