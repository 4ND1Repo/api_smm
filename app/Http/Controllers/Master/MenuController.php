<?php

namespace App\Http\Controllers\Master;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Master\MenuModel AS Menu;

// Embed a Helper
use DB;
use App\Helpers\Api;
use Illuminate\Http\Request;


class MenuController extends Controller
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
        return response()->json(Api::response(true,"Sukses",Menu::all()),200);
    }

    public function parent(Request $r){
        return response()->json(Api::response(true,"Sukses",Menu::selectRaw('master.master_menu.*, menu_parent.parent')->leftJoin(DB::raw("(SELECT id_menu AS id_parent, menu_name AS parent FROM master.master_menu) AS menu_parent"), 'menu_parent.id_parent','=','master.master_menu.id_parent')->where(['menu_page' => $r->menu_page])->get()),200);
    }

    private function _exists($r){
      return (Menu::where(['menu_name' => $r->menu_name, 'menu_url' => $r->menu_url, 'id_parent' => $r->id_parent])->count() > 0);
    }

    private function _getID(){
      $tmp = Menu::selectRaw('id_menu')->orderBy('id_menu', 'DESC');
      return ($tmp->count() > 0)? $tmp->first()->id_menu+1:1;
    }

    public function add(Request $r){
        if(!$this->_exists($r)){
            $menu = new Menu;
            $menu->id_menu = $this->_getID();
            $menu->menu_name = $r->menu_name;
            $menu->menu_url = $r->menu_url;
            $menu->id_parent = $r->id_parent;
            $menu->menu_icon = $r->menu_icon;
            $menu->menu_page = $r->menu_page;
            $menu->save();
            return response()->json(Api::response(true,"Success"),200);
        }
        return response()->json(Api::response(false,"Menu are exists"),200);
    }

    public function delete(Request $r){
        Menu::where(['id_menu' => $r->id])->delete();
        return response()->json(Api::response(true,"Success"),200);
    }

    public function grid(Request $r){
        // collect data from post
        $input = $r->input();
        $column_search = [
            'menu_name',
            'menu_url',
            'menu_parent.parent',
            'page.page'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'id_menu'
            );

        // whole query
        $query = Menu::selectRaw('master.master_menu.*, page.page, menu_parent.parent')
            ->join(DB::raw('(SELECT page_code AS menu_page, page_name AS page FROM master.master_page) AS page'),'page.menu_page','=','master.master_menu.menu_page')
            ->leftJoin(DB::raw('(SELECT id_menu AS id_parent, menu_name AS parent FROM master.master_menu) AS menu_parent'),'menu_parent.id_parent','=','master.master_menu.id_parent');

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
