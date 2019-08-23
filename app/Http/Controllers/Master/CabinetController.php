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

    public function get(Request $r, $p=NULL){
        return response()->json(Api::response(true,"Sukses",Cabinet::where(['page_code' => $p])->get()),200);
    }

    public function add(Request $r){
        if($r->has('page_code') && $r->has('cabinet_name') && $r->has('cabinet_description')){
            $cab = new Cabinet;
            $cab->cabinet_code = $this->__generate_code();
            $cab->cabinet_name = $r->cabinet_name;
            if($r->has('parent_cabinet_code')){
                if(!empty($r->parent_cabinet_code))
                    $cab->parent_cabinet_code = $r->parent_cabinet_code;
            }
            $cab->cabinet_description = $r->cabinet_description;
            $cab->is_child = (int)1;
            $cab->page_code = $r->page_code;
            if($cab->save()){
                if($r->has('parent_cabinet_code')){
                    if(!empty($r->parent_cabinet_code))
                        $pCab = Cabinet::where(['cabinet_code' => $r->parent_cabinet_code])->update(['is_child' => 0]);
                }
                return response()->json(Api::response(true,"Sukses"),200);
            }

            return response()->json(Api::response(false,"Gagal"),200);
        }
        return response()->json(Api::response(false,'Periksa kembali inputan anda'),200);
    }

    public function delete(Request $r){
        MainCabinet::where(['cabinet_code' => $r->cabinet_code, 'page_code' => $r->page_code])->delete();
        $cb = Cabinet::where(['cabinet_code' => $r->cabinet_code])->first()->toArray();
        $sts = Cabinet::where(['cabinet_code' => $r->cabinet_code])->delete();
        if($sts){
            // if parent not null
            if(!is_null($cb['parent_cabinet_code'])){
                // check child parent is null
                $cbt = Cabinet::where(['parent_cabinet_code' => $cb['parent_cabinet_code'], 'page_code' => $r->page_code])->get();
                if($cbt->count() == 0)
                    Cabinet::where(['cabinet_code' => $cb['parent_cabinet_code'], 'page_code' => $r->page_code])->update(['is_child' => 1]);
            }
        }

        return response()->json(Api::response(true,"Sukses"),200);
    }

    public function tree(Request $r){
        $data = [];

        $cb = Cabinet::where(['parent_cabinet_code' => ($r->parent == "#")?NULL:$r->parent, 'page_code' => $r->p])->orderBy('cabinet_name','ASC')->get();
        if($cb->count() > 0){
            foreach($cb AS $row){
                // check has child
                $cnt = Cabinet::where(['parent_cabinet_code' => $row->cabinet_code, 'is_child' => 0])->count();
                $tmp = [
                    'id' => $row->cabinet_code,
                    'icon' => $row->is_child == 1? 'fa fa-box kt-font-success':'fa fa-folder icon-lg kt-font-warning',
                    'text' => $row->cabinet_name,
                    'children' => ($cnt > 0),
                    'a_attr' => [
                        'href' => $row->cabinet_code
                    ]
                ];

                if(!is_null($row->cabinet_description) && !empty($row->cabinet_description)){
                    $tmp['a_attr']['title'] = $row->cabinet_description;
                }

                if($row->is_child == 1){
                    $tmp['a_attr']['data-toggle'] = 'modal';
                    $tmp['a_attr']['data-target'] = '#listStockModal';
                }
                $data[] = $tmp;
            }
        }
        return response()->json($data,200);
    }

    public function tree_child(Request $r){
        $data = [];

        $cb = Cabinet::where(['parent_cabinet_code' => $r->parent, 'is_child' => 1])->orderBy('cabinet_name','DESC')->get();
        if($cb->count() > 0){
            $chunk = $cb->toArray();
            $data = array_chunk($chunk,$r->cnt);
        }
        return response()->json($data,200);
    }

}
