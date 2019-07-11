<?php

namespace App\Http\Controllers\Master;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Master\PageModel AS Page;

// Embed a Helper
use DB;
use App\Helpers\Api;
use Illuminate\Http\Request;


class PageController extends Controller
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
        return Api::response(true,"Sukses",Page::all());
    }

}