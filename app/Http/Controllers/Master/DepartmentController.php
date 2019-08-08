<?php

namespace App\Http\Controllers\Master;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Master\DepartmentModel AS Department;

// Embed a Helper
use DB;
use App\Helpers\Api;
use Illuminate\Http\Request;


class DepartmentController extends Controller
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
        return response()->json(Api::response(true,"Sukses",Department::where(['company_code'=>$r->company_code])->get()),200);
    }

}
