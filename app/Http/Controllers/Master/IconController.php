<?php

namespace App\Http\Controllers\Master;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Master\IconModel AS Icon;

// Embed a Helper
use DB;
use App\Helpers\{Api, Log};
use Illuminate\Http\Request;


class IconController extends Controller
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
        return response()->json(Api::response(true,"Sukses",Icon::all()),200);
    }

    private function _exists($r){
      return (Icon::where(['icon_name' => $r->icon_name])->count() > 0);
    }

    public function add(Request $r){
      if(!$this->_exists($r)){
        $icon = new Icon;
        $icon->icon_name = $r->icon_name;
        $icon->save();

        Log::add([
          'type' => 'Add',
          'nik' => $r->nik,
          'description' => 'Menambah Ikon : '.$r->icon_name
        ]);

        return response()->json(Api::response(true,"Success"),200);
      }
      return response()->json(Api::response(false,"Icon was added"),200);
    }

    public function delete(Request $r){
      $icon = Icon::where('icon_id',$r->id)->first();
      Icon::where('icon_id',$r->id)->delete();
      Log::add([
        'type' => 'Delete',
        'nik' => $r->nik,
        'description' => 'Menghapus Ikon : '.$icon->icon_name
      ]);

      return response()->json(Api::response(true,"Success"),200);
    }

    public function grid(Request $r){
        // collect data from post
        $input = $r->input();
        $column_search = [
            'icon_name'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'icon_id'
            );

        // whole query
        $query = Icon::selectRaw('*');

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
