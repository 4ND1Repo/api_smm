<?php

namespace App\Http\Controllers\Account;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Account\UserModel AS User;

// Embed a Helper
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Helpers\Api;

class UserController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    private function _split_string($string){
      if(!empty($string) && !is_null($string)){
        $len = strlen($string);
        $arr = [];
        for($i=0; $i<$len; $i++){
          if(preg_match("/[a-z]/i", strtolower(substr($string, $i, 1))))
            $arr[] = substr($string, $i, 1);
          else {
            break;
          }
        }
        return (count($arr) > 0)? implode("",$arr):null;
      }
      return $string;
    }

    public function index(Request $r, Hash $h){
        return response()->json(Api::response(1,'Selamat datang di Api User'), 200);
    }

    public function find($id){
        $data = User::selectRaw('nik, company_code, department_code, division_code')->where(['nik' => $id])->first();
        return response()->json(Api::response(1,'Sukses', $data), 200);
    }

    public function check(Request $r){
        $prefix = $this->_split_string($r->nik);
        if($prefix){
          $tmp = User::select('nik')->where('nik','like',$prefix."%")->orderBy('nik', 'DESC');
          if($tmp->count() > 0){
            $nik = str_replace($prefix,'',$tmp->first()->nik);
            $tmp = ((int) $nik)+1;
            $last = sprintf($prefix."%0".strlen($nik).'d',$tmp);
          } else
            $last = null;
        } else {
          $tmp = User::select('nik')->whereRaw(DB::raw("nik NOT LIKE '[ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz]%'"))->orderBy('nik', 'DESC');
          if($tmp->count() > 0){
            $nik = $tmp->first()->nik;
            $last = sprintf("%0".strlen($nik)."d",(((int) $nik) +1));
          } else
            $last = null;
        }

        $data = [
          'count' => User::where(['nik' => $r->nik])->count(),
          'last' => $last
        ];
        return response()->json(Api::response(1,'Sukses', $data), 200);
    }

    public function add(Request $r){
      $user = new User;
      $user->nik = $r->username;
      $user->pwd_hash = Hash::make($r->password);
      $user->company_code = $r->company_code;
      $user->department_code = $r->has('department_code')?(!empty($r->department_code)?$r->department_code:NULL):NULL;
      $user->division_code = $r->has('division_code')?(!empty($r->division_code)?$r->division_code:NULL):NULL;
      $user->status_code = "ST01";
      $user->save();
      return response()->json(Api::response(1,'Sukses'), 200);
    }

    public function edit(Request $r){
      $data = [
        'company_code' => $r->company_code,
        'department_code' => $r->has('department_code')?(!empty($r->department_code)?$r->department_code:NULL):NULL,
        'division_code' => $r->has('division_code')?(!empty($r->division_code)?$r->division_code:NULL):NULL
      ];

      if($r->has('password')){
        if(!empty($r->password)){
          $data['pwd_hash'] = Hash::make($r->password);
        }
      }
      User::where(['nik' => $r->username])->update($data);
      return response()->json(Api::response(1,'Sukses'), 200);
    }

    public function delete(Request $r){
        // not check history transaction
        User::where(['nik' => $r->id])->delete();
        // check history transaction



        return response()->json(Api::response(1,'Sukses'), 200);
    }

    public function grid(Request $r){
        // collect data from post
        $input = $r->input();
        $column_search = [
            'nik',
            'master.master_company.company_name',
            'master.master_department.department_name',
            'master.master_division.division_name',
            'master.master_status.status_label'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'nik'
            );

        // whole query
        $query = User::selectRaw('account.[user].*, master.master_company.company_name, master.master_department.department_name, master.master_division.division_name, master.master_status.status_label')
            ->leftJoin('master.master_company','master.master_company.company_code','=','account.user.company_code')
            ->leftJoin('master.master_department','master.master_department.department_code','=','account.user.department_code')
            ->leftJoin('master.master_division','master.master_division.division_code','=','account.user.division_code')
            ->leftJoin('master.master_status','master.master_status.status_code','=','account.user.status_code');

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
