<?php

namespace App\Http\Controllers\Master;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Master\CategoryModel AS Category;

// Embed a Helper
use DB;
use App\Helpers\Api;
use Illuminate\Http\Request;


class CategoryController extends Controller
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
        return Api::response(true,"Sukses",Category::all());
    }

    public function find($id){
        return Api::response(true,"Sukses",Category::where('category_code',$id)->first());
    }

    public function add(Request $r){
        $res = false;

        // validate for same name
        $validate = Category::where('category_name',$r->input('category_name'))->get();
        if($validate->count() == 0){
            $ct = new Category;
            $ct->category_code = $r->category_code;
            $ct->category_name = $r->category_name;
            $res = $ct->save();
        }

        return response()->json(Api::response($res,$res?"Sukses":"Satuan telah ada"),200);
    }

    public function edit(Request $r){
        // edit Category
        $old = Category::where(['category_code'=>$r->category_code]);
        if($old->count() > 0){
            $old->first();

            // validate for same name
            $validate = Category::where('category_name',$r->category_name)->get();

            if($validate->count() == 0){
                $old->update([
                    'category_name' => $r->input('category_name')
                ]);
            } else 
                return response()->json(Api::response(false,"Kategori sudah ada"),200);
        } else 
            return response()->json(Api::response(false,"Data kategori tidak ada"),200);

        return response()->json(Api::response(true,"Sukses"),200);
    }

    public function delete(Request $r){
        $query = Category::where('category_code',$r->category_code)->delete();
        return response()->json(Api::response(true,"Sukses"),200);
    }

    public function grid(Request $r){
        // collect data from post
        $input = $r->input();
        $column_search = [
            'category_code',
            'category_name'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'category_code'
            );

        // whole query
        $query = Category::selectRaw('*');

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
        $query->get();

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