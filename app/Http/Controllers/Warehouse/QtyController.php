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
use App\Helpers\{Api, Log};
use Illuminate\Http\Request;



class QtyController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    public function _check_qty($qty,$stk_code,$page_code){
        $stock = Stock::selectRaw('CAST((SELECT SUM(qty) FROM stock.qty WHERE main_stock_code=stock.stock.main_stock_code) as DECIMAL) as qty')
                ->where(['stock.stock.stock_code'=>$stk_code, 'page_code' => $page_code])->first();

        $waiting = ReqTools::selectRaw('CAST(SUM(req_tools_qty) as DECIMAL) as qty')
        ->join('document.request_tools_detail', 'document.request_tools_detail.req_tools_code', '=', 'document.request_tools.req_tools_code')
        ->where(['document.request_tools_detail.stock_code'=>$stk_code, 'page_code' => $page_code,'status' => 'ST02'])
        ->groupBy(['document.request_tools_detail.stock_code']);

        return $qty<($stock->qty-($waiting->count()>0?$waiting->first()->qty:0));
    }

    public function add(Request $r){
        $date = date("Y-m-d H:i:s");
        $stk = Stock::where(['main_stock_code' => $r->main_stock_code])->first();

        $qty = $r->stock_qty;

        $chk = (Qty::where(['main_stock_code' => $r->main_stock_code])->count() > 0);
        if($chk){
          if($qty < 0){
            $qQty = new Qty;
            $qQty->main_stock_code = $r->main_stock_code;
            $qQty->supplier_code = NULL;
            $qQty->qty = 0-$qty;
            $qQty->stock_price = 0;
            $qQty->stock_notes = "Stok opname (".$date.")";
            $qQty->stock_date = $date;
            $qQty->nik = $r->nik;
            $qQty->do_code = NULL;
            if($qQty->save()){
              // check status in request tools status to fullfill request if stock available
              // get stock code by main_stock_code
              $stk = Stock::selectRaw('stock.stock.main_stock_code, stock.stock.stock_code, stock.stock.page_code, CASE WHEN SUM(stock.qty.qty) <> NULL THEN SUM(stock.qty.qty) ELSE 0 END as qty')
                ->leftJoin('stock.qty', 'stock.qty.main_stock_code', '=', 'stock.stock.main_stock_code')
                ->where(['stock.stock.main_stock_code' => $r->main_stock_code])
                ->groupBy(['stock.stock.main_stock_code', 'stock.stock.stock_code', 'stock.stock.page_code'])->first();
              // get outstanding fullfillment
              $rtd = ReqToolsDetail::selectRaw('document.request_tools_detail.req_tools_code, document.request_tools_detail.stock_code, document.request_tools_detail.req_tools_qty')
                ->join('document.request_tools','document.request_tools_detail.req_tools_code', '=', 'document.request_tools.req_tools_code')
                ->where([
                  'document.request_tools_detail.stock_code' => $r->stock_code,
                  'document.request_tools.page_code' => $r->page_code,
                  'document.request_tools_detail.fullfillment' => 0
                ])->get();

              if($rtd->count() > 0){
                $stock_qty = $stk->qty;
                // process update status for waiting list fullfillment in request tools
                foreach($rtd AS $i => $row){
                  if($this->_check_qty($row->req_tools_qty, $stk->stock_code, $stk->page_code)){
                    ReqToolsDetail::where([
                      'req_tools_code' => $row->req_tools_code,
                      'stock_code' => $row->stock_code
                    ])->update(['fullfillment' => 1]);
                    $stock_qty -= $row->req_tools_qty;
                    // update status to process if all stock are fullfillment
                    $cnt = ReqToolsDetail::where([
                      'req_tools_code' => $row->req_tools_code,
                      'fullfillment' => 0
                    ])->count();
                    // updating to process in request tools
                    if($cnt == 0){
                      ReqTools::where([
                        'req_tools_code' => $row->req_tools_code
                      ])->update(['status' => 'ST02']);
                    }
                  }
                }
              }
              Log::add([
                'type' => 'Add',
                'nik' => $r->nik,
                'description' => 'Menambah Kuantiti Stok : '.$r->stock_code
              ]);
            }
          } else{
            DB::select(DB::raw("EXEC stock.stock_out @stcode='".$r->stock_code."', @qty='".$qty."',@nik='".$r->nik."', @notes='Stock Opname (".$date.")', @page='".$r->page_code."' "));

            Log::add([
              'type' => 'Edit',
              'nik' => $r->nik,
              'description' => 'Opname Stok : '.$r->stock_code
            ]);
          }
        } else {
          $qQty = new Qty;
          $qQty->main_stock_code = $r->main_stock_code;
          $qQty->supplier_code = NULL;
          $qQty->qty = $qty;
          $qQty->stock_price = 0;
          $qQty->stock_notes = "Stok Awal (".$date.")";
          $qQty->stock_date = $date;
          $qQty->nik = $r->nik;
          $qQty->do_code = NULL;
          if($qQty->save()){
            // check status in request tools status to fullfill request if stock available
            // get stock code by main_stock_code
            $stk = Stock::selectRaw('stock.stock.main_stock_code, stock.stock.stock_code, stock.stock.page_code, CASE WHEN SUM(stock.qty.qty) <> NULL THEN SUM(stock.qty.qty) ELSE 0 END as qty')
              ->leftJoin('stock.qty', 'stock.qty.main_stock_code', '=', 'stock.stock.main_stock_code')
              ->where(['stock.stock.main_stock_code' => $r->main_stock_code])
              ->groupBy(['stock.stock.main_stock_code', 'stock.stock.stock_code', 'stock.stock.page_code'])->first();
            // get outstanding fullfillment
            $rtd = ReqToolsDetail::selectRaw('document.request_tools_detail.req_tools_code, document.request_tools_detail.stock_code, document.request_tools_detail.req_tools_qty')
              ->join('document.request_tools','document.request_tools_detail.req_tools_code', '=', 'document.request_tools.req_tools_code')
              ->where([
                'document.request_tools_detail.stock_code' => $r->stock_code,
                'document.request_tools.page_code' => $r->page_code,
                'document.request_tools_detail.fullfillment' => 0
              ])->get();

            if($rtd->count() > 0){
              $stock_qty = $stk->qty;
              // process update status for waiting list fullfillment in request tools
              foreach($rtd AS $i => $row){
                if($this->_check_qty($row->req_tools_qty, $stk->stock_code, $stk->page_code)){
                  ReqToolsDetail::where([
                    'req_tools_code' => $row->req_tools_code,
                    'stock_code' => $row->stock_code
                  ])->update(['fullfillment' => 1]);
                  $stock_qty -= $row->req_tools_qty;
                  // update status to process if all stock are fullfillment
                  $cnt = ReqToolsDetail::where([
                    'req_tools_code' => $row->req_tools_code,
                    'fullfillment' => 0
                  ])->count();
                  // updating to process in request tools
                  if($cnt == 0){
                    ReqTools::where([
                      'req_tools_code' => $row->req_tools_code
                    ])->update(['status' => 'ST02']);
                  }
                }
              }
            }

            Log::add([
              'type' => 'Edit',
              'nik' => $r->nik,
              'description' => 'Menambah Kuantiti Stok : '.$r->stock_code
            ]);
          }
        }
        return response()->json(Api::response(true,'Berhasil'),200);
    }

    public function grid_in(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'stock.qty.stock_notes',
            'master.master_stock.stock_name',
            'master.master_stock.stock_size',
            'master.master_stock.stock_brand',
            'master.master_stock.stock_type',
            'master.master_stock.stock_color',
            'master.master_measure.measure_type',
            'master.master_stock.stock_min_qty',
            'master.master_supplier.supplier_name'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'stock_name'
            );

        // whole query
        $sup = Qty::selectRaw('stock.qty.stock_notes, stock.qty.nik, stock.qty.stock_date, master.master_supplier.supplier_name, stock.qty.main_stock_code, master.master_stock.*, master.master_measure.measure_type, (stock.qty.qty + CASE WHEN (
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
                ) ELSE 0 END) AS stock_qty')
        ->join('stock.stock', 'stock.stock.main_stock_code', '=', 'stock.qty.main_stock_code')
        ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
        ->join('master.master_measure', 'master.master_measure.measure_code', '=', 'master.master_stock.measure_code')
        ->leftJoin('master.master_supplier', 'master.master_supplier.supplier_code', '=', 'stock.qty.supplier_code')
        ->where(['stock.stock.page_code' => $input['page_code'], 'stock.stock.main_stock_code' => $input['main_stock_code']]);

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

    public function grid_out(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'stock.qty_out.nik',
            'stock.qty_out.stock_notes',
            'master.master_stock.stock_name',
            'master.master_stock.stock_size',
            'master.master_stock.stock_brand',
            'master.master_stock.stock_type',
            'master.master_stock.stock_color',
            'master.master_measure.measure_type',
            'stock.qty_out.qty',
            'master.master_stock.stock_min_qty',
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
        ->where(['stock.stock.page_code' => $input['page_code'], 'stock.stock.main_stock_code' => $input['main_stock_code']]);

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('measure_code','stock_brand','stock_daily_use'))){
                      if(!empty($val) && !is_null($val))
                        $sup->where("master.master_stock.".$field,($val=="null"?NULL:$val));
                    }
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


    // for import data stock from excel
    public function import(Request $r){
        $date = date("Y-m-d H:i:s");
        if($r->has('data')){
          $lst = [];
          foreach ($r->data as $i => $row) {
            $stk = null;
            if(!empty($row['stock_code']) && !is_null($row['stock_code'])){
              $query = Stock::where(['stock_code' => $row['stock_code'], 'page_code' => $r->page_code]);
              if($query->count() > 0){
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

                  // append list stock_code
                  $lst[] = $row['stock_code'];
                }
              }
            }
          }
          // add log
          Log::add([
            'type' => 'Add',
            'nik' => $r->nik,
            'description' => 'Import Kuantiti dari Excel Untuk stock : '.implode(', ', $lst)
          ]);
        }
        return response()->json(Api::response(true, 'sukses'),200);
    }
}
