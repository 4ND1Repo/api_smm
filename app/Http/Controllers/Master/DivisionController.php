<?php

namespace App\Http\Controllers\Master;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Master\DivisionModel AS Division;

// Embed a Helper
use DB;
use App\Helpers\Api;
use Illuminate\Http\Request;


class DivisionController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    public function index(Request $r){
        return Api::response(true,"Sukses",Division::where(['company_code'=>$r->company_code, 'department_code' => $r->department_code])->get());
    }

}
