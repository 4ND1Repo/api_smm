<?php

namespace App\Http\Controllers\Master;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Master\CityModel AS City;

// Embed a Helper
use App\Model\Master\MeasureModel as Measure;
use DB;
use App\Helpers\{Api, Log};
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
    public function find($id){
        return Api::response(true,"Sukses",City::where('city_code',$id)->first());
    }

    public function add(Request $r){
        // add stock
        $res = false;

        // validate for same name
        $validate = City::where('city_name',$r->input('city_name'))->orWhere('city_code', $r->input('city_code'))->get();
        if($validate->count() == 0){
            $city = new City;
            $city->city_code = $r->input('city_code');
            $city->city_name = $r->input('city_name');
            $city->status_code = 'ST01';
            $res = $city->save();

            Log::add([
                'type' => 'Add',
                'nik' => $r->nik,
                'description' => 'Menambah Kota : '.$r->input('city_name')
            ]);
        }

        return response()->json(Api::response($res,$res?"Sukses":"Kota telah ada"),200);
    }

    public function edit(Request $r){
        // edit Measure
        $old = City::where(['city_code'=>$r->input('city_code')]);
        if($old->count() > 0){
            $old->first();

            // validate for same name
            $validate = City::where('city_name',$r->input('city_name'))->orWhere('city_code', $r->input('city_code'))->get();

            if($validate->count() > 0){
                $old->update([
                    'city_name' => $r->input('city_name')
                ]);

                Log::add([
                    'type' => 'Edit',
                    'nik' => $r->nik,
                    'description' => 'Mengubah Kota : '.$r->input('city_name')
                ]);
                return response()->json(Api::response(true,"Sukses"),200);
            } else{
                $old->update([
                    'city_name' => $r->input('city_name'),
                    'city_code' => $r->input('city_code')
                ]);
                Log::add([
                    'type' => 'Edit',
                    'nik' => $r->nik,
                    'description' => 'Mengubah Kota : '.$r->input('city_name')
                ]);
            }
        } else
            return response()->json(Api::response(false,"Data Kota tidak ada"),200);

        return response()->json(Api::response(true,"Sukses"),200);
    }

    public function delete(Request $r){
        $city = City::where('city_code',$r->input('city_code'))->first();
        City::where('city_code',$r->input('city_code'))->delete();

        Log::add([
            'type' => 'Delete',
            'nik' => $r->nik,
            'description' => 'Menghapus Kota : '.$city->city_name
        ]);
        return response()->json(Api::response(true,"Sukses"),200);
    }

    public function get(Request $r){
        // collect data from post
        $input = $r->input();
        $column_search = [
            'city_code',
            'city_name'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'city_name'
            );

        // whole query
        $query = City::selectRaw('*');

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
            'city_code',
            'city_name'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'city_name'
            );

        // whole query
        $query = City::selectRaw('*');

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