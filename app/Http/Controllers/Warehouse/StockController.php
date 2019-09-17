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
use App\Helpers\{Api, Log, Converter};
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
        $find = Stock::where(['page_code' => $r->page_code, 'stock_code' => $r->stock_code]);
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
        return response()->json(Api::response(true,"Sukses",$data),200);
    }

    public function find_by_stock(Request $r){
        $qty = 'qty.stock_qty';
        // if find stock by borrow page
        if($r->has('borrow'))
          $qty = "(qty.stock_qty - CASE WHEN (SELECT SUM(borrowed_qty) FROM document.borrowed WHERE main_stock_code=stock.stock.main_stock_code AND status = 'ST06') IS NOT NULL THEN (SELECT SUM(borrowed_qty) FROM document.borrowed WHERE main_stock_code=stock.stock.main_stock_code AND status = 'ST06') ELSE 0 END) AS stock_qty";

        $data = Stock::selectRaw("stock.stock.main_stock_code, master.master_stock.*, ".$qty.", master.master_measure.measure_type, (SELECT SUM(rqd.req_tools_qty) as qty FROM document.request_tools_detail rqd JOIN document.request_tools rq ON rq.req_tools_code = rqd.req_tools_code WHERE rqd.stock_code = master.master_stock.stock_code AND rq.page_code='".$r->page_code."' AND rqd.fullfillment=0 GROUP BY rqd.stock_code, rq.page_code, rqd.fullfillment) as need_qty")
          ->join('master.master_stock','master.master_stock.stock_code','=','stock.stock.stock_code')
          ->leftJoin(DB::raw("(SELECT DISTINCT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty GROUP BY main_stock_code ) AS qty"),'qty.main_stock_code','=','stock.stock.main_stock_code')
          ->leftJoin("master.master_measure",'master.master_measure.measure_code','=','master.master_stock.measure_code')
          ->where('stock.stock.stock_code',$r->stock_code)
          ->where('stock.stock.page_code',$r->page_code)
          ->first();
        $data['query'] = $qty;
        return response()->json(Api::response(true,"Sukses",$data),200);
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

            $stk = Stock::firstOrNew(['stock_code'=>$stock->stock_code, 'page_code' => $r->page_code]);
            if(!$stk->exists){
                $stk->main_stock_code = $this->_generate_prefix();
                $stk->nik = $r->nik;
                $stk->save();

                Log::add([
                  'type' => 'Add',
                  'nik' => $r->nik,
                  'description' => 'Menambah Stok baru : '.$stk->stock_code
                ]);
            } else
                return response()->json(Api::response(false,'Stock sudah tersedia'),200);

            return response()->json(Api::response(true,'Sukses'),200);
        }

        return response()->json(Api::response(false,'Data stok sudah ada'),200);
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
                'stock_min_qty' => (!empty($r->input('stock_min_qty')) && !is_null($r->input('stock_min_qty')))?$r->input('stock_min_qty'):0,
                'stock_daily_use' => $r->has('stock_daily_use')?1:0
            ]);
            Log::add([
              'type' => 'Edit',
              'nik' => $r->nik,
              'description' => 'Mengubah stok : '.$r->input('stock_code')
            ]);
        } else
            return response()->json(Api::response(false,"Data stok tidak ada"),200);

        return response()->json(Api::response(true,"Sukses"),200);
    }

    public function delete(Request $r){
        // delete from stock qty first then main stock
        Qty::where(['main_stock_code' => $r->main_stock_code])->delete();
        $stock_code = Stock::where(['main_stock_code' => $r->main_stock_code])->first()->stock_code;
        Stock::where(['main_stock_code' => $r->main_stock_code])->delete();
        MasterStock::where(['stock_code' => $stock_code])->delete();

        Log::add([
          'type' => 'Delete',
          'nik' => $r->nik,
          'description' => 'Menghapus stok : '.$stock_code
        ]);

        return response()->json(Api::response(true,'Sukses'),200);
    }

    public function autocomplete(Request $r){
        $return = [];
        // $stock = Stock::join('master.master_stock','master.master_stock.stock_code','=','stock.stock.stock_code');
        $stock = DB::select(DB::raw("SELECT TOP 10 * FROM (
            SELECT stock.stock.main_stock_code, ('(' + master.master_stock.stock_code + ') ' + master.master_stock.stock_name + (CASE WHEN master.master_stock.stock_size IS NOT NULL THEN ' ' + master.master_stock.stock_size ELSE '' END) + (CASE WHEN master.master_stock.stock_type IS NOT NULL THEN ' ' + master.master_stock.stock_type ELSE '' END) + (CASE WHEN master.master_stock.stock_brand IS NOT NULL THEN ' ' + master.master_stock.stock_brand ELSE '' END) + (CASE WHEN master.master_stock.stock_color IS NOT NULL THEN ' ' + master.master_stock.stock_color ELSE '' END)) as stock_find,
            (SELECT SUM(rqd.req_tools_qty) as qty FROM document.request_tools_detail rqd JOIN document.request_tools rq ON rq.req_tools_code = rqd.req_tools_code WHERE rqd.stock_code = master.master_stock.stock_code AND rq.page_code='".$r->page_code."' AND rqd.fullfillment=0 GROUP BY rqd.stock_code, rq.page_code, rqd.fullfillment) as need_qty,
            master.master_stock.*,
            CASE WHEN stock_qty.qty IS NOT NULL THEN stock_qty.qty ELSE 0 END AS qty,
            master.master_measure.measure_type
            FROM stock.stock
            JOIN master.master_stock ON master.master_stock.stock_code = stock.stock.stock_code".($r->has('stock_daily_use')?" AND stock_daily_use = 1":"")."
            JOIN master.master_measure ON master.master_measure.measure_code = master.master_stock.measure_code
            LEFT JOIN (SELECT DISTINCT main_stock_code, SUM(qty) AS qty FROM stock.qty GROUP BY main_stock_code) AS stock_qty ON stock_qty.main_stock_code = stock.stock.main_stock_code
        ) as stock WHERE stock_find LIKE '%".$r->find."%'"));
        if(count($stock) > 0)
            foreach($stock as $row){
                $return[] = [
                    'id' => $row->main_stock_code,
                    'label' => $row->stock_find,
                    'need' => $row->need_qty,
                    'data' => $row
                ];
            }
        return $return;
    }

    public function get(Request $r){
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
            'stock_min_qty'
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
        ->leftJoin(DB::raw("(SELECT main_stock_code, cabinet_name FROM stock.cabinet LEFT JOIN master.master_cabinet ON master.master_cabinet.cabinet_code = stock.cabinet.cabinet_code WHERE master.master_cabinet.page_code = '".$input['page_code']."') AS cabinet"),'cabinet.main_stock_code','=','stock.stock.main_stock_code')
        ->leftJoin(DB::raw("(SELECT DISTINCT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty GROUP BY main_stock_code ) AS qty"),'qty.main_stock_code','=','stock.stock.main_stock_code')
        ->where(['stock.stock.page_code' => $input['page_code']]);

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('measure_code','stock_brand','stock_size','stock_type','stock_color','stock_daily_use')) && (!empty($val) && !is_null($val)))
                        $sup->where("master.master_stock.".$field,($val=="null"?NULL:urldecode($val)));
                    else if($field == 'find'){
                        if(!empty($val)){
                            $sup->where(function($sup) use($column_search,$val){
                                foreach($column_search as $row)
                                    $sup->orWhere($row,'like',(in_array($row,['stock_name','stock_brand','stock_size'])?"":"%").$val."%");
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
        if($input['pagination']['perpage'] == "NaN") $input['pagination']['perpage'] = NULL;

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
            'stock_min_qty'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'stock_name'
            );
        if($input['sort']['field'] == "stock_code") $input['sort']['field'] = "stock.stock.".$input['sort']['field'];

        // whole query
        $sup = Stock::selectRaw('stock.stock.main_stock_code, master.master_stock.*, master.master_measure.measure_type, qty.stock_qty, cabinet.cabinet_name')
        ->join('master.master_stock','master.master_stock.stock_code','=','stock.stock.stock_code')
        ->join('master.master_measure','master.master_measure.measure_code','=','master.master_stock.measure_code')
        ->leftJoin(DB::raw("(SELECT main_stock_code, cabinet_name FROM stock.cabinet LEFT JOIN master.master_cabinet ON master.master_cabinet.cabinet_code = stock.cabinet.cabinet_code WHERE master.master_cabinet.page_code = '".$input['page_code']."') AS cabinet"),'cabinet.main_stock_code','=','stock.stock.main_stock_code')
        ->leftJoin(DB::raw("(SELECT DISTINCT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty GROUP BY main_stock_code ) AS qty"),'qty.main_stock_code','=','stock.stock.main_stock_code')
        ->where(['stock.stock.page_code' => $input['page_code']]);

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('measure_code','stock_brand','stock_type','stock_size','stock_color','stock_daily_use')))
                        $sup->where("master.master_stock.".$field,($val=="null"?NULL:urldecode($val)));
                    else if($field == 'find'){
                        if(!empty($val)){
                            $sup->where(function($sup) use($column_search,$val){
                                foreach($column_search as $row)
                                    $sup->orWhere($row,'like',(in_array($row,['stock_name','stock_brand','stock_size'])?"":"%").$val."%");
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

    public function history_get(Request $r){
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
        $sup = Qty::selectRaw("stock.qty.stock_notes, stock.qty.nik, FORMAT (stock.qty.stock_date, 'dd/MM/yyyy hh:mm:ss') as stock_date, stock.qty.stock_price, master.master_supplier.supplier_name, stock.qty.main_stock_code, master.master_stock.*, master.master_measure.measure_type, (stock.qty.qty + CASE WHEN (
            SELECT TOP 1 SUM(qty) FROM stock.qty_out WHERE
                main_stock_code=stock.qty.main_stock_code
                AND stock_price=stock.qty.stock_price
                AND stock_date=stock.qty.stock_date
                AND (supplier_code=stock.qty.supplier_code OR supplier_code IS NULL)
            GROUP BY main_stock_code, stock_price, stock_date, supplier_code
            ) IS NOT NULL THEN (
                SELECT TOP 1 SUM(qty) FROM stock.qty_out WHERE
                    main_stock_code=stock.qty.main_stock_code
                    AND stock_price=stock.qty.stock_price
                    AND stock_date=stock.qty.stock_date
                    AND (supplier_code=stock.qty.supplier_code OR supplier_code IS NULL)
                GROUP BY main_stock_code, stock_price, stock_date, supplier_code
                ) ELSE 0 END) AS stock_qty")
            ->join('stock.stock', 'stock.stock.main_stock_code', '=', 'stock.qty.main_stock_code')
            ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
            ->join('master.master_measure', 'master.master_measure.measure_code', '=', 'master.master_stock.measure_code')
            ->leftJoin('master.master_supplier', 'master.master_supplier.supplier_code', '=', 'stock.qty.supplier_code')
            ->where(['stock.stock.page_code' => $input['page_code']]);

        // condition for date range
        if(isset($input['query']['start_date']))
            $sup->whereRaw("stock_date >= '".Converter::fromID($input['query']['start_date'])." 00:00:00'");
        if(isset($input['query']['end_date']))
            $sup->whereRaw("stock_date <= '".Converter::fromID($input['query']['end_date'])." 23:59:59'");

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('measure_code','stock_brand','stock_size','stock_type','stock_color','stock_daily_use')) && (!is_null($val) && !empty($val)))
                        $sup->where("master.master_stock.".$field,($val=="null"?NULL:urldecode($val)));
                    else if($field == 'find'){
                        if(!empty($val)){
                            $sup->where(function($sup) use($column_search,$val){
                                foreach($column_search as $row)
                                    $sup->orWhere($row,'like',(in_array($row,['master.master_stock.stock_name'])?"":"%").$val."%");
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
        $sup = Qty::selectRaw("stock.qty.stock_notes, stock.qty.nik, FORMAT (stock.qty.stock_date, 'dd/MM/yyyy hh:mm:ss') as stock_date, stock.qty.stock_price, master.master_supplier.supplier_name, stock.qty.main_stock_code, master.master_stock.*, master.master_measure.measure_type, (stock.qty.qty + CASE WHEN (
            SELECT TOP 1 SUM(qty) FROM stock.qty_out WHERE
                main_stock_code=stock.qty.main_stock_code
                AND stock_price=stock.qty.stock_price
                AND stock_date=stock.qty.stock_date
                AND (supplier_code=stock.qty.supplier_code OR supplier_code IS NULL)
            GROUP BY main_stock_code, stock_price, stock_date, supplier_code
            ) IS NOT NULL THEN (
                SELECT TOP 1 SUM(qty) FROM stock.qty_out WHERE
                    main_stock_code=stock.qty.main_stock_code
                    AND stock_price=stock.qty.stock_price
                    AND stock_date=stock.qty.stock_date
                    AND (supplier_code=stock.qty.supplier_code OR supplier_code IS NULL)
                GROUP BY main_stock_code, stock_price, stock_date, supplier_code
                ) ELSE 0 END) AS stock_qty")
        ->join('stock.stock', 'stock.stock.main_stock_code', '=', 'stock.qty.main_stock_code')
        ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
        ->join('master.master_measure', 'master.master_measure.measure_code', '=', 'master.master_stock.measure_code')
        ->leftJoin('master.master_supplier', 'master.master_supplier.supplier_code', '=', 'stock.qty.supplier_code')
        ->where(['stock.stock.page_code' => $input['page_code']]);

        // condition for date range
        if(isset($input['query']['start_date']))
            $sup->whereRaw("stock_date >= '".Converter::fromID($input['query']['start_date'])." 00:00:00'");
        if(isset($input['query']['end_date']))
            $sup->whereRaw("stock_date <= '".Converter::fromID($input['query']['end_date'])." 23:59:59'");

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('measure_code','stock_brand','stock_size','stock_type','stock_color','stock_daily_use')))
                        $sup->where("master.master_stock.".$field,($val=="null"?NULL:urldecode($val)));
                    else if($field == 'find'){
                        if(!empty($val)){
                            $sup->where(function($sup) use($column_search,$val){
                                foreach($column_search as $row)
                                    $sup->orWhere($row,'like',(in_array($row,['master.master_stock.stock_name'])?"":"%").$val."%");
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

    public function history_out_get(Request $r){
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
        $sup = QtyOut::selectRaw("stock.qty_out.stock_notes, stock.qty_out.nik, FORMAT (stock.qty_out.stock_date, 'dd/MM/yyyy hh:mm:ss') AS stock_date, FORMAT (stock.qty_out.stock_out_date, 'dd/MM/yyyy hh:mm:ss') AS stock_out_date, stock.qty_out.stock_price, master.master_supplier.supplier_name, stock.qty_out.main_stock_code, master.master_stock.*, master.master_measure.measure_type, stock.qty_out.qty AS stock_qty")
            ->join('stock.stock', 'stock.stock.main_stock_code', '=', 'stock.qty_out.main_stock_code')
            ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
            ->join('master.master_measure', 'master.master_measure.measure_code', '=', 'master.master_stock.measure_code')
            ->leftJoin('master.master_supplier', 'master.master_supplier.supplier_code', '=', 'stock.qty_out.supplier_code')
            ->where(['stock.stock.page_code' => $input['page_code']]);

        // condition for date range
        if(isset($input['query']['start_date']))
            $sup->whereRaw("stock_out_date >= '".Converter::fromID($input['query']['start_date'])." 00:00:00'");
        if(isset($input['query']['end_date']))
            $sup->whereRaw("stock_out_date <= '".Converter::fromID($input['query']['end_date'])." 23:59:59'");

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('measure_code','stock_brand','stock_size','stock_type','stock_color','stock_daily_use')) && (!is_null($val) && !empty($val)))
                        $sup->where("master.master_stock.".$field,($val=="null"?NULL:urldecode($val)));
                    else if($field == 'find'){
                        if(!empty($val)){
                            $sup->where(function($sup) use($column_search,$val){
                                foreach($column_search as $row)
                                    $sup->orWhere($row,'like',(in_array($row,['master.master_stock.stock_name'])?"":"%").$val."%");
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
        $sup = QtyOut::selectRaw("stock.qty_out.stock_notes, stock.qty_out.nik, FORMAT (stock.qty_out.stock_date, 'dd/MM/yyyy hh:mm:ss') AS stock_date, FORMAT (stock.qty_out.stock_out_date, 'dd/MM/yyyy hh:mm:ss') AS stock_out_date, stock.qty_out.stock_price, master.master_supplier.supplier_name, stock.qty_out.main_stock_code, master.master_stock.*, master.master_measure.measure_type, stock.qty_out.qty AS stock_qty")
        ->join('stock.stock', 'stock.stock.main_stock_code', '=', 'stock.qty_out.main_stock_code')
        ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
        ->join('master.master_measure', 'master.master_measure.measure_code', '=', 'master.master_stock.measure_code')
        ->leftJoin('master.master_supplier', 'master.master_supplier.supplier_code', '=', 'stock.qty_out.supplier_code')
        ->where(['stock.stock.page_code' => $input['page_code']]);

        // condition for date range
        if(isset($input['query']['start_date']))
            $sup->whereRaw("stock_out_date >= '".Converter::fromID($input['query']['start_date'])." 00:00:00'");
        if(isset($input['query']['end_date']))
            $sup->whereRaw("stock_out_date <= '".Converter::fromID($input['query']['end_date'])." 23:59:59'");

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('measure_code','stock_brand','stock_size','stock_type','stock_color','stock_daily_use')))
                        $sup->where("master.master_stock.".$field,($val=="null"?NULL:urldecode($val)));
                    else if($field == 'find'){
                        if(!empty($val)){
                            $sup->where(function($sup) use($column_search,$val){
                                foreach($column_search as $row)
                                    $sup->orWhere($row,'like',(in_array($row,['master.master_stock.stock_name'])?"":"%").$val."%");
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

    public function qty(Request $r){
        // collect data from post
        $input = $r->input();

        // whole query
        $query = Stock::selectRaw('stock.stock.main_stock_code, qty.stock_qty')
        ->leftJoin(DB::raw("(SELECT DISTINCT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty GROUP BY main_stock_code ) AS qty"),'qty.main_stock_code','=','stock.stock.main_stock_code')
        ->where(['stock.stock.page_code' => $input['page_code'], 'stock.stock.main_stock_code' => $input['main_stock_code']])->first();


        return response()->json(Api::response(true,'Sukses',!is_null($query)?$query->stock_qty:0),200);
    }




    // for import data stock from excel
    public function import(Request $r){
        $date = date("Y-m-d H:i:s");
        if($r->has('data')){
          $lst = [];
          foreach ($r->data as $i => $row) {
            $stk = null;
            if(empty($row['stock_code']) || is_null($row['stock_code'])){
              if(!empty($row['category_code']) && !is_null($row['category_code'])){
                // validation by stock_type, stock_size, stock_brand, stock_color
                $query = MasterStock::where([
                  'stock_type' => $row['stock_type'],
                  'stock_size' => $row['stock_size'],
                  'stock_brand' => $row['stock_brand'],
                  'stock_color' => $row['stock_color']
                ]);

                if($query->count() == 0){
                  $stk = new MasterStock;
                  $stk->stock_code = $this->_generate_master_prefix($row['category_code']);
                  $stk->stock_type = $row['stock_type'];
                  $stk->stock_size = $row['stock_size'];
                  $stk->stock_brand = $row['stock_brand'];
                  $stk->stock_color = $row['stock_color'];
                  $stk->stock_daily_use = $row['stock_daily_use'];
                  $stk->measure_code = $row['measure_code'];
                  $stk->stock_min_qty = $row['stock_min_qty'];
                  $stk->save();
                } else
                  $stk = $query->first();
              }
            } else {
              $query = MasterStock::where(['stock_code' => $row['stock_code']]);
              if($query->count() > 0){
                // process update it or no
                $query->update([
                  'stock_type' => $row['stock_type'],
                  'stock_size' => $row['stock_size'],
                  'stock_brand' => $row['stock_brand'],
                  'stock_color' => $row['stock_color'],
                  'stock_daily_use' => $row['stock_daily_use'],
                  'measure_code' => $row['measure_code'],
                  'stock_min_qty' => $row['stock_min_qty']
                ]);

                $query = MasterStock::where(['stock_code' => $row['stock_code']]);
                $stk = $query->first();
              }
            }

            // get main
            if(!is_null($stk)){
              // check stock already exists or no
              $query = Stock::where(['stock_code' => $stk->stock_code, 'page_code' => $r->page_code]);
              if($query->count() == 0){
                return $stk;
                $main = new Stock;
                $main->main_stock_code = $this->_generate_prefix();
                $main->stock_code = $stk->stock_code;
                $main->page_code = $r->page_code;
                $main->nik = $r->nik;
                if($main->save()){
                  if(!empty($row['qty']) && !is_null($row['qty']) && ((float)$row['qty'] != 0)){
                    $qty = new Qty;
                    $qty->main_stock_code = $main->main_stock_code;
                    $qty->supplier_code = NULL;
                    $qty->stock_price = 0;
                    $qty->qty = (float) $row['qty'];
                    $qty->nik = $r->nik;
                    $qty->stock_date = $date;
                    $qty->stock_notes = "Stock Awal (".$date.")";
                    $qty->save();
                  }
                }
              } else {
                $main = $query->first();
                if(!empty($row['qty']) && !is_null($row['qty']) && ((float)$row['qty'] != 0)){
                  $query = Qty::where(['main_stock_code' => $main->main_stock_code]);
                  if($query->count() > 0){
                    $query->delete();
                  }
                  $query = QtyOut::where(['main_stock_code' => $main->main_stock_code]);
                  if($query->count() > 0){
                    $query->delete();
                  }

                  $qty = new Qty;
                  $qty->main_stock_code = $main->main_stock_code;
                  $qty->supplier_code = NULL;
                  $qty->stock_price = 0;
                  $qty->qty = (float) $row['qty'];
                  $qty->nik = $r->nik;
                  $qty->stock_date = $date;
                  $qty->stock_notes = "Stock Awal (".$date.")";
                  $qty->save();
                }
              }
              $lst[] = $stk->stock_code;
            }
          }

          Log::add([
            'type' => 'Add',
            'nik' => $r->nik,
            'description' => 'Import Stock dari Excel Untuk stock : '.implode(', ', $lst)
          ]);
        }
        return response()->json(Api::response(true, 'sukses'),200);
    }

    public function report_get(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'stock.stock.stock_code',
            'master.master_stock.stock_name',
            'master.master_stock.stock_size',
            'master.master_stock.stock_brand',
            'master.master_stock.stock_type',
            'master.master_stock.stock_color'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'stock_name'
            );
        
        // date filter
        if(isset($input['query']['start_date'])) $start_date = Converter::fromID($input['query']['start_date']);
        if(isset($input['query']['end_date'])){
            $end_date = Converter::fromID($input['query']['end_date']);
            if(!isset($start_date))
                $start_date = $end_date;
        }
        if(!isset($end_date) && isset($input['query']['start_date'])) $end_date = $start_date;

        // whole query
        $sup = Stock::selectRaw('master.master_stock.*, stock.stock.main_stock_code,
            (CASE WHEN stock_in.stock_qty IS NOT NULL THEN stock_in.stock_qty ELSE 0 END) AS first_qty,
            (CASE WHEN stock_in_current.stock_qty IS NOT NULL THEN stock_in_current.stock_qty ELSE 0 END) AS in_qty,
            (CASE WHEN stock_out_current.stock_qty IS NOT NULL THEN stock_out_current.stock_qty ELSE 0 END) AS out_qty,
            ((CASE WHEN stock_in.stock_qty IS NOT NULL THEN stock_in.stock_qty ELSE 0 END + CASE WHEN stock_in_current.stock_qty IS NOT NULL THEN stock_in_current.stock_qty ELSE 0 END) - CASE WHEN stock_out_current.stock_qty IS NOT NULL THEN stock_out_current.stock_qty ELSE 0 END) AS current_qty,
            master.master_measure.measure_type
        ')
        ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
        ->join('master.master_measure', 'master.master_measure.measure_code', '=', 'master.master_stock.measure_code')
        ->leftJoin(DB::raw("(
                SELECT tmp_all_child.main_stock_code, ".(isset($start_date)?"(tmp_all_child.stock_qty - CASE WHEN tmp_all_diff.stock_qty IS NOT NULL THEN tmp_all_diff.stock_qty ELSE 0 END)":"CAST(0 AS DECIMAL(20,2))")." AS stock_qty FROM(
                    SELECT main_stock_code, sum(stock_qty) AS stock_qty FROM (
                        SELECT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty ".(isset($start_date)?"WHERE stock_date < '".$start_date." 00:00:00'":"")." GROUP BY main_stock_code
                        UNION ALL
                        SELECT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty_out ".(isset($start_date)?"WHERE stock_date < '".$start_date." 00:00:00'":"")." GROUP BY main_stock_code
                    ) AS tmp_all_children GROUP BY main_stock_code
                ) AS tmp_all_child
                LEFT JOIN (
                    SELECT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty_out ".(isset($start_date)?"WHERE stock_date < '".$start_date." 00:00:00' AND stock_out_date < '".$start_date." 00:00:00'":"")." GROUP BY main_stock_code
                ) AS tmp_all_diff ON tmp_all_diff.main_stock_code = tmp_all_child.main_stock_code
            ) AS stock_in"), function($on) use($input){
            $on->on('stock_in.main_stock_code', '=', 'stock.stock.main_stock_code');
        })
        ->leftJoin(DB::raw("(SELECT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty_out ".(isset($start_date)?"WHERE stock_out_date < '".$start_date." 00:00:00'":"")." GROUP BY main_stock_code) AS stock_out"), function($on) use($input){
            $on->on('stock_out.main_stock_code', '=', 'stock.stock.main_stock_code');
        })
        ->leftJoin(DB::raw("(SELECT main_stock_code, SUM(stock_qty) AS stock_qty FROM (
            SELECT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty ".(isset($start_date)?"WHERE stock_date BETWEEN '".$start_date." 00:00:00' AND '".$end_date." 23:59:59'":"")." GROUP BY main_stock_code
            UNION ALL
            SELECT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty_out ".(isset($start_date)?"WHERE stock_date BETWEEN '".$start_date." 00:00:00' AND '".$end_date." 23:59:59'":"")." GROUP BY main_stock_code
            ) AS tmp_in_table GROUP BY main_stock_code
        ) AS stock_in_current"), function($on) use($input){
            $on->on('stock_in_current.main_stock_code', '=', 'stock.stock.main_stock_code');
        })
        ->leftJoin(DB::raw("(SELECT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty_out ".(isset($start_date)?"WHERE stock_out_date BETWEEN '".$start_date." 00:00:00' AND '".$end_date." 23:59:59'":"")." GROUP BY main_stock_code) AS stock_out_current"), function($on) use($input){
            $on->on('stock_out_current.main_stock_code', '=', 'stock.stock.main_stock_code');
        })
        ->where(['stock.stock.page_code' => $input['page_code']]);

        if(isset($start_date)){
            $sup->whereRaw(DB::raw("
                stock.stock.main_stock_code IN(
                    SELECT main_stock_code FROM (
                        SELECT main_stock_code FROM stock.qty WHERE stock_date BETWEEN '".$start_date." 00:00:00' AND '".$end_date." 23:59:59' GROUP BY main_stock_code
                        UNION ALL
                        SELECT main_stock_code FROM stock.qty_out WHERE stock_out_date BETWEEN '".$start_date." 00:00:00' AND '".$end_date." 23:59:59' GROUP BY main_stock_code
                    ) AS tmp_table GROUP BY main_stock_code
                )
            "));
        }

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('measure_code','stock_brand','stock_size','stock_type','stock_color')))
                        $sup->where("master.master_stock.".$field,($val=="null"?NULL:urldecode($val)));
                    else if($field == 'find'){
                        if(!empty($val)){
                            $sup->where(function($sup) use($column_search,$val){
                                foreach($column_search as $row)
                                    $sup->orWhere($row,'like',(in_array($row,['master.master_stock.stock_name'])?"":"%").$val."%");
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

    public function report_grid(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'stock.stock.stock_code',
            'master.master_stock.stock_name',
            'master.master_stock.stock_size',
            'master.master_stock.stock_brand',
            'master.master_stock.stock_type',
            'master.master_stock.stock_color'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'stock_name'
            );
        
        // date filter
        if(isset($input['query']['start_date'])) $start_date = Converter::fromID($input['query']['start_date']);
        if(isset($input['query']['end_date'])){
            $end_date = Converter::fromID($input['query']['end_date']);
            if(!isset($start_date))
                $start_date = $end_date;
        }
        if(!isset($end_date) && isset($input['query']['start_date'])) $end_date = $start_date;

        // whole query
        $sup = Stock::selectRaw('master.master_stock.*, stock.stock.main_stock_code,
            (CASE WHEN stock_in.stock_qty IS NOT NULL THEN stock_in.stock_qty ELSE 0 END) AS first_qty,
            (CASE WHEN stock_in_current.stock_qty IS NOT NULL THEN stock_in_current.stock_qty ELSE 0 END) AS in_qty,
            (CASE WHEN stock_out_current.stock_qty IS NOT NULL THEN stock_out_current.stock_qty ELSE 0 END) AS out_qty,
            ((CASE WHEN stock_in.stock_qty IS NOT NULL THEN stock_in.stock_qty ELSE 0 END + CASE WHEN stock_in_current.stock_qty IS NOT NULL THEN stock_in_current.stock_qty ELSE 0 END) - CASE WHEN stock_out_current.stock_qty IS NOT NULL THEN stock_out_current.stock_qty ELSE 0 END) AS current_qty,
            master.master_measure.measure_type
        ')
        ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
        ->join('master.master_measure', 'master.master_measure.measure_code', '=', 'master.master_stock.measure_code')
        ->leftJoin(DB::raw("(
                SELECT tmp_all_child.main_stock_code, ".(isset($start_date)?"(tmp_all_child.stock_qty - CASE WHEN tmp_all_diff.stock_qty IS NOT NULL THEN tmp_all_diff.stock_qty ELSE 0 END)":"CAST(0 AS DECIMAL(20,2))")." AS stock_qty FROM(
                    SELECT main_stock_code, sum(stock_qty) AS stock_qty FROM (
                        SELECT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty ".(isset($start_date)?"WHERE stock_date < '".$start_date." 00:00:00'":"")." GROUP BY main_stock_code
                        UNION ALL
                        SELECT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty_out ".(isset($start_date)?"WHERE stock_date < '".$start_date." 00:00:00'":"")." GROUP BY main_stock_code
                    ) AS tmp_all_children GROUP BY main_stock_code
                ) AS tmp_all_child
                LEFT JOIN (
                    SELECT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty_out ".(isset($start_date)?"WHERE stock_date < '".$start_date." 00:00:00' AND stock_out_date < '".$start_date." 00:00:00'":"")." GROUP BY main_stock_code
                ) AS tmp_all_diff ON tmp_all_diff.main_stock_code = tmp_all_child.main_stock_code
            ) AS stock_in"), function($on) use($input){
            $on->on('stock_in.main_stock_code', '=', 'stock.stock.main_stock_code');
        })
        ->leftJoin(DB::raw("(SELECT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty_out ".(isset($start_date)?"WHERE stock_out_date < '".$start_date." 00:00:00'":"")." GROUP BY main_stock_code) AS stock_out"), function($on) use($input){
            $on->on('stock_out.main_stock_code', '=', 'stock.stock.main_stock_code');
        })
        ->leftJoin(DB::raw("(SELECT main_stock_code, SUM(stock_qty) AS stock_qty FROM (
            SELECT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty ".(isset($start_date)?"WHERE stock_date BETWEEN '".$start_date." 00:00:00' AND '".$end_date." 23:59:59'":"")." GROUP BY main_stock_code
            UNION ALL
            SELECT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty_out ".(isset($start_date)?"WHERE stock_date BETWEEN '".$start_date." 00:00:00' AND '".$end_date." 23:59:59'":"")." GROUP BY main_stock_code
            ) AS tmp_in_table GROUP BY main_stock_code
        ) AS stock_in_current"), function($on) use($input){
            $on->on('stock_in_current.main_stock_code', '=', 'stock.stock.main_stock_code');
        })
        ->leftJoin(DB::raw("(SELECT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty_out ".(isset($start_date)?"WHERE stock_out_date BETWEEN '".$start_date." 00:00:00' AND '".$end_date." 23:59:59'":"")." GROUP BY main_stock_code) AS stock_out_current"), function($on) use($input){
            $on->on('stock_out_current.main_stock_code', '=', 'stock.stock.main_stock_code');
        })
        ->where(['stock.stock.page_code' => $input['page_code']]);

        if(isset($start_date)){
            $sup->whereRaw(DB::raw("
                stock.stock.main_stock_code IN(
                    SELECT main_stock_code FROM (
                        SELECT main_stock_code FROM stock.qty WHERE stock_date BETWEEN '".$start_date." 00:00:00' AND '".$end_date." 23:59:59' GROUP BY main_stock_code
                        UNION ALL
                        SELECT main_stock_code FROM stock.qty_out WHERE stock_out_date BETWEEN '".$start_date." 00:00:00' AND '".$end_date." 23:59:59' GROUP BY main_stock_code
                    ) AS tmp_table GROUP BY main_stock_code
                )
            "));
        }
        // dd($sup->toSql());

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('measure_code','stock_brand','stock_size','stock_type','stock_color')))
                        $sup->where("master.master_stock.".$field,($val=="null"?NULL:urldecode($val)));
                    else if($field == 'find'){
                        if(!empty($val)){
                            $sup->where(function($sup) use($column_search,$val){
                                foreach($column_search as $row)
                                    $sup->orWhere($row,'like',(in_array($row,['master.master_stock.stock_name'])?"":"%").$val."%");
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

    public function detail_report_get(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'master.master_stock.stock_code',
            'master.master_stock.stock_name',
            'master.master_stock.stock_size',
            'master.master_stock.stock_brand',
            'master.master_stock.stock_type',
            'master.master_stock.stock_color'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'stock_name'
            );
        
        // date filter
        if(isset($input['query']['start_date'])) $start_date = $input['query']['start_date'];
        if(isset($input['query']['end_date'])){
            $end_date = $input['query']['end_date'];
            if(!isset($start_date))
                $start_date = $end_date;
        }
        if(!isset($end_date) && isset($input['query']['start_date'])) $end_date = $start_date;

        // whole query
        $sup = Stock::selectRaw('master.master_stock.*, 
            stock_detail.*,
            master.master_measure.measure_type
        ')
        ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
        ->join('master.master_measure', 'master.master_measure.measure_code', '=', 'master.master_stock.measure_code')
        ->join(DB::raw("(SELECT 
                stock_date,
                main_stock_code,
                CASE WHEN first_qty IS NOT NULL THEN first_qty ELSE 0 END AS first_qty,
                qty_in,
                qty_out,
                ((CASE WHEN first_qty IS NOT NULL THEN first_qty ELSE 0 END + qty_in) - qty_out) AS current_qty
            FROM (
                SELECT main_stock_code, stock_date, (
                    SELECT SUM(qty_in)-SUM(qty_out) FROM(
                        SELECT main_stock_code, stock_date, SUM(first_qty) AS first_qty, SUM(qty_in) AS qty_in, SUM(qty_out) AS qty_out FROM (
                            SELECT main_stock_code, CAST(stock_date AS DATE) AS stock_date, 0 AS first_qty, SUM(qty) AS qty_in, 0 AS qty_out FROM stock.qty GROUP BY main_stock_code, CAST(stock_date AS DATE)
                            UNION ALL
                            SELECT main_stock_code, CAST(stock_date AS DATE) AS stock_date, 0 AS first_qty, SUM(qty) AS qty_in, 0 AS qty_out FROM stock.qty_out GROUP BY main_stock_code, CAST(stock_date AS DATE)
                            UNION ALL
                            SELECT main_stock_code, CAST(stock_out_date AS DATE) AS stock_date, 0 AS first_qty, 0 AS qty_in, SUM(qty) AS qty_out FROM stock.qty_out GROUP BY main_stock_code, CAST(stock_out_date AS DATE)
                        ) AS tmp_in_out_detail GROUP BY main_stock_code, stock_date
                    ) AS tmp_in_out WHERE stock_date < stock_in_out.stock_date AND main_stock_code = stock_in_out.main_stock_code
                ) AS first_qty, qty_in, qty_out FROM (
                    SELECT main_stock_code, stock_date, SUM(first_qty) AS first_qty, SUM(qty_in) AS qty_in, SUM(qty_out) AS qty_out FROM (
                        SELECT main_stock_code, CAST(stock_date AS DATE) AS stock_date, 0 AS first_qty, SUM(qty) AS qty_in, 0 AS qty_out FROM stock.qty GROUP BY main_stock_code, CAST(stock_date AS DATE)
                        UNION ALL
                        SELECT main_stock_code, CAST(stock_date AS DATE) AS stock_date, 0 AS first_qty, SUM(qty) AS qty_in, 0 AS qty_out FROM stock.qty_out GROUP BY main_stock_code, CAST(stock_date AS DATE)
                        UNION ALL
                        SELECT main_stock_code, CAST(stock_out_date AS DATE) AS stock_date, 0 AS first_qty, 0 AS qty_in, SUM(qty) AS qty_out FROM stock.qty_out GROUP BY main_stock_code, CAST(stock_out_date AS DATE)
                    ) AS stock_in GROUP BY main_stock_code, stock_date
                ) AS stock_in_out
        ) AS tmp_detail) AS stock_detail"), function($on) use($input){
            $on->on('stock_detail.main_stock_code', '=', 'stock.stock.main_stock_code');
        })
        ->where([
            'stock.stock.page_code' => $input['page_code'],
            'stock.stock.main_stock_code' => $input['main_stock_code']
        ]);

        if(isset($start_date)){
            $sup->whereRaw(DB::raw("
                stock_detail.stock_date BETWEEN '".$start_date."' AND '".$end_date."'
            "));
        }
        // dd($sup->toSql());

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('measure_code','stock_brand','stock_size','stock_type','stock_color')))
                        $sup->where("master.master_stock.".$field,($val=="null"?NULL:urldecode($val)));
                    else if($field == 'find'){
                        if(!empty($val)){
                            $sup->where(function($sup) use($column_search,$val){
                                foreach($column_search as $row)
                                    $sup->orWhere($row,'like',(in_array($row,['master.master_stock.stock_name'])?"":"%").$val."%");
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

    public function detail_report_grid(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'master.master_stock.stock_code',
            'master.master_stock.stock_name',
            'master.master_stock.stock_size',
            'master.master_stock.stock_brand',
            'master.master_stock.stock_type',
            'master.master_stock.stock_color'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'stock_name'
            );
        
        // date filter
        if(isset($input['query']['start_date'])) $start_date = $input['query']['start_date'];
        if(isset($input['query']['end_date'])){
            $end_date = $input['query']['end_date'];
            if(!isset($start_date))
                $start_date = $end_date;
        }
        if(!isset($end_date) && isset($input['query']['start_date'])) $end_date = $start_date;

        // whole query
        $sup = Stock::selectRaw('master.master_stock.*, 
            stock_detail.*,
            master.master_measure.measure_type
        ')
        ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
        ->join('master.master_measure', 'master.master_measure.measure_code', '=', 'master.master_stock.measure_code')
        ->join(DB::raw("(SELECT 
                stock_date,
                main_stock_code,
                CASE WHEN first_qty IS NOT NULL THEN first_qty ELSE 0 END AS first_qty,
                qty_in,
                qty_out,
                ((CASE WHEN first_qty IS NOT NULL THEN first_qty ELSE 0 END + qty_in) - qty_out) AS current_qty
            FROM (
                SELECT main_stock_code, stock_date, (
                    SELECT SUM(qty_in)-SUM(qty_out) FROM(
                        SELECT main_stock_code, stock_date, SUM(first_qty) AS first_qty, SUM(qty_in) AS qty_in, SUM(qty_out) AS qty_out FROM (
                            SELECT main_stock_code, CAST(stock_date AS DATE) AS stock_date, 0 AS first_qty, SUM(qty) AS qty_in, 0 AS qty_out FROM stock.qty GROUP BY main_stock_code, CAST(stock_date AS DATE)
                            UNION ALL
                            SELECT main_stock_code, CAST(stock_date AS DATE) AS stock_date, 0 AS first_qty, SUM(qty) AS qty_in, 0 AS qty_out FROM stock.qty_out GROUP BY main_stock_code, CAST(stock_date AS DATE)
                            UNION ALL
                            SELECT main_stock_code, CAST(stock_out_date AS DATE) AS stock_date, 0 AS first_qty, 0 AS qty_in, SUM(qty) AS qty_out FROM stock.qty_out GROUP BY main_stock_code, CAST(stock_out_date AS DATE)
                        ) AS tmp_in_out_detail GROUP BY main_stock_code, stock_date
                    ) AS tmp_in_out WHERE stock_date < stock_in_out.stock_date AND main_stock_code = stock_in_out.main_stock_code
                ) AS first_qty, qty_in, qty_out FROM (
                    SELECT main_stock_code, stock_date, SUM(first_qty) AS first_qty, SUM(qty_in) AS qty_in, SUM(qty_out) AS qty_out FROM (
                        SELECT main_stock_code, CAST(stock_date AS DATE) AS stock_date, 0 AS first_qty, SUM(qty) AS qty_in, 0 AS qty_out FROM stock.qty GROUP BY main_stock_code, CAST(stock_date AS DATE)
                        UNION ALL
                        SELECT main_stock_code, CAST(stock_date AS DATE) AS stock_date, 0 AS first_qty, SUM(qty) AS qty_in, 0 AS qty_out FROM stock.qty_out GROUP BY main_stock_code, CAST(stock_date AS DATE)
                        UNION ALL
                        SELECT main_stock_code, CAST(stock_out_date AS DATE) AS stock_date, 0 AS first_qty, 0 AS qty_in, SUM(qty) AS qty_out FROM stock.qty_out GROUP BY main_stock_code, CAST(stock_out_date AS DATE)
                    ) AS stock_in GROUP BY main_stock_code, stock_date
                ) AS stock_in_out
        ) AS tmp_detail) AS stock_detail"), function($on) use($input){
            $on->on('stock_detail.main_stock_code', '=', 'stock.stock.main_stock_code');
        })
        ->where([
            'stock.stock.page_code' => $input['page_code'],
            'stock.stock.main_stock_code' => $input['main_stock_code']
        ]);

        if(isset($start_date)){
            $sup->whereRaw(DB::raw("
                stock_detail.stock_date BETWEEN '".$start_date."' AND '".$end_date."'
            "));
        }
        // dd($sup->toSql());

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('measure_code','stock_brand','stock_size','stock_type','stock_color')))
                        $sup->where("master.master_stock.".$field,($val=="null"?NULL:urldecode($val)));
                    else if($field == 'find'){
                        if(!empty($val)){
                            $sup->where(function($sup) use($column_search,$val){
                                foreach($column_search as $row)
                                    $sup->orWhere($row,'like',(in_array($row,['master.master_stock.stock_name'])?"":"%").$val."%");
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
