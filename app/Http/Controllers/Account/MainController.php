<?php

namespace App\Http\Controllers\Account;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Account\UserModel AS User;

// Embed a Helper
use Illuminate\Http\Request;
use App\Helpers\Api;

class MainController extends Controller
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
        if($r->has('u')){
            $user = User::where(['nik'=>$r->get('u')]);
            if($user->count() >= 1){
                $user = $user->first();
                // call join table
                $user->status;
                $user->company;
                $user->department;
                $user->division;

                return response()->json(Api::response(1,'Sukses', $user),200);
            }
            return response()->json(Api::response(0,'data tidak ditemukan'), 200);
        }
        return response()->json(Api::response(0,'gagal mengambil data'), 200);
    }

}