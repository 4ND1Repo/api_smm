<?php

namespace App\Http\Controllers\Warehouse;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Master\StockModel AS MasterStock;
use App\Model\Stock\StockModel AS Stock;
use App\Model\Stock\QtyModel AS Qty;
use App\Model\Stock\QtyOutModel AS QtyOut;
use App\Model\Document\RequestToolsModel AS ReqTools;
use App\Model\Document\RequestToolsDetailModel AS ReqToolsDetail;

// Embed a Helper
use DB;
use App\Helpers\Api;
use Illuminate\Http\Request;



class StockController extends Controller
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
        $prefix = "MSQ";
        $SP = Stock::select('main_stock_code')->orderBy('main_stock_code', 'DESC')->get();
        if($SP->count() > 0){
            $SP = $SP->first();
            $tmp = explode($prefix, $SP->main_stock_code);
            $count = ((int)$tmp[1])+1;
        } else
            $count = 1;

        return $prefix.sprintf("%07d",$count);
    }

    private function _generate_master_prefix($prefix = "STK"){
        $SP = MasterStock::select('stock_code')->where('stock_code','LIKE',$prefix.'%')->orderBy('stock_code', 'DESC')->get();
        if($SP->count() > 0){
            $SP = $SP->first();
            $tmp = explode($prefix, $SP->stock_code);
            $count = ((int)$tmp[1])+1;
        } else
            $count = 1;

        return $prefix.sprintf("%07d",$count);
    }

    public function index(){
        return date("Y-m-d H:i:s");
    }

    public function __check_data($r){
        $find = Stock::where(['menu_page' => $r->menu_page, 'stock_code' => $r->stock_code]);
        if($find->count() > 0)
            return $find->get();
        return false;
    }

    public function __check_master_data($r){
        $find = MasterStock::where(['stock_name' => $r->stock_name, 'stock_size' => $r->stock_size, 'stock_brand' => $r->stock_brand, 'stock_type' => $r->stock_type])->get();
        if($find->count() > 0)
            return $find->first();
        return false;
    }

    public function find($id){
        $data = Stock::selectRaw('stock.stock.main_stock_code, master.master_stock.*, qty.stock_qty')
        ->join('master.master_stock','master.master_stock.stock_code','=','stock.stock.stock_code')
        ->leftJoin(DB::raw("(SELECT DISTINCT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty GROUP BY main_stock_code ) AS qty"),'qty.main_stock_code','=','stock.stock.main_stock_code')
        ->where('stock.stock.main_stock_code',$id)
        ->first();
        return Api::response(true,"Sukses",$data);
    }

    public function add_master($r){
        $stock = new MasterStock;
        $stock->stock_code = $this->_generate_master_prefix($r->category_code);
        $stock->stock_name = $r->input('stock_name');
        $stock->stock_size = $r->input('stock_size');
        $stock->stock_brand = $r->input('stock_brand');
        $stock->stock_type = $r->input('stock_type');
        $stock->stock_color = $r->input('stock_color');
        $stock->measure_code = $r->input('measure_code');
        $stock->stock_min_qty = $r->input('stock_min_qty')?$r->input('stock_min_qty'):0;
        $stock->stock_max_qty = $r->input('stock_max_qty')?$r->input('stock_max_qty'):0;
        $stock->stock_daily_use = $r->has('stock_daily_use')?1:0;
        $stock->save();

        return $stock;
    }

    public function add(Request $r){
        if($stock = $this->__check_master_data($r))
            $stock->first();
        else
            $stock = $this->add_master($r);
        
        if(! $this->__check_data($r)){

            $stk = Stock::firstOrNew(['stock_code'=>$stock->stock_code, 'menu_page' => $r->menu_page]);
            if(!$stk->exists){
                $stk->main_stock_code = $this->_generate_prefix();
                $stk->nik = $r->nik;
                $stk->save();
            } else 
                return response()->json(Api::response(false,'Stock sudah tersedia'),200);

            return response()->json(Api::response(true,'Sukses'),200);
        }

        return response()->json(Api::response(false,'Data sudah dimasukan ke rak'),200);
    }

    public function edit(Request $r){
        // edit stock
        $old = MasterStock::where(['stock_code'=>$r->input('stock_code')]);
        if($old->count() > 0){
            $old->update([
                'stock_name' => $r->input('stock_name'),
                'stock_size' => $r->input('stock_size'),
                'stock_brand' => $r->input('stock_brand'),
                'stock_type' => $r->input('stock_type'),
                'stock_color' => $r->input('stock_color'),
                'measure_code' => $r->input('measure_code'),
                'stock_min_qty' => $r->input('stock_min_qty'),
                'stock_max_qty' => $r->input('stock_max_qty'),
                'stock_daily_use' => $r->has('stock_daily_use')?1:0
            ]);
        } else 
            return response()->json(Api::response(false,"Data stok tidak ada"),200);

        return response()->json(Api::response(true,"Sukses"),200);
    }

    public function delete(Request $r){
        // delete from stock qty first then main stock
        Qty::where(['main_stock_code' => $r->main_stock_code])->delete();
        Stock::where(['main_stock_code' => $r->main_stock_code])->delete();

        return response()->json(Api::response(true,'Sukses'),200);
    }

    public function autocomplete(Request $r){
        $return = [];
        // $stock = Stock::join('master.master_stock','master.master_stock.stock_code','=','stock.stock.stock_code');
        $stock = DB::select(DB::raw("SELECT * FROM (
            SELECT stock.stock.main_stock_code, (master.master_stock.stock_code + ' - ' + master.master_stock.stock_name + ' - ' + master.master_stock.stock_type + ' - ' + master.master_stock.stock_size) as stock_name
            FROM stock.stock 
            JOIN master.master_stock ON master.master_stock.stock_code = stock.stock.stock_code
        ) as stock WHERE stock_name LIKE '%".$r->find."%'"));
        if(count($stock) > 0)
            foreach($stock as $row){
                $return[] = [
                    'id' => $row->main_stock_code,
                    'label' => $row->stock_name
                ];
            }
        return $return;
    }

    public function grid(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'stock.stock.stock_code',
            'stock_name',
            'stock_size',
            'stock_brand',
            'stock_type',
            'stock_color',
            'cabinet.cabinet_name',
            'master.master_measure.measure_type',
            'qty.stock_qty',
            'stock_min_qty',
            'stock_max_qty'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'stock_name'
            );

        // whole query
        $sup = Stock::selectRaw('stock.stock.main_stock_code, master.master_stock.*, master.master_measure.measure_type, qty.stock_qty, cabinet.cabinet_name')
        ->join('master.master_stock','master.master_stock.stock_code','=','stock.stock.stock_code')
        ->join('master.master_measure','master.master_measure.measure_code','=','master.master_stock.measure_code')
        ->leftJoin(DB::raw("(SELECT main_stock_code, cabinet_name FROM stock.cabinet LEFT JOIN master.master_cabinet ON master.master_cabinet.cabinet_code = stock.cabinet.cabinet_code WHERE master.master_cabinet.menu_page = '".$input['menu_page']."') AS cabinet"),'cabinet.main_stock_code','=','stock.stock.main_stock_code')
        ->leftJoin(DB::raw("(SELECT DISTINCT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty GROUP BY main_stock_code ) AS qty"),'qty.main_stock_code','=','stock.stock.main_stock_code')
        ->where(['stock.stock.menu_page' => $input['menu_page']]);

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('measure_code','stock_brand','stock_daily_use')))
                        $sup->where("master.master_stock.".$field,($val=="null"?NULL:$val));
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

    public function history(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'stock.stock.stock_code',
            'stock.qty.nik',
            'stock.qty.stock_notes',
            'master.master_stock.stock_name',
            'master.master_stock.stock_size',
            'master.master_stock.stock_brand',
            'master.master_stock.stock_type',
            'master.master_stock.stock_color',
            'master.master_measure.measure_type',
            'stock.qty.qty',
            'stock.qty.stock_price',
            'master.master_stock.stock_min_qty',
            'master.master_stock.stock_max_qty',
            'master.master_supplier.supplier_name'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'stock_name'
            );

        // whole query
        $sup = Qty::selectRaw('stock.qty.stock_notes, stock.qty.nik, stock.qty.stock_date, stock.qty.stock_price, master.master_supplier.supplier_name, stock.qty.main_stock_code, master.master_stock.*, master.master_measure.measure_type, (stock.qty.qty + CASE WHEN (
            SELECT TOP 1 ISNULL(SUM(qty),0) FROM stock.qty_out WHERE 
                main_stock_code=stock.qty.main_stock_code
                AND stock_price=stock.qty.stock_price
                AND stock_date=stock.qty.stock_date
                AND supplier_code=stock.qty.supplier_code
            GROUP BY main_stock_code, stock_price, stock_date, supplier_code
            ) IS NOT NULL THEN (
                SELECT TOP 1 ISNULL(SUM(qty),0) FROM stock.qty_out WHERE 
                    main_stock_code=stock.qty.main_stock_code
                    AND stock_price=stock.qty.stock_price
                    AND stock_date=stock.qty.stock_date
                    AND supplier_code=stock.qty.supplier_code
                GROUP BY main_stock_code, stock_price, stock_date, supplier_code
                ) ELSE 0 END) AS stock_qty')
        ->join('stock.stock', 'stock.stock.main_stock_code', '=', 'stock.qty.main_stock_code')
        ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
        ->join('master.master_measure', 'master.master_measure.measure_code', '=', 'master.master_stock.measure_code')
        ->leftJoin('master.master_supplier', 'master.master_supplier.supplier_code', '=', 'stock.qty.supplier_code')
        ->where(['stock.stock.menu_page' => $input['menu_page']]);

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('measure_code','stock_brand','stock_daily_use')))
                        $sup->where("master.master_stock.".$field,($val=="null"?NULL:$val));
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

    public function history_out(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'stock.stock.stock_code',
            'stock.qty_out.nik',
            'stock.qty_out.stock_notes',
            'master.master_stock.stock_name',
            'master.master_stock.stock_size',
            'master.master_stock.stock_brand',
            'master.master_stock.stock_type',
            'master.master_stock.stock_color',
            'master.master_measure.measure_type',
            'stock.qty_out.qty',
            'stock.qty_out.stock_price',
            'master.master_stock.stock_min_qty',
            'master.master_stock.stock_max_qty',
            'master.master_supplier.supplier_name'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'stock_name'
            );

        // whole query
        $sup = QtyOut::selectRaw('stock.qty_out.stock_notes, stock.qty_out.nik, stock.qty_out.stock_date, stock.qty_out.stock_out_date, stock.qty_out.stock_price, master.master_supplier.supplier_name, stock.qty_out.main_stock_code, master.master_stock.*, master.master_measure.measure_type, stock.qty_out.qty AS stock_qty')
        ->join('stock.stock', 'stock.stock.main_stock_code', '=', 'stock.qty_out.main_stock_code')
        ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
        ->join('master.master_measure', 'master.master_measure.measure_code', '=', 'master.master_stock.measure_code')
        ->leftJoin('master.master_supplier', 'master.master_supplier.supplier_code', '=', 'stock.qty_out.supplier_code')
        ->where(['stock.stock.menu_page' => $input['menu_page']]);

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('measure_code','stock_brand','stock_daily_use')))
                        $sup->where("master.master_stock.".$field,($val=="null"?NULL:$val));
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