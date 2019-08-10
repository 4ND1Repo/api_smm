<?php

namespace App\Http\Controllers\Purchasing;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Master\StockModel AS MasterStock;
use App\Model\Stock\StockModel AS Stock;
use App\Model\Stock\QtyModel AS Qty;
use App\Model\Stock\QtyOutModel AS QtyOut;
use App\Model\Document\RequestToolsModel AS ReqTools;
use App\Model\Document\RequestToolsDetailModel AS ReqToolsDetail;
use App\Model\Document\PoModel AS PO;
use App\Model\Document\PoDetailModel AS PODetail;

// Embed a Helper
use DB;
use App\Helpers\Api;
use Illuminate\Http\Request;



class PoController extends Controller
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

    public function process(Request $r){
        foreach ($r->data as $po_code => $row) {
          foreach ($row as $main_stock_code => $data) {
              $update = [];
              $qty = PODetail::where([
                  'main_stock_code' => $main_stock_code,
                  'po_code' => $po_code
              ])->first();

              if(!empty($data['supplier'])){
                $tmp = explode(' - ',$data['supplier']);
                $update['supplier_code'] = $tmp[0];
              } else
                $update['supplier_code'] = NULL;

              if(!empty($data['date'])){
                $tmp = explode('/',$data['date']);
                $update['po_date_delivery'] = $tmp[2]."-".$tmp[1]."-".$tmp[0];
              } else
                $update['po_date_delivery'] = NULL;

              $update['stock_price'] = !empty($data['price'])?(float)$data['price']:0;

              if((float)$data['qty'] !== (float)$qty->po_qty){
                if(is_null($qty->po_old_qty)){
                  $update['po_old_qty'] = $qty->po_qty;
                }
                $update['po_qty'] = $data['qty'];
              }

              if(PODetail::where([
                  'main_stock_code' => $main_stock_code,
                  'po_code' => $po_code
              ])->update($update)){
                if(!empty($data['date'])){
                  PO::where(['po_code' => $po_code])->update(['status' => 'ST02']);
                }
              }
          }
        }
        $return = [
          "po_code" => $po_code,
          "to" => PO::where(['po_code' => $po_code])->first()->create_by
        ];
        return response()->json(Api::response(true,"Sukses",$return),200);
    }

    public function find($id){
        $data = [];
        $data['purchase_order'] = PO::where('po_code',$id)->first();
        $data['purchase_order_detail'] = PODetail::selectRaw('master.master_stock.*, document.purchase_order_detail.*, (document.purchase_order_detail.po_qty - CASE WHEN DocDO.qty IS NULL THEN 0 ELSE DocDO.qty END) AS qty, master.master_supplier.supplier_name, master.master_measure.measure_type')
                ->join('stock.stock', 'stock.stock.main_stock_code', '=', 'document.purchase_order_detail.main_stock_code')
                ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
                ->join('master.master_measure', 'master.master_measure.measure_code', '=', 'master.master_stock.measure_code')
                ->leftJoin('master.master_supplier', 'master.master_supplier.supplier_code', '=', 'document.purchase_order_detail.supplier_code')
                ->leftJoin(DB::raw("(SELECT po_code, main_stock_code, sum(do_qty) AS qty FROM document.delivery_order GROUP BY po_code, main_stock_code) AS DocDO"), function($do){
                  $do->on('DocDO.po_code','=','document.purchase_order_detail.po_code');
                  $do->on('DocDO.main_stock_code','=','document.purchase_order_detail.main_stock_code');
                })
                ->where('document.purchase_order_detail.po_code',$id)->get();
        return response()->json(Api::response(true,"Sukses",$data),200);
    }

    public function check_price(Request $r){
        $tmp = explode(' - ',$r->supplier_code);
        $query = PODetail::selectRaw('document.purchase_order_detail.stock_price')
            ->join('document.purchase_order', 'document.purchase_order.po_code', '=', 'document.purchase_order_detail.po_code')
            ->where('main_stock_code', $r->main_stock_code)
            ->where('supplier_code', $tmp[0])
            ->whereNotNull('finish_by')
            ->orderBy('create_date', 'DESC');
        return response()->json(Api::response(true,"Sukses",$query->count() > 0?(!is_null($r = $query->first()->stock_price)?$r:0):0),200);
    }

    public function cancel(Request $r){
        $query = PO::where('po_code', $r->po_code)
            ->update(['status' => 'ST09', 'reason' => $r->reason, 'finish_by' => $r->nik, 'finish_date' => date('Y-m-d H:i:s')]);
        return response()->json(Api::response(true,"Sukses"),200);
    }

    public function get(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'po_code',
            'po_date',
            'page_name',
            'nik'
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
        ->join('master.master_page', 'master.master_page.page_code', '=', 'document.purchase_order.page_code')
        ->where(['document.purchase_order.page_code_destination' => $input['page_code']])
        ->whereIn('document.purchase_order.status', ['ST02','ST05','ST06']);

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('status')) && (!empty($val) && !is_null($val)))
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

        $sup->orderBy($input['sort']['field'],$input['sort']['sort']);

        $data = $sup->get();

        return response()->json($data,200);
    }

    public function grid(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'po_code',
            'po_date',
            'page_name',
            'nik'
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
        ->join('master.master_page', 'master.master_page.page_code', '=', 'document.purchase_order.page_code')
        ->where(['document.purchase_order.page_code_destination' => $input['page_code']])
        ->whereIn('document.purchase_order.status', ['ST02','ST05','ST06']);

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

    public function history_get(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'document.purchase_order_detail.po_code',
            'document.purchase_order.po_date',
            'document.delivery_order.do_code',
            'master.master_stock.stock_name',
            'master.master_stock.stock_size',
            'master.master_stock.stock_brand',
            'master.master_stock.stock_type',
            'master.master_stock.stock_color'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'desc',
                'field' => 'document.purchase_order.po_date'
            );
        else {
          if($input['sort']['field'] == 'po_code') $input['sort']['field'] = 'document.purchase_order_detail.po_code';
        }

        // whole query
        $sup = PODetail::selectRaw('document.purchase_order_detail.*, master.master_stock.*, po_date, document.purchase_order.status, finish_by, finish_date, document.delivery_order.do_code, document.delivery_order.do_qty, master.master_status.status_label, master.master_measure.measure_type')
        ->join('document.purchase_order', 'document.purchase_order.po_code', '=', 'document.purchase_order_detail.po_code')
        ->leftJoin('document.delivery_order', function($query){
            $query->on('document.delivery_order.po_code', '=', 'document.purchase_order_detail.po_code');
            $query->on('document.delivery_order.main_stock_code', '=', 'document.purchase_order_detail.main_stock_code');
        })
        ->join('stock.stock', 'stock.stock.main_stock_code', '=', 'document.purchase_order_detail.main_stock_code')
        ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
        ->join('master.master_status', 'master.master_status.status_code', '=', 'document.purchase_order.status')
        ->join('master.master_measure', 'master.master_measure.measure_code', '=', 'master.master_stock.measure_code')
        ->where(['document.purchase_order.page_code_destination' => $input['page_code']])
        ->whereIn('document.purchase_order.status', ['ST05','ST09']);

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('status')) && (!empty($val) && !is_null($val)))
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

        $sup->orderBy($input['sort']['field'],$input['sort']['sort']);

        $data = $sup->get();

        return response()->json($data,200);
    }

    public function history_grid(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'document.purchase_order_detail.po_code',
            'document.purchase_order.po_date',
            'document.delivery_order.do_code',
            'master.master_stock.stock_name',
            'master.master_stock.stock_size',
            'master.master_stock.stock_brand',
            'master.master_stock.stock_type',
            'master.master_stock.stock_color'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'desc',
                'field' => 'document.purchase_order.po_date'
            );
        else {
          if($input['sort']['field'] == 'po_code') $input['sort']['field'] = 'document.purchase_order_detail.po_code';
        }

        // whole query
        $sup = PODetail::selectRaw('document.purchase_order_detail.*, master.master_stock.*, po_date, document.purchase_order.status, finish_by, finish_date, document.delivery_order.do_code, document.delivery_order.do_qty, master.master_status.status_label, master.master_measure.measure_type')
        ->join('document.purchase_order', 'document.purchase_order.po_code', '=', 'document.purchase_order_detail.po_code')
        ->leftJoin('document.delivery_order', function($query){
            $query->on('document.delivery_order.po_code', '=', 'document.purchase_order_detail.po_code');
            $query->on('document.delivery_order.main_stock_code', '=', 'document.purchase_order_detail.main_stock_code');
        })
        ->join('stock.stock', 'stock.stock.main_stock_code', '=', 'document.purchase_order_detail.main_stock_code')
        ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
        ->join('master.master_status', 'master.master_status.status_code', '=', 'document.purchase_order.status')
        ->join('master.master_measure', 'master.master_measure.measure_code', '=', 'master.master_stock.measure_code')
        ->where(['document.purchase_order.page_code_destination' => $input['page_code']])
        ->whereIn('document.purchase_order.status', ['ST05','ST09']);

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
