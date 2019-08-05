<?php

namespace App\Http\Controllers\Account;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Account\UserGroupModel AS UserGroup;
use App\Model\Account\UserMenuModel AS UserMenu;
use App\Model\Master\MenuModel AS Menu;

// Embed a Helper
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Helpers\Api;

class UserGroupController extends Controller
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
        $prefix = "USGP";
        $SP = UserGroup::select('group_code')->orderBy('group_code', 'DESC')->get();
        if($SP->count() > 0){
            $SP = $SP->first();
            $tmp = explode($prefix, $SP->group_code);
            $count = ((int)$tmp[1])+1;
        } else
            $count = 1;

        return $prefix.sprintf("%03d",$count);
    }

    public function index(Request $r, Hash $h){
        return response()->json(Api::response(1,'Sukses', UserGroup::all()), 200);
    }

    public function find($id){
        $data = UserGroup::selectRaw('*')
        ->where('group_code',$id)
        ->first();
        return response()->json(Api::response(true,"Sukses",$data),200);
    }

    public function menu(Request $r){
        $data = UserMenu::selectRaw('*')
        ->where('group_code',$r->group_code)
        ->get();
        return response()->json(Api::response(true,"Sukses",$data),200);
    }

    public function delete(Request $r){
        UserMenu::where('group_code',$r->id)->delete();
        UserGroup::where('group_code',$r->id)->delete();
        return response()->json(Api::response(true,"Sukses"),200);
    }

    public function add(Request $r){
        $grp = new UserGroup;
        $grp->group_code = $this->_generate_prefix();
        $grp->group_name = $r->group_name;
        $grp->page_code = $r->page_code;
        $grp->company_code = $r->company_code;
        $grp->department_code = $r->department_code;
        $grp->division_code = $r->division_code;
        $grp->save();

        return response()->json(Api::response(1,'Sukses'), 200);
    }

    public function edit(Request $r){
        UserGroup::where(['group_code' => $r->group_code])->update([
          'group_name' => $r->group_name,
          'page_code' => $r->page_code,
          'company_code' => $r->company_code,
          'department_code' => $r->department_code,
          'division_code' => $r->division_code
        ]);

        return response()->json(Api::response(1,'Sukses'), 200);
    }

    public function grid(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'group_name',
            'page_name',
            'company_name',
            'department_name',
            'division_name'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'group_code'
            );

        // whole query
        $query = UserGroup::selectRaw('account.user_group.*, master.master_page.page_name, master.master_company.company_name, master.master_department.department_name, master.master_division.division_name')
            ->join('master.master_page', 'master.master_page.page_code', '=', 'account.user_group.page_code')
            ->leftJoin('master.master_company', 'master.master_company.company_code', '=', 'account.user_group.company_code')
            ->leftJoin('master.master_department', 'master.master_department.department_code', '=', 'account.user_group.department_code')
            ->leftJoin('master.master_division', 'master.master_division.division_code', '=', 'account.user_group.division_code');

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('measure_code','stock_brand','stock_daily_use')))
                        $query->where("master.master_stock.".$field,($val=="null"?NULL:$val));
                    else if($field == 'find'){
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
