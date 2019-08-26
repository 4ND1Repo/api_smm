<?php

namespace App\Http\Controllers\Account;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Master\MenuModel AS Menu;
use App\Model\Account\UserModel AS User;
use App\Model\Account\UserMenuModel AS UserMenu;
use App\Model\Account\UserGroupModel AS UserGroup;
use App\Model\Account\UserBiodataModel AS UserBiodata;

// Embed a Helper
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Helpers\{Api, Log};

class AuthController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    public function index(Request $r, Hash $h){
        return response()->json(Api::response(1,'Selamat datang di Api Account'), 200);
    }

    public function login(Request $r, Hash $h){
        if($r->has('u') && $r->has('p')){
            $user = User::where(['nik'=>$r->get('u')]);
            if($user->count() >= 1){
                $user = $user->first();
                if(hash::check($r->post('p'),$user->pwd_hash)){
                    // get company_code, department_code, division_code, page_code
                    $q = UserGroup::where(['group_code' => $user->group_code])->first();
                    $bio = UserBiodata::where(['nik' => $user->nik]);
                    $stat = ($bio->count() > 0)? $bio->first(): false;
                    Log::add([
                      'type' => 'Login',
                      'nik' => $user->nik,
                      'description' => 'Masuk Sistem'
                    ]);
                    return response()->json(Api::response(1,'Sukses', ['nik' => $user->nik, 'name' => ($stat?($stat->first_name." ".$stat->last_name):$user->nik), 'photo' => $user->photo, 'company' => $q->company_code, 'department' => $q->department_code, 'division' => $q->division_code, 'page' => $q->page_code, 'group' => $q->group_code]),200);
                }
                return response()->json(Api::response(0,'Kata sandi salah'), 200);
            }
            return response()->json(Api::response(0,'data tidak ditemukan'), 200);
        }
        return response()->json(Api::response(0,'Parameter yang di kirim salah'), 200);
    }

    public function menu(Request $r){
        $res = NULL;
        $data = [];

        $qMenu = UserMenu::where(['group_code' => $r->group])->get();
        if($qMenu->count() > 0){
            $idMenu = $res = [];
            foreach($qMenu AS $row){
                $idMenu[] = $row->id_menu;
            }

            // get menu
            $tmpMenu = Menu::selectRaw('master.master_menu.*, account.user_menu."add", account.user_menu.edit, account.user_menu.del')->join('account.user_menu', function($on) use($r){
              $on->on('account.user_menu.id_menu', '=', 'master.master_menu.id_menu')->whereRaw("account.user_menu.group_code = '".$r->group."'");
            })->whereIn('master.master_menu.id_menu',$idMenu)->orderBy('id_parent', 'ASC')->orderBy('master.master_menu.id_menu', 'ASC')->get();
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
        }
        return response()->json(Api::response(1,'Sukses mengakses menu',$data),200);
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
