<?php

namespace App\Http\Controllers\Warehouse;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Stock\CabinetModel AS Cabinet;

// Embed a Helper
use DB;
use App\Helpers\{Api, Log};
use Illuminate\Http\Request;



class CabinetController extends Controller
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
        $prefix = "CBS";
        $SP = Cabinet::select('stock_cabinet_code')->orderBy('stock_cabinet_code', 'DESC')->get();
        if($SP->count() > 0){
            $SP = $SP->first();
            $tmp = explode($prefix, $SP->stock_cabinet_code);
            $count = ((int)$tmp[1])+1;
        } else
            $count = 1;

        return $prefix.sprintf("%07d",$count);
    }

    public function index(){
        return date("Y-m-d H:i:s");
    }

    public function __check_data($r){
        $find = Cabinet::where(['page_code' => $r->page_code, 'main_stock_code' => $r->main_stock_code]);
        if($find->count() > 0)
            return $find->get();
        return false;
    }

    public function add(Request $r){
        if(! $this->__check_data($r)){
            $cab = new Cabinet;
            $cab->stock_cabinet_code = $this->_generate_prefix();
            $cab->page_code = $r->page_code;
            $cab->cabinet_code = $r->cabinet_code;
            $cab->main_stock_code = $r->main_stock_code;
            $cab->save();

            Log::add([
              'type' => 'Add',
              'nik' => $r->nik,
              'description' => 'Menambah Stok di Rak : '.Stock::where(['main_stock_code' => $r->main_stock_code])->first()->stock_code
            ]);

            return response()->json(Api::response(true,'Sukses', $cab),200);
        }

        return response()->json(Api::response(false,'Data sudah dimasukan ke rak'),200);
    }

    public function delete(Request $r){
        $rak = Cabinet::where(['stock_cabinet_code' => $r->stock_cabinet_code])->first();
        Cabinet::where(['stock_cabinet_code' => $r->stock_cabinet_code])->delete();

        Log::add([
          'type' => 'Delete',
          'nik' => $r->nik,
          'description' => 'Menghapus rak : '.$rak->cabinet_name
        ]);

        return response()->json(Api::response(true,'Sukses'),200);
    }


    public function grid(Request $r){
        // collect data from post
        $input = $r->input();
        $column_search = [
            'master_cabinet.cabinet_name',
            'stock.stock.stock_code',
            'stock_name',
            'stock_size',
            'stock_brand',
            'stock_type',
            'stock_color'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'stock_name'
            );
        if($input['sort']['field'] == "stock_code") $input['sort']['field'] = "stock.stock.".$input['sort']['field'];

        // whole query
        $sup = Cabinet::selectRaw('master.master_cabinet.cabinet_name, master.master_stock.*, stock.cabinet.stock_cabinet_code')
        ->join('master.master_cabinet','master.master_cabinet.cabinet_code','=','stock.cabinet.cabinet_code')
        ->join('stock.stock','stock.cabinet.main_stock_code','=','stock.stock.main_stock_code')
        ->join('master.master_stock','stock.stock.stock_code','=','master.master_stock.stock_code')
        ->where(['stock.cabinet.page_code' => $input['page_code'], 'stock.cabinet.cabinet_code' => $input['cabinet_code']]);

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if($field == 'measure_code')
                        $sup->where("master.master_stock.".$field,$val);
                    else if($field == 'stock_brand')
                        $sup->where("master.master_stock.".$field,($val=="null"?NULL:$val));
                    else if($field == 'find'){
                        if(!empty($val)){
                            $sup->where(function($sup) use($column_search,$val){
                                foreach($column_search as $row)
                                    $sup->orWhere($row,'like',(in_array($row,['stock_name'])?"":"%").$val."%");
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
        $sup->get();

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

}
