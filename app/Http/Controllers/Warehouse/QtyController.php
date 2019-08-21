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

    public function add(Request $r){
        $date = date("Y-m-d H:i:s");
        $stk = Stock::where(['main_stock_code' => $r->main_stock_code])->first();

        $qty = $r->stock_qty;

        $chk = (Qty::where(['main_stock_code' => $r->main_stock_code])->count() > 0);
        if($chk){
          DB::select(DB::raw("EXEC stock.stock_out @stcode='".$r->stock_code."', @qty='".$qty."',@nik='".$r->nik."', @notes='Stock Opname (".$date.")', @page='".$r->page_code."' "));
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
          }
        }
        return response()->json(Api::response(true,'Berhasil'),200);
    }

}
