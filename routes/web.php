<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect('login');
});


Route::get('bianhao/{bianhao}/renshu/{renshu}','InfoController@store')
     ->where('bianhao','[A-Za-z0-9]+')
     ->where('renshu','[0-9]+');

Route::get('login','InfoController@index');
Route::post('login','InfoController@login');
Route::get('logout','InfoController@logout');

Route::get('info/{date?}','InfoController@info');
Route::post('info/export','InfoController@export');