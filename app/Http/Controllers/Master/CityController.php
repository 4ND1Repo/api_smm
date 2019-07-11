<?php

namespace App\Http\Controllers\Master;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Master\CityModel AS City;

// Embed a Helper
use DB;
use App\Helpers\Api;
use Illuminate\Http\Request;


class CityController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    public function index(){
        return Api::response(true,"Sukses",City::all());
    }

}