<?php

namespace App\Http\Controllers\Warehouse;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Stock\OpnameModel AS Opname;
use App\Model\Stock\StockModel AS Stock;

// Embed a Helper
use DB;
use App\Helpers\Api;
use Illuminate\Http\Request;



class OpnameController extends Controller
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
        return date("Y-m-d H:i:s");
    }

    private function __check_approve($r){
        $query = Opname::where(['main_stock_code' => $r->main_stock_code])
            ->where(function($query){
                $query->where('approve_by', NULL);
                $query->where('reject_by', NULL);
            });
        return ($query->count()==0);
    }

    public function find($id){
      list($main_stock_code, $opname_date_from) = explode('|', urldecode($id));
      $query = Opname::selectRaw('stock.opname.*, master.master_stock.*')
          ->join('stock.stock', 'stock.stock.main_stock_code','=','stock.opname.main_stock_code')
          ->join('master.master_stock', 'master.master_stock.stock_code','=','stock.stock.stock_code')
          ->where(['stock.opname.main_stock_code' => $main_stock_code, 'opname_date_from' => $opname_date_from])
          ->orderBy('stock.opname.create_date','DESC')
          ->first();
      return response()->json(Api::response(true, 'Sukses', $query),200);
    }

    public function date(){
      $query = Opname::select('opname_date_from')
          ->groupBy(['opname_date_from'])
          ->get();
      return response()->json(Api::response(true,'Sukses',$query),200);
    }

    public function add(Request $r){
        if($this->__check_approve($r)){
            $op = new Opname;
            $op->main_stock_code = $r->main_stock_code;
            $op->opname_qty = $r->opname_qty;
            $op->opname_qty_from = $r->opname_qty_from;
            $op->create_by = $r->nik;
            $op->opname_notes = $r->opname_notes;
            $op->save();

            return response()->json(Api::response(true, 'sukses'),200);
        }

        return response()->json(Api::response(false, 'Stok sedang menunggu Approve'),200);
    }

    public function approve(Request $r){
        list($main_stock_code,$opname_date_from) = explode('|', urldecode($r->opname));
        $date = date("Y-m-d H:i:s");
        $stk = Stock::where(['main_stock_code' => $main_stock_code])->first();

        $qty = Opname::selectRaw("CAST((opname_qty_from - opname_qty) AS NUMERIC) AS qty")->where(['main_stock_code' => $main_stock_code, 'opname_date_from' => $opname_date_from])->first()->qty;
        DB::select(DB::raw("EXEC stock.stock_out @stcode='".$stk->stock_code."', @qty='".$qty."',@nik='".$r->nik."', @notes='Stock Opname (".$date.")', @page='".$stk->page_code."' "));
        $query = Opname::where([
            'main_stock_code' => $main_stock_code,
            'opname_date_from' => $opname_date_from
          ])->update([
            'approve_by' => $r->nik,
            'approve_date' => $date
          ]);

        return response()->json(Api::response(true, 'sukses'),200);
    }

    public function reject(Request $r){
        list($main_stock_code,$opname_date_from) = explode('|', urldecode($r->opname));
        $query = Opname::where([
            'main_stock_code' => $main_stock_code,
            'opname_date_from' => $opname_date_from
          ])->update([
            'reject_by' => $r->nik,
            'reject_date' => date("Y-m-d H:i:s")
          ]);

        return response()->json(Api::response(true, 'sukses'),200);
    }

    public function get(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'stock.stock.stock_code',
            'master.master_stock.stock_name',
            'master.master_stock.stock_size',
            'master.master_stock.stock_brand',
            'master.master_stock.stock_type',
            'opname_qty_from',
            'opname_qty',
            'opname_notes'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'opname_qty_from'
            );

        // whole query
        $sup = Opname::selectRaw('stock.opname.*, stock.stock.stock_code, master.master_stock.stock_name, master.master_stock.stock_size, master.master_stock.stock_brand, master.master_stock.stock_color, master.master_stock.stock_min_qty, master.master_stock.stock_daily_use, master.master_stock.stock_type, master.master_measure.measure_type')
        ->join('stock.stock', 'stock.opname.main_stock_code', '=', 'stock.stock.main_stock_code')
        ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
        ->leftJoin('master.master_measure', 'master.master_measure.measure_code', '=', 'master.master_stock.measure_code')
        ->where(['stock.stock.page_code' => $input['page_code']]);

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('stock_brand')) && (!empty($val) && !is_null($val)))
                        $sup->where("master.master_stock.".$field,($val=="null"?NULL:$val));
                    else if(in_array($field, array('approve'))){
                        if($val==1)
                            $sup->whereRaw("stock.opname.approve_by IS NOT NULL");
                        else if($val==0)
                            $sup->whereRaw("stock.opname.reject_by IS NOT NULL");
                    }
                    else if(in_array($field, array('opname_date_from')))
                        $sup->where("stock.opname.".$field,($val=="null"?NULL:$val));
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
            'stock.stock.stock_code',
            'master.master_stock.stock_name',
            'master.master_stock.stock_size',
            'master.master_stock.stock_brand',
            'master.master_stock.stock_type',
            'opname_qty_from',
            'opname_qty',
            'opname_notes'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'opname_qty_from'
            );

        // whole query
        $sup = Opname::selectRaw('stock.opname.*, stock.stock.stock_code, master.master_stock.stock_name, master.master_stock.stock_size, master.master_stock.stock_brand, master.master_stock.stock_type')
        ->join('stock.stock', 'stock.opname.main_stock_code', '=', 'stock.stock.main_stock_code')
        ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
        ->where(['stock.stock.page_code' => $input['page_code']]);

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('stock_brand')))
                        $sup->where("master.master_stock.".$field,($val=="null"?NULL:$val));
                    else if(in_array($field, array('approve'))){
                        if($val==1)
                            $sup->whereRaw("stock.opname.approve_by IS NOT NULL");
                        else if($val==0)
                            $sup->whereRaw("stock.opname.reject_by IS NOT NULL");
                    }
                    else if(in_array($field, array('opname_date_from')))
                        $sup->where("stock.opname.".$field,($val=="null"?NULL:$val));
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
}
