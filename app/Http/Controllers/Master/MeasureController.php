<?php

namespace App\Http\Controllers\Master;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Master\MeasureModel AS Measure;

// Embed a Helper
use DB;
use App\Helpers\Api;
use Illuminate\Http\Request;


class MeasureController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    private function _generate_prefix(){
        $prefix = "MEA";
        $SP = Measure::select('measure_code')->orderBy('measure_code', 'DESC')->get();
        if($SP->count() > 0){
            $SP = $SP->first();
            $tmp = explode($prefix, $SP->measure_code);
            $count = ((int)$tmp[1])+1;
        } else
            $count = 1;

        return $prefix.sprintf("%03d",$count);
    }

    public function index(){
        return Api::response(true,"Sukses",Measure::all());
    }

    public function find($id){
        return Api::response(true,"Sukses",Measure::where('measure_code',$id)->first());
    }

    public function add(Request $r){
        // add stock
        $res = false;

        // validate for same name
        $validate = Measure::where('measure_type',$r->input('measure_type'))->get();
        if($validate->count() == 0){
            $mea = new Measure;
            $mea->measure_code = $this->_generate_prefix();
            $mea->measure_type = $r->input('measure_type');
            $res = $mea->save();
        }

        return response()->json(Api::response($res,$res?"Sukses":"Satuan telah ada"),200);
    }

    public function edit(Request $r){
        // edit Measure
        $old = Measure::where(['measure_code'=>$r->input('measure_code')]);
        if($old->count() > 0){
            $old->first();

            // validate for same name
            $validate = Measure::where('measure_type',$r->input('measure_type'))->get();

            if($validate->count() == 0){
                $old->update([
                    'measure_type' => $r->input('measure_type')
                ]);
            } else
                return response()->json(Api::response(false,"Satuan sudah ada"),200);
        } else
            return response()->json(Api::response(false,"Data satuan tidak ada"),200);

        return response()->json(Api::response(true,"Sukses"),200);
    }

    public function delete(Request $r){
        $stock = Measure::where('measure_code',$r->input('measure_code'))->delete();
        return response()->json(Api::response(true,"Sukses"),200);
    }

    public function get(Request $r){
        // collect data from post
        $input = $r->input();
        $column_search = [
            'measure_code',
            'measure_type'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'measure_code'
            );

        // whole query
        $query = Measure::selectRaw('*');

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if($field == 'find'){
                        if(!empty($val)){
                            $query->where(function($query) use($column_search,$val){
                                foreach($column_search as $row)
                                    $query->orWhere($row,'like',"%".$val."%");
                            });
                        }
                    }
                }
            }
        }

        $query->orderBy($input['sort']['field'],$input['sort']['sort']);

        $data = $query->get();

        return response()->json($data,200);
    }

    public function grid(Request $r){
        // collect data from post
        $input = $r->input();
        $column_search = [
            'measure_code',
            'measure_type'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'measure_code'
            );

        // whole query
        $query = Measure::selectRaw('*');

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if($field == 'find'){
                        if(!empty($val)){
                            $query->where(function($query) use($column_search,$val){
                                foreach($column_search as $row)
                                    $query->orWhere($row,'like',"%".$val."%");
                            });
                        }
                    }
                }
            }
        }
        // $query->where();
        $count_all = $query->count();
        // get total page from count all
        $pages = (!empty($input['pagination']['perpage']) && !is_null($input['pagination']['perpage']))? ceil($count_all/$input['pagination']['perpage']):1;

        $query->orderBy($input['sort']['field'],$input['sort']['sort']);

        // skipping for next page
        $skip = (!empty($input['pagination']['perpage']) && !is_null($input['pagination']['perpage']))?($input['pagination']['page']-1)*$input['pagination']['perpage']:0;
        $query->skip($skip);
        if(!empty($input['pagination']['perpage']) && !is_null($input['pagination']['perpage']))
            $query->take($input['pagination']['perpage']);

        $row = $query->get();
        $data = [
            "meta"=> [
                "page"=> $input['pagination']['page'],
                "pages"=> $pages,
                "perpage"=> (!empty($input['pagination']['perpage']) && !is_null($input['pagination']['perpage']))?$input['pagination']['perpage']:-1,
                "total"=> $count_all,
                "sort"=> $input['sort']['sort'],
                "field"=> $input['sort']['field']
            ],
            "data"=> $row
        ];

        return response()->json($data,200);
    }

}
