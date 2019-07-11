<?php

namespace App\Http\Controllers\Master;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Master\StatusModel AS Status;

// Embed a Helper
use App\Helpers\Api;
use Illuminate\Http\Request;


class StatusController extends Controller
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
        return Api::response(true,"Sukses",Status::all());
    }

}