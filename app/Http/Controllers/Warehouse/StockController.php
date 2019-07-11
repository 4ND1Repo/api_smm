<?php

namespace App\Http\Controllers\Warehouse;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Master\StockModel AS MasterStock;
use App\Model\Stock\StockModel AS Stock;
use App\Model\Stock\QtyModel AS Qty;
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

    private function _generate_master_prefix(){
        $prefix = "STK";
        $SP = MasterStock::select('stock_code')->orderBy('stock_code', 'DESC')->get();
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
        ->join(DB::raw("(SELECT DISTINCT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty GROUP BY main_stock_code ) AS qty"),'qty.main_stock_code','=','stock.stock.main_stock_code')
        ->where('stock.stock.main_stock_code',$id)
        ->first();
        return Api::response(true,"Sukses",$data);
    }

    public function add_master($r){
        $stock = new MasterStock;
        $stock->stock_code = $this->_generate_master_prefix();
        $stock->stock_name = $r->input('stock_name');
        $stock->stock_size = $r->input('stock_size');
        $stock->stock_brand = $r->input('stock_brand');
        $stock->stock_type = $r->input('stock_type');
        $stock->stock_color = $r->input('stock_color');
        $stock->measure_code = $r->input('measure_code');
        $stock->stock_price = $r->input('stock_price')?$r->input('stock_price'):0;
        $stock->stock_deliver_price = $r->input('stock_deliver_price')?$r->input('stock_deliver_price'):0;
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
            }

            $qty = Qty::firstOrNew(['main_stock_code'=>$stk->main_stock_code]);
            if(! $qty->exists){
                $qty->main_stock_code = $stk->main_stock_code;
                $qty->qty = !is_null($r->stock_qty) && !empty($r->stock_qty)?$r->stock_qty:0;
                $qty->nik = $r->nik;
                $qty->stock_notes = "New Stock";
                $qty->save();
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
                'stock_price' => $r->input('stock_price'),
                'stock_deliver_price' => $r->input('stock_deliver_price'),
                'stock_min_qty' => $r->input('stock_min_qty'),
                'stock_max_qty' => $r->input('stock_max_qty'),
                'stock_daily_use' => $r->has('stock_daily_use')?1:0
            ]);
            $qty = Qty::selectRaw('CAST(SUM(qty) AS DECIMAL(20,2)) as stock_qty, stock.stock.main_stock_code')
            ->join('stock.stock', 'stock.stock.main_stock_code', '=', 'stock.qty.main_stock_code')
            ->where(['stock_code' => $r->input('stock_code'), 'menu_page' =>$r->input('menu_page')])->groupBy('stock.stock.main_stock_code')->first();
            if((float)$qty->stock_qty !== (float)$r->input('stock_qty')){

                if((float)$qty->stock_qty > 0){
                    // balancing to 0
                    $qtyNew = new Qty;
                    $qtyNew->main_stock_code = $qty->main_stock_code;
                    $qtyNew->qty = (0-$qty->stock_qty);
                    $qtyNew->stock_notes = 'Edit Stock (Balancing)';
                    $qtyNew->nik = $r->input('nik');
                    $qtyNew->save();
                }

                // New Qty
                $qtyNew = new Qty;
                $qtyNew->main_stock_code = $qty->main_stock_code;
                $qtyNew->qty = $r->input('stock_qty');
                $qtyNew->stock_notes = 'Edit Stock (New Qty)';
                $qtyNew->nik = $r->input('nik');
                $qtyNew->save();


                // change status in waiting list
                $sts = DB::select(DB::raw("SELECT stock_code, sum(stock_qty) as qty FROM (
                    SELECT stock.stock.stock_code, SUM(qty) as stock_qty FROM stock.qty
                    INNER JOIN stock.stock ON stock.stock.main_stock_code = stock.qty.main_stock_code
                    WHERE stock.stock.menu_page='".$r->menu_page."'
                    GROUP BY stock_code
                    UNION
                    SELECT stock_code, (0-req_tools_qty) as stock_qty FROM document.request_tools_detail
                    INNER JOIN document.request_tools ON document.request_tools_detail.req_tools_code = document.request_tools.req_tools_code 
                    WHERE fullfillment = 1 AND document.request_tools.menu_page='".$r->menu_page."' AND document.request_tools_detail.finish_by IS NULL
                ) AS stock_table WHERE stock_code='".$r->stock_code."' GROUP BY stock_code"));
                if(count($sts) > 0){

                    // revert waiting list
                    if($sts[0]->qty < 0){
                        $last_qty = DB::select(DB::raw("SELECT stock_code, sum(stock_qty) as qty FROM (
                            SELECT stock.stock.stock_code, SUM(qty) as stock_qty FROM stock.qty
                            INNER JOIN stock.stock ON stock.stock.main_stock_code = stock.qty.main_stock_code
                            WHERE stock.stock.menu_page='".$r->menu_page."'
                            GROUP BY stock_code
                        ) AS stock_table WHERE stock_code='".$r->stock_code."' GROUP BY stock_code"))[0]->qty;

                        $waiting_list = DB::select(DB::raw("SELECT document.request_tools_detail.req_tools_code, stock_code, req_tools_qty as stock_qty FROM document.request_tools_detail
                            INNER JOIN document.request_tools ON document.request_tools_detail.req_tools_code = document.request_tools.req_tools_code 
                            WHERE fullfillment = 1 AND stock_code='".$r->stock_code."' AND document.request_tools.menu_page='".$r->menu_page."' AND document.request_tools_detail.finish_by IS NULL ORDER BY document.request_tools.create_date ASC"));
                        if(count($waiting_list) > 0){
                            foreach($waiting_list AS $row){
                                if(($last_qty - $row->stock_qty) < 0){
                                    ReqToolsDetail::where([
                                        'req_tools_code' => $row->req_tools_code,
                                        'stock_code' => $row->stock_code
                                    ])->update(['fullfillment' => 0]);
                                }
                                $last_qty -= $row->stock_qty;
                            }
                        }
                    } else {
                        $waiting_list = DB::select(DB::raw("SELECT document.request_tools_detail.req_tools_code, stock_code, req_tools_qty as stock_qty FROM document.request_tools_detail
                            INNER JOIN document.request_tools ON document.request_tools_detail.req_tools_code = document.request_tools.req_tools_code 
                            WHERE fullfillment = 0 AND stock_code='".$r->stock_code."' AND document.request_tools.menu_page='".$r->menu_page."'
                            ORDER BY document.request_tools.create_date ASC"));
                        if(count($waiting_list) > 0){
                            foreach($waiting_list AS $row){
                                if(($sts[0]->qty - $row->stock_qty) >= 0){
                                    ReqToolsDetail::where([
                                        'req_tools_code' => $row->req_tools_code,
                                        'stock_code' => $row->stock_code
                                    ])->update(['fullfillment' => 1]);
                                }
                                $sts[0]->qty -= $row->stock_qty;
                                // break process if qty <= 0
                                if($sts[0]->qty <= 0)
                                    break;
                            }
                        }
                    }
                }

                // sync status for master waiting list
                    $sts_wait = DB::select(DB::raw("SELECT document.request_tools.req_tools_code, document.request_tools.status, 
                        SUM(CAST(fullfillment AS INT)) AS fully, 
                        COUNT(document.request_tools_detail.req_tools_code) AS count_stock 
                    FROM document.request_tools_detail
                    INNER JOIN document.request_tools ON document.request_tools_detail.req_tools_code = document.request_tools.req_tools_code 
                    WHERE document.request_tools.menu_page='".$r->menu_page."'
                        AND status IN('ST02','ST03')
                    GROUP BY document.request_tools.req_tools_code, document.request_tools.status, document.request_tools.create_date
                    ORDER BY document.request_tools.create_date ASC"));
                if(count($sts_wait) > 0){
                    foreach($sts_wait as $row){
                        ReqTools::where([
                            'req_tools_code' => $row->req_tools_code
                        ])->update([
                            'status' => ($row->fully == $row->count_stock? "ST02" : "ST03")
                        ]);
                    }
                }
                // end change status
            }
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
            SELECT stock.stock.main_stock_code, CONCAT(master.master_stock.stock_code,' - ',master.master_stock.stock_name,' - ',master.master_stock.stock_type,' - ',master.master_stock.stock_size) as stock_name
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
            'master.master_measure.measure_type',
            'qty.stock_qty',
            'stock_price',
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
        $sup = Stock::selectRaw('stock.stock.main_stock_code, master.master_stock.*, master.master_measure.measure_type, qty.stock_qty')
        ->join('master.master_stock','master.master_stock.stock_code','=','stock.stock.stock_code')
        ->join('master.master_measure','master.master_measure.measure_code','=','master.master_stock.measure_code')
        ->join(DB::raw("(SELECT DISTINCT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty GROUP BY main_stock_code ) AS qty"),'qty.main_stock_code','=','stock.stock.main_stock_code')
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
            'master.master_stock.stock_price',
            'master.master_stock.stock_min_qty',
            'master.master_stock.stock_max_qty'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'stock_name'
            );

        // whole query
        $sup = Qty::selectRaw('stock.qty.stock_notes, stock.qty.nik, stock.qty.stock_date, stock.qty.main_stock_code, master.master_stock.*, master.master_measure.measure_type, stock.qty.qty AS stock_qty')
        ->join('stock.stock', 'stock.stock.main_stock_code', '=', 'stock.qty.main_stock_code')
        ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
        ->join('master.master_measure', 'master.master_measure.measure_code', '=', 'master.master_stock.measure_code')
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