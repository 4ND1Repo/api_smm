<?php

namespace App\Http\Controllers\Account;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Master\MenuModel AS Menu;
use App\Model\Account\UserModel AS User;
use App\Model\Account\UserMenuModel AS UserMenu;

// Embed a Helper
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Helpers\Api;

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
                    return response()->json(Api::response(1,'Sukses', ['nik' => $user->nik, 'company' => $user->company_code, 'department' => $user->department_code, 'division' => $user->division_code]),200);
                }
                return response()->json(Api::response(0,'Kata sandi salah'), 200);
            }
            return response()->json(Api::response(0,'data tidak ditemukan'), 200);
        }
        return response()->json(Api::response(0,'Parameter yang di kirim salah'), 200);
    }

    public function menu(Request $r){
        $res = NULL;
        $qMenu = UserMenu::where(['company_code' => $r->company,'department_code' => $r->department,'division_code' => $r->division])->get();
        if($qMenu->count() > 0){
            $idMenu = $res = [];
            foreach($qMenu AS $row){
                $idMenu[] = $row->id_menu;
            }

            // get menu
            $tmpMenu = Menu::whereIn('id_menu',$idMenu)->orderBy('id_parent', 'ASC')->orderBy('id_menu', 'ASC')->get();
            if($tmpMenu->count() > 0){
                $res = self::read_menu($tmpMenu);
            }

            // sort Ascending by menu name
            $tmp = [];
            foreach($res as $row)
                $tmp[$row->menu_name] = $row;
            ksort($tmp);
            $data = [];
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