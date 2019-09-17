<?php

namespace App\Http\Controllers\Master;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Master\SupplierModel AS Supplier;

// Embed a Helper
use DB;
use App\Helpers\{Api, Log};
use Illuminate\Http\Request;


class SupplierController extends Controller
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
        $prefix = "SP";
        $SP = Supplier::select('supplier_code')->where('supplier_code', 'like', $prefix.'%')->orderBy('supplier_code', 'DESC')->get();
        if($SP->count() > 0){
            $SP = $SP->first();
            $tmp = explode($prefix, $SP->supplier_code);
            $count = ((int)$tmp[1])+1;
        } else
            $count = 1;

        return $prefix.sprintf("%04d",$count);
    }

    public function index(){
        return Api::response(true,"Sukses",Supplier::all());
    }

    public function find($id){
        return Api::response(true,"Sukses",Supplier::where('supplier_code',$id)->first());
    }

    public function add(Request $r){
        // add supplier
        $sup = new Supplier;
        $sup->supplier_code = $this->_generate_prefix();
        $sup->supplier_name = $r->input('supplier_name');
        $sup->supplier_phone = $r->input('supplier_phone');
        $sup->supplier_address = $r->input('supplier_address');
        $sup->city_code = $r->input('city_code');
        $sup->supplier_category = $r->input('supplier_category');
        $sup->supplier_npwp = $r->input('supplier_npwp');
        $sup->status_code = "ST01";
        $res = $sup->save();

        Log::add([
          'type' => 'Add',
          'nik' => $r->nik,
          'description' => 'Menambah Supplier : '.$r->input('supplier_name')
        ]);

        return response()->json(Api::response($res,$res?"Sukses":"Gagal",$sup),200);
    }

    public function edit(Request $r){
        // edit supplier
        Supplier::where('supplier_code',$r->input('supplier_code'))
            ->update([
                'supplier_name' => $r->input('supplier_name'),
                'supplier_phone' => $r->input('supplier_phone'),
                'supplier_address' => $r->input('supplier_address'),
                'supplier_category' => $r->input('supplier_category'),
                'supplier_npwp' => $r->input('supplier_npwp'),
                'city_code' => $r->input('city_code')
            ]);

        Log::add([
          'type' => 'Edit',
          'nik' => $r->nik,
          'description' => 'Mengubah Supplier : '.$r->input('supplier_code')
        ]);

        return response()->json(Api::response(true,"Sukses"),200);
    }

    public function delete(Request $r){
        $sup = Supplier::where('supplier_code',$r->input('supplier_code'))->delete();
        Log::add([
          'type' => 'Delete',
          'nik' => $r->nik,
          'description' => 'Menghapus Supplier : '.$r->input('supplier_code')
        ]);
        return response()->json(Api::response(true,"Sukses"),200);
    }

    public function get(Request $r){
        // collect data from post
        $input = $r->input();
        $column_search = [
            'supplier_code',
            'supplier_name',
            'supplier_phone',
            'supplier_address',
            'supplier_npwp',
            'city_name',
            'master.master_city.city_code',
            'supplier_category'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'supplier_code'
            );

        // whole query
        $sup = Supplier::selectRaw('master.master_supplier.*, master.master_status.status_label, master.master_city.city_name, NULL as action')
        ->join('master.master_status','master.master_supplier.status_code','=','master.master_status.status_code')
        ->join('master.master_city','master.master_supplier.city_code','=','master.master_city.city_code');

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if($field == 'status_code')
                        $sup->where("master.master_status.".$field,$val);
                    else if($field == 'find'){
                        if(!empty($val)){
                            $sup->where(function($sup) use($column_search,$val){
                                foreach($column_search as $row)
                                    $sup->orWhere($row,'like',"%".$val."%");
                            });
                        }
                    }
                }
            }
        }

        $sup->orderBy($input['sort']['field'],$input['sort']['sort']);

        $data = $sup->get();

        return response()->json($data,200);
    }

    public function grid(Request $r){
        // collect data from post
        $input = $r->input();
        $column_search = [
            'supplier_code',
            'supplier_name',
            'supplier_phone',
            'supplier_address',
            'supplier_npwp',
            'city_name',
            'master.master_city.city_code',
            'supplier_category'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'supplier_code'
            );

        // whole query
        $sup = Supplier::selectRaw('master.master_supplier.*, master.master_status.status_label, master.master_city.city_name, NULL as action')
        ->join('master.master_status','master.master_supplier.status_code','=','master.master_status.status_code')
        ->join('master.master_city','master.master_supplier.city_code','=','master.master_city.city_code');

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if($field == 'status_code')
                        $sup->where("master.master_status.".$field,$val);
                    else if($field == 'find'){
                        if(!empty($val)){
                            $sup->where(function($sup) use($column_search,$val){
                                foreach($column_search as $row)
                                    $sup->orWhere($row,'like',"%".$val."%");
                            });
                        }
                    }
                }
            }
        }
        // $sup->where();
        $count_all = $sup->count();
        // get total page from count all
        $pages = (!empty($input['pagination']['perpage']) && !is_null($input['pagination']['perpage']))? ceil($count_all/$input['pagination']['perpage']):1;

        $sup->orderBy($input['sort']['field'],$input['sort']['sort']);

        // skipping for next page
        $skip = (!empty($input['pagination']['perpage']) && !is_null($input['pagination']['perpage']))?($input['pagination']['page']-1)*$input['pagination']['perpage']:0;
        $sup->skip($skip);
        if(!empty($input['pagination']['perpage']) && !is_null($input['pagination']['perpage']))
            $sup->take($input['pagination']['perpage']);

        $row = $sup->get();
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

    public function autocomplete(Request $r){
        $return = [];

        $supplier = DB::select(DB::raw("SELECT * FROM (
            SELECT supplier_code, (supplier_code + ' - ' + supplier_name) as supplier
            FROM master.master_supplier) as supp WHERE supplier LIKE '%".$r->find."%'"));
        if(count($supplier) > 0)
            foreach($supplier as $row){
                $return[] = [
                    'id' => $row->supplier_code,
                    'label' => $row->supplier
                ];
            }
        return $return;
    }

}
