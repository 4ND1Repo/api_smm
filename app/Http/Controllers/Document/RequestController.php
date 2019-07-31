<?php

namespace App\Http\Controllers\Document;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Document\RequestToolsModel AS ReqTools;
use App\Model\Document\RequestToolsDetailModel AS ReqToolsDetail;
use App\Model\Document\PoModel AS PO;
use App\Model\Document\PoDetailModel AS PODetail;
use App\Model\Document\DoModel AS DocDO;
use App\Model\Master\StockModel AS MasterStock;
use App\Model\Stock\StockModel AS Stock;
use App\Model\Stock\QtyModel AS Qty;

// Embed a Helper
use DB;
use App\Helpers\Api;
use Illuminate\Http\Request;



class RequestController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    private function _generate_prefix_tools(){
        $prefix = "DRT";
        $SP = ReqTools::select('req_tools_code')->orderBy('req_tools_code', 'DESC')->get();
        if($SP->count() > 0){
            $SP = $SP->first();
            $tmp = explode($prefix, $SP->req_tools_code);
            $count = ((int)$tmp[1])+1;
        } else
            $count = 1;

        return $prefix.sprintf("%07d",$count);
    }

    private function _generate_prefix_po(){
        $prefix = "PO".date("y");
        $SP = PO::select('po_code')->orderBy('po_code', 'DESC')->get();
        if($SP->count() > 0){
            $SP = $SP->first();
            $tmp = explode($prefix, $SP->po_code);
            $count = ((int)$tmp[1])+1;
        } else
            $count = 1;

        return $prefix.sprintf("%07d",$count);
    }

    public function index(){
        return date("Y-m-d H:i:s");
    }

    public function _check_qty($qty,$stk_code,$menu_page){
        $stock = Stock::selectRaw('CAST((SELECT SUM(qty) FROM stock.qty WHERE main_stock_code=stock.stock.main_stock_code) as DECIMAL) as qty')
                ->where(['stock.stock.stock_code'=>$stk_code, 'menu_page' => $menu_page])->first();

        $waiting = ReqTools::selectRaw('CAST(SUM(req_tools_qty) as DECIMAL) as qty')
        ->join('document.request_tools_detail', 'document.request_tools_detail.req_tools_code', '=', 'document.request_tools.req_tools_code')
        ->where(['document.request_tools_detail.stock_code'=>$stk_code, 'menu_page' => $menu_page,'status' => 'ST02'])
        ->groupBy(['document.request_tools_detail.stock_code']);

        return $qty<($stock->qty-($waiting->count()>0?$waiting->first()->qty:0));
    }

    public function find_tools($id){
        $data = [];
        $data['request_tools'] = ReqTools::where('req_tools_code',$id)->first();
        $data['request_tools_detail'] = ReqToolsDetail::selectRaw('master.master_stock.*, master.master_measure.measure_type, document.request_tools_detail.req_tools_code, document.request_tools_detail.req_tools_qty, document.request_tools_detail.finish_by, document.request_tools_detail.fullfillment')
            ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'document.request_tools_detail.stock_code')
            ->join('master.master_measure', 'master.master_measure.measure_code', '=', 'master.master_stock.measure_code')
                ->where('req_tools_code',$id)->get();
        return Api::response(true,"Sukses",$data);
    }

    public function add_tools(Request $r){
        $drt = new ReqTools;
        $drt->req_tools_code = $this->_generate_prefix_tools();
        $drt->create_by = $r->nik;
        $drt->menu_page = $r->menu_page;
        $drt->name_of_request = $r->name_of_request;
        if($r->has('req_nik')){
            if(!empty($r->req_nik) && !is_null($r->req_nik))
                $drt->req_nik = $r->req_nik;
        }
        if($drt->save()){
            foreach($r->items as $stock_code => $qty){
                $drtd = new ReqToolsDetail;
                $drtd->req_tools_code = $drt->req_tools_code;
                $drtd->stock_code = $stock_code;
                $drtd->req_tools_qty = $qty;
                if(!$this->_check_qty($qty,$stock_code,$r->menu_page)){
                    $drt->where(['req_tools_code' => $drt->req_tools_code])->update(['status' => "ST03"]);
                    $drtd->fullfillment = 0;
                }
                $drtd->save();
            }

            return response()->json(Api::response(true,'Sukses'),200);
        }

        return response()->json(Api::response(false,'Gagal menyimpan data'),200);
    }

    public function delete_tools(Request $r){
        // first delete request tools detail
        ReqToolsDetail::where(['req_tools_code' => $r->req_tools_code])->delete();
        // second delete request tools
        ReqTools::where(['req_tools_code' => $r->req_tools_code])->delete();
        // third remove from stock qty
        $qty = Qty::where(['po_code' => $r->req_tools_code])->get();
        if($qty->count() > 0){
            // balancing qty from deleted request tools
            foreach($qty as $row){
                $new_qty = new Qty;
                $new_qty->main_stock_code = $row->main_stock_code;
                $new_qty->qty = abs($row->qty);
                $new_qty->nik = $r->nik;
                $new_qty->po_code = $row->po_code;
                $new_qty->stock_notes = "Hapus dari request barang (".$row->po_code.")";
                $new_qty->save();
            }
            // set null for po_code
            Qty::where(['po_code' => $r->req_tools_code])->update(['po_code' => NULL]);
        }
        return response()->json(Api::response(true,'Sukses'),200);
    }

    public function send_tools(Request $r){
        $data = [];
        $notes = "Pengeluaran Stock";
        // get data sended stock
        $stock = ReqToolsDetail::selectRaw('document.request_tools.req_tools_code, stock_code, menu_page, req_tools_qty, (SELECT TOP 1 main_stock_code FROM stock.stock WHERE stock_code = document.request_tools_detail.stock_code AND menu_page = document.request_tools.menu_page) as main_stock_code')
                ->join('document.request_tools', 'document.request_tools.req_tools_code', '=', 'document.request_tools_detail.req_tools_code')
                ->where(['stock_code' => $r->stock_code, 'document.request_tools.req_tools_code' => $r->req_tools_code])->first();

        $qty = DB::select("EXEC stock.stock_out @stcode='".$r->stock_code."', @qty=".$stock->req_tools_qty.", @nik='".$r->nik."', @page='".$stock->menu_page."', @notes='".$notes."'");
        if(count($qty) > 0){
            // flag finish stock in document.request_tools_detail
            $drtd = ReqToolsDetail::where(['req_tools_code' => $stock->req_tools_code, 'stock_code' => $stock->stock_code])->update(['finish_by' => $r->nik, 'finish_date' => date('Y-m-d H:i:s')]);

            // check if all request was done
            $count = ReqToolsDetail::where(['req_tools_code' => $stock->req_tools_code, 'finish_by' => null])->count();
            if($count == 0){
                ReqTools::where(['req_tools_code'=>$stock->req_tools_code])->update(['status' => 'ST05','finish_by' => $r->nik, 'finish_date' => date('Y-m-d H:i:s')]);
            }
        }
        return Api::response(true,"Sukses",$data);
    }

    public function grid_tools(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'req_tools_code',
            'req_nik',
            'req_tools_date',
            'name_of_request'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'desc',
                'field' => 'req_tools_date'
            );

        // whole query
        $sup = ReqTools::selectRaw('document.request_tools.*, (SELECT count(req_tools_code) FROM document.request_tools_detail WHERE req_tools_code=document.request_tools.req_tools_code) as sum_item, master.master_status.status_label')
        ->join('master.master_status', 'master.master_status.status_code', '=', 'document.request_tools.status')
        ->where(['document.request_tools.menu_page' => $input['menu_page']]);

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('status')))
                        $sup->where("document.request_tools.".$field,($val=="null"?NULL:$val));
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




    // for Purchase order
    public function add_po(Request $r){
        $po = new PO;
        $po->po_code = $this->_generate_prefix_po();
        $po->nik = $r->nik;
        $po->create_by = $r->nik;
        $po->menu_page = $r->menu_page;
        $po->po_date = date("Y-m-d H:i:s");
        $po->menu_page_destination = $r->menu_page_destination;
        if($po->save()){
            foreach($r->data as $main_stock_code => $qty){
                $pod = new PODetail;
                $pod->po_code = $po->po_code;
                $pod->main_stock_code = $main_stock_code;
                $pod->po_qty = (float) $qty;
                $pod->po_notes = isset($r->notes[$main_stock_code])?$r->notes[$main_stock_code]:NULL;
                $pod->save();
            }

            return response()->json(Api::response(true,'Sukses'),200);
        }

        return response()->json(Api::response(false,'Gagal menyimpan data'),200);
    }

    public function find_po($id){
        $data = [];
        $data['purchase_order'] = PO::where('po_code',$id)->first();
        $data['purchase_order_detail'] = PODetail::selectRaw('master.master_stock.*, document.purchase_order_detail.*, master.master_measure.measure_type')
                ->join('stock.stock', 'stock.stock.main_stock_code', '=', 'document.purchase_order_detail.main_stock_code')
                ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
                ->join('master.master_measure', 'master.master_measure.measure_code', '=', 'master.master_stock.measure_code')
                ->where('po_code',$id)->get();
        return Api::response(true,"Sukses",$data);
    }

    public function delete_po(Request $r){
        // first delete request tools detail
        PODetail::where(['po_code' => $r->po_code])->delete();
        // second delete request tools
        PO::where(['po_code' => $r->po_code])->delete();

        return response()->json(Api::response(true,'Sukses'),200);
    }

    public function grid_po(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'po_code',
            'po_date',
            'page_name'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'desc',
                'field' => 'po_date'
            );

        // whole query
        $sup = PO::selectRaw('document.purchase_order.*, (SELECT count(po_code) FROM document.purchase_order_detail WHERE po_code=document.purchase_order.po_code) as sum_item, master.master_status.status_label, master.master_page.page_name')
        ->join('master.master_status', 'master.master_status.status_code', '=', 'document.purchase_order.status')
        ->join('master.master_page', 'master.master_page.page_code', '=', 'document.purchase_order.menu_page_destination')
        ->where(['document.purchase_order.menu_page' => $input['menu_page']]);

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('status')))
                        $sup->where("document.purchase_order.".$field,($val=="null"?NULL:$val));
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







    // for delivery order
    public function add_do(Request $r){
        // record to DO table
        $date = date("Y-m-d H:i:s");
        foreach($r->data AS $po_code => $row){
          foreach ($row as $main_stock_code => $qty) {
            if($qty > 0){
              $do = new DocDO;
              $do->do_code = $r->do_code;
              $do->po_code = $po_code;
              $do->menu_page = $r->menu_page;
              $do->main_stock_code = $main_stock_code;
              $do->do_qty = $qty;
              $do->create_by = $r->nik;
              $do->create_date = $date;
              if($do->save()){
                $pod = PODetail::selectRaw('supplier_code, stock_price')->where(['po_code' => $po_code, 'main_stock_code' => $main_stock_code])->first();
                // save to stock Qty Real Prod
                $qQty = new Qty;
                $qQty->main_stock_code = $main_stock_code;
                $qQty->supplier_code = $pod->supplier_code;
                $qQty->qty = $qty;
                $qQty->stock_price = is_null($pod->stock_price)?0:$pod->stock_price;
                $qQty->stock_notes = 'Pembelian';
                $qQty->stock_date = $date;
                $qQty->nik = $r->nik;
                $qQty->do_code = $r->do_code;
                if($qQty->save()){
                  // check status in request tools status to fullfill request if stock available
                  // get stock code by main_stock_code
                  $stk = Stock::selectRaw('stock.stock.main_stock_code, stock.stock.stock_code, stock.stock.menu_page, SUM(stock.qty.qty) as qty')
                    ->join('stock.qty', 'stock.qty.main_stock_code', '=', 'stock.stock.main_stock_code')
                    ->where(['stock.stock.main_stock_code' => $main_stock_code])
                    ->groupBy(['stock.stock.main_stock_code', 'stock.stock.stock_code', 'stock.stock.menu_page'])->first();
                  // get outstanding fullfillment
                  $rtd = ReqToolsDetail::selectRaw('document.request_tools_detail.req_tools_code, document.request_tools_detail.stock_code, document.request_tools_detail.req_tools_qty')
                    ->join('document.request_tools','document.request_tools_detail.req_tools_code', '=', 'document.request_tools.req_tools_code')
                    ->where([
                      'document.request_tools_detail.stock_code' => $stk->stock_code,
                      'document.request_tools.menu_page' => $stk->menu_page,
                      'document.request_tools_detail.fullfillment' => 0
                    ])->get();

                  if($rtd->count() > 0){
                    $stock_qty = $stk->qty;
                    // process update status for waiting list fullfillment in request tools
                    foreach($rtd AS $i => $row){
                      if($this->_check_qty($row->req_tools_qty, $stk->stock_code, $stk->menu_page)){
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
            }
          }

          // check qty PO and DO to change status
          $q = DB::select(DB::raw("SELECT * FROM (SELECT row_number() over(ORDER BY document.purchase_order_detail.po_qty ASC) as id, document.purchase_order_detail.*, CAST(CASE WHEN (SELECT SUM(do_qty) FROM document.delivery_order WHERE po_code=document.purchase_order_detail.po_code AND main_stock_code=document.purchase_order_detail.main_stock_code) IS NULL THEN 0 ELSE (SELECT SUM(do_qty) FROM document.delivery_order WHERE po_code=document.purchase_order_detail.po_code AND main_stock_code=document.purchase_order_detail.main_stock_code) END AS NUMERIC(20,2)) AS qty FROM document.purchase_order_detail WHERE po_code='".$po_code."') sub WHERE qty < po_qty"));
          if(count($q) == 0){
            PO::where(['po_code' => $po_code])->update(['status' => 'ST05']);
          }
        }
        return response()->json(Api::response(true,'Sukses'),200);
    }

    public function check_do(Request $r){
        $query = DocDO::where(['do_code' => $r->do_code, 'po_code' => $r->po_code]);
        $cnt = $query->count() == 0;
        return response()->json(Api::response($cnt,!$cnt?"Nomor Surat Jalan sudah ada":"Aman"),200);
    }

    public function find_do($id){
        $data = [];
        $data['purchase_order'] = PO::where('po_code',$id)->first();
        $data['purchase_order_detail'] = PODetail::selectRaw('master.master_stock.*, document.purchase_order_detail.*, (document.purchase_order_detail.po_qty - CASE WHEN DocDO.qty IS NULL THEN 0 ELSE DocDO.qty END) AS qty')
                ->join('stock.stock', 'stock.stock.main_stock_code', '=', 'document.purchase_order_detail.main_stock_code')
                ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
                ->leftJoin(DB::raw("(SELECT po_code, main_stock_code, sum(do_qty) AS qty FROM document.delivery_order GROUP BY po_code, main_stock_code) AS DocDO"), function($do){
                  $do->on('DocDO.po_code','=','document.purchase_order_detail.po_code');
                  $do->on('DocDO.main_stock_code','=','document.purchase_order_detail.main_stock_code');
                })
                ->where('document.purchase_order_detail.po_code',$id)->get();
        return response()->json(Api::response(true,"Sukses",$data),200);
    }

    public function grid_do(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'po_code',
            'po_date',
            'page_name'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'desc',
                'field' => 'po_date'
            );

        // whole query
        $sup = PO::selectRaw('document.purchase_order.*, (SELECT count(po_code) FROM document.purchase_order_detail WHERE po_code=document.purchase_order.po_code) as sum_item, master.master_status.status_label, master.master_page.page_name')
        ->join('master.master_status', 'master.master_status.status_code', '=', 'document.purchase_order.status')
        ->join('master.master_page', 'master.master_page.page_code', '=', 'document.purchase_order.menu_page_destination')
        ->where(['document.purchase_order.menu_page' => $input['menu_page']])
        ->whereIn('document.purchase_order.status', ['ST02']);

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('status')))
                        $sup->where("document.purchase_order.".$field,($val=="null"?NULL:$val));
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
