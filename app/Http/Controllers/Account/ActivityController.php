<?php

namespace App\Http\Controllers\Account;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Account\HistoryModel AS History;
use App\Model\Master\ActivityModel AS Activity;

// Embed a Helper
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Helpers\{Api, Log};

class ActivityController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    public function delete(Request $r){
        History::where('history_id',$r->id)->delete();
        return response()->json(Api::response(true,"Sukses"),200);
    }

    public function grid(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'nik',
            'history_date',
            'master.master_activity.activity_type',
            'history_description'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'desc',
                'field' => 'history_date'
            );

        // whole query
        $query = History::selectRaw('account.history.*, master.master_activity.activity_type')
            ->join('master.master_activity', 'master.master_activity.activity_code', '=', 'account.history.activity_code');

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

    public function role(Request $r){
        $res = NULL;
        $data = [];

        // get menu
        $tmpMenu = Menu::all();
        if($tmpMenu->count() > 0){
            $res = self::read_menu($tmpMenu);
        }

        // sort Ascending by menu name
        $tmp = [];
        foreach($res as $row)
            $tmp[$row->menu_name] = $row;
        ksort($tmp);
        foreach($tmp as $row)
            $data[] = $row;

        return response()->json(Api::response(1,'Sukses mengakses menu',$data),200);
    }

    public function genRole(Request $r){
        $res = NULL;
        $data = [];
        $input = $r->input();
        // drop all of configuration
        UserMenu::where(['group_code' => $input['group_code']])->delete();
        // insert new role
        foreach ($input['menu'] as $id => $status) {
          $role = new UserMenu;
          $role->group_code = $input['group_code'];
          $role->id_menu = $id;
          $role->add = isset($input['add'][$id])?1:0;
          $role->edit = isset($input['edit'][$id])?1:0;
          $role->del = isset($input['del'][$id])?1:0;
          $role->save();
        }


        Log::add([
          'type' => 'Edit',
          'nik' => $r->nik,
          'description' => 'Mengubah Role : '.$input['group_code']
        ]);
        return response()->json(Api::response(1,'Saved'),200);
    }

    public static function read_menu($elements, $parentId=0){
        $branch = array();

        foreach ($elements as $element) {
            if ($element->id_parent == $parentId) {
                $children = self::read_menu($elements, $element->id_menu);
                if ($children) {
                    $element['children'] = $children;
                }
                $branch[] = $element;
            }
        }

        return $branch;
    }

}
