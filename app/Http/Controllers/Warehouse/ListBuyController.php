<?php

namespace App\Http\Controllers\Warehouse;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Stock\StockModel AS Stock;

// Embed a Helper
use DB;
use App\Helpers\Api;
use Illuminate\Http\Request;



class ListBuyController extends Controller
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
        $sup = Stock::selectRaw("stock.stock.main_stock_code, master.master_stock.*, master.master_measure.measure_type, qty.stock_qty, cabinet.cabinet_name, (SELECT TOP 1 document.purchase_order.po_code FROM document.purchase_order JOIN document.purchase_order_detail ON document.purchase_order.po_code=document.purchase_order_detail.po_code WHERE document.purchase_order.status = 'ST06' AND document.purchase_order_detail.main_stock_code=stock.stock.main_stock_code ORDER BY document.purchase_order.po_code ASC) AS po_code")
        ->join('master.master_stock','master.master_stock.stock_code','=','stock.stock.stock_code')
        ->join('master.master_measure','master.master_measure.measure_code','=','master.master_stock.measure_code')
        ->leftJoin(DB::raw("(SELECT main_stock_code, cabinet_name FROM stock.cabinet LEFT JOIN master.master_cabinet ON master.master_cabinet.cabinet_code = stock.cabinet.cabinet_code WHERE master.master_cabinet.menu_page = '".$input['menu_page']."') AS cabinet"),'cabinet.main_stock_code','=','stock.stock.main_stock_code')
        ->leftJoin(DB::raw("(SELECT DISTINCT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty GROUP BY main_stock_code ) AS qty"),'qty.main_stock_code','=','stock.stock.main_stock_code')
        ->where(['stock.stock.menu_page' => $input['menu_page']])
        ->where(function($sup){
          $sup->whereRaw(DB::raw('(CASE WHEN [qty].[stock_qty] IS NULL THEN 0 ELSE [qty].[stock_qty] END) <= [master].[master_stock].[stock_min_qty]'));
          $sup->orWhereRaw(DB::raw("(SELECT COUNT(stock_code) AS cnt FROM document.request_tools_detail WHERE stock_code = master.master_stock.stock_code AND fullfillment = 0 GROUP BY stock_code) > 0 "));
        });

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
