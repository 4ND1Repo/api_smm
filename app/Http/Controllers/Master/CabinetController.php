<?php

namespace App\Http\Controllers\Master;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Master\CabinetModel AS Cabinet;
use App\Model\Stock\CabinetModel AS MainCabinet;

// Embed a Helper
use DB;
use App\Helpers\Api;
use Illuminate\Http\Request;


class CabinetController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    private function __generate_code(){
        $prefix = "CB";
        $SP = Cabinet::select('cabinet_code')->orderBy('cabinet_code', 'DESC')->get();
        if($SP->count() > 0){
            $SP = $SP->first();
            $tmp = explode($prefix, $SP->cabinet_code);
            $count = ((int)$tmp[1])+1;
        } else
            $count = 1;

        return $prefix.sprintf("%04d",$count);
    }

    public function index(Request $r){
        return response()->json(Api::response(true,"Sukses",Cabinet::all()),200);
    }

    public function get(Request $r, $ty=NULL){
        return response()->json(Api::response(true,"Sukses",Cabinet::where(['menu_page' => $ty])->get()),200);
    }

    public function add(Request $r){
        if($r->has('menu_page') && $r->has('cabinet_name') && $r->has('cabinet_description')){
            $cab = new Cabinet;
            $cab->cabinet_code = $this->__generate_code();
            $cab->cabinet_name = $r->cabinet_name;
            $cab->cabinet_description = $r->cabinet_description;
            $cab->menu_page = $r->menu_page;
            if($cab->save())
                return response()->json(Api::response(true,"Sukses"),200);
            
            return response()->json(Api::response(false,"Gagal"),200);
        }
        return response()->json(Api::response(false,'Periksa kembali inputan anda'),200);
    }

    public function delete(Request $r){
        Cabinet::where(['cabinet_code' => $r->cabinet_code])->delete();
        MainCabinet::where(['cabinet_code' => $r->cabinet_code, 'menu_page' => $r->menu_page])->delete();
        return response()->json(Api::response(true,"Sukses"),200);
    }

}