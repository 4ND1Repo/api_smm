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
use App\Model\Document\DoModel AS Delivery;
use App\Model\Document\PoDetailModel AS PODetail;

// Embed a Helper
use DB;
use App\Helpers\{Api, Log};
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

    private function _generate_prefix_pod(){
        $prefix = "POD".date("ym");
        $SP = PODetail::select('pod_code')->where('pod_code','LIKE',$prefix.'%')->orderBy('pod_code', 'DESC')->get();
        if($SP->count() > 0){
            $SP = $SP->first();
            $tmp = explode($prefix, $SP->pod_code);
            $count = ((int)$tmp[1])+1;
        } else
            $count = 1;

        return $prefix.sprintf("%05d",$count);
    }

    public function process(Request $r){
        $date = date('Y-m-d H:i:s');
        foreach ($r->data as $po_code => $row) {
          $finish = 0;
          $cnt = count($row);
          foreach ($row as $pod_code => $data) {
              $update = [];
              $qty = PODetail::where([
                  'pod_code' => $pod_code,
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

                // begin:generate if user split item
              if(isset($data['new'])){
                foreach($data['new'] AS $i => $new_row){
                    $pod = new PODetail;
                    $pod->pod_code = $this->_generate_prefix_pod();
                    $pod->main_stock_code = $qty->main_stock_code;
                    $pod->po_code = $po_code;
                    $pod->po_qty = (!empty($new_row['qty'])?(float)$new_row['qty']:0);
                    if(!empty($new_row['date'])){
                        $tmp = explode('/',$new_row['date']);
                        $pod->po_date_delivery = $tmp[2]."-".$tmp[1]."-".$tmp[0];
                    }
                    if(!empty($new_row['supplier'])){
                        $tmp = explode(' - ',$new_row['supplier']);
                        $pod->supplier_code = $tmp[0];
                    }
                    $pod->stock_price = !empty($new_row['price'])?(float)$new_row['price']:0;
                    $pod->po_notes = $qty->po_notes;
                    $pod->urgent = $qty->urgent;
                    $pod->save();
                }
              }
              // end:generate if user split item

              if(PODetail::where([
                  'pod_code' => $pod_code,
                  'po_code' => $po_code
              ])->update($update)){
                if(!empty($data['date'])){
                  PO::where(['po_code' => $po_code])->update(['status' => 'ST02']);
                }
                // check qty in after DO Receive
                $q = Delivery::selectRaw("SUM(do_qty) as qty")->where(['pod_code' => $pod_code])->groupBy('pod_code');
                if($q->count() > 0){
                  $tmp = $q->first();
                  if($data['qty'] <= $tmp->qty)
                    $finish++;
                }
              }
          }
          if($finish == $cnt){
            PO::where(['po_code' => $po_code])->update(['status' => 'ST05', 'finish_by' => $r->nik, 'finish_date' => $date]);
          }
          // log when edit
          Log::add([
            'type' => 'Edit',
            'nik' => $r->nik,
            'description' => 'Memproses PO nomor : '.$po_code
          ]);
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

    public function print_data(Request $r){
        $query = PODetail::selectRaw('master.master_stock.*, document.purchase_order_detail.*, master.master_supplier.supplier_name, master.master_supplier.supplier_address, master.master_supplier.supplier_phone, master.master_city.city_name, master.master_measure.measure_type, document.purchase_order.po_date')
                ->join('stock.stock', 'stock.stock.main_stock_code', '=', 'document.purchase_order_detail.main_stock_code')
                ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
                ->join('master.master_measure', 'master.master_measure.measure_code', '=', 'master.master_stock.measure_code')
                ->join('document.purchase_order', 'document.purchase_order.po_code', '=', 'document.purchase_order_detail.po_code')
                ->leftJoin('master.master_supplier', 'master.master_supplier.supplier_code', '=', 'document.purchase_order_detail.supplier_code')
                ->leftJoin('master.master_city', 'master.master_city.city_code', '=', 'master.master_supplier.city_code')
                ->where('document.purchase_order_detail.po_code',$r->po_code)
                ->where('document.purchase_order_detail.supplier_code', $r->supplier_code)->get();
        $data = [];
        foreach ($query as $i => $row) {
          if(!is_null($row->supplier_code) && !empty($row->supplier_code))
            $data[$row->supplier_code][] = $row;
        }
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
        ->whereIn('document.purchase_order.status', ['ST02','ST06']);

        // condition for date range
        if(isset($input['query']['start_date']))
            $sup->whereRaw("document.purchase_order.po_date >= '".$input['query']['start_date']." 00:00:00'");
        if(isset($input['query']['end_date']))
            $sup->whereRaw("document.purchase_order.po_date <= '".$input['query']['end_date']." 23:59:59'");

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
        ->whereIn('document.purchase_order.status', ['ST02','ST06']);

        // condition for date range
        if(isset($input['query']['start_date']))
            $sup->whereRaw("document.purchase_order.po_date >= '".$input['query']['start_date']." 00:00:00'");
        if(isset($input['query']['end_date']))
            $sup->whereRaw("document.purchase_order.po_date <= '".$input['query']['end_date']." 23:59:59'");

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

        // condition for date range
        if(isset($input['query']['start_date']))
            $sup->whereRaw("document.purchase_order.po_date >= '".$input['query']['start_date']." 00:00:00'");
        if(isset($input['query']['end_date']))
            $sup->whereRaw("document.purchase_order.po_date <= '".$input['query']['end_date']." 23:59:59'");

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
                                    $sup->orWhere($row,'like',(in_array($row,['master.master_stock.stock_name','master.master_stock.stock_brand','master.master_stock.stock_size'])?"":"%").$val."%");
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

        // condition for date range
        if(isset($input['query']['start_date']))
            $sup->whereRaw("document.purchase_order.po_date >= '".$input['query']['start_date']." 00:00:00'");
        if(isset($input['query']['end_date']))
            $sup->whereRaw("document.purchase_order.po_date <= '".$input['query']['end_date']." 23:59:59'");

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
                                    $sup->orWhere($row,'like',(in_array($row,['master.master_stock.stock_name','master.master_stock.stock_brand','master.master_stock.stock_size'])?"":"%").$val."%");
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

    public function print_get(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'document.purchase_order_detail.po_code',
            'document.purchase_order_detail.po_notes',
            'document.purchase_order.po_date',
            'stock.stock_name',
            'stock.stock_spec',
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
        $sup = PODetail::selectRaw('ROW_NUMBER() OVER(ORDER BY '.$input['sort']['field'].' '.$input['sort']['sort'].') as id, document.purchase_order_detail.po_code, document.purchase_order_detail.po_qty, document.purchase_order_detail.po_notes, document.purchase_order.po_date, stock.stock_spec, stock.stock_name')
        ->join('document.purchase_order', 'document.purchase_order.po_code', '=', 'document.purchase_order_detail.po_code')
        ->join(DB::raw("(
            SELECT stock.stock.main_stock_code, master.master_stock.stock_name, (
                CASE WHEN master.master_stock.stock_size IS NOT NULL THEN master.master_stock.stock_size ELSE '' END +
                CASE WHEN master.master_stock.stock_type IS NOT NULL THEN master.master_stock.stock_type ELSE '' END +
                CASE WHEN master.master_stock.stock_brand IS NOT NULL THEN master.master_stock.stock_brand ELSE '' END +
                CASE WHEN master.master_stock.stock_color IS NOT NULL THEN master.master_stock.stock_color ELSE '' END
            ) AS stock_spec FROM stock.stock
            JOIN master.master_stock ON master.master_stock.stock_code = stock.stock.stock_code
        ) AS stock"),'stock.main_stock_code', '=', 'document.purchase_order_detail.main_stock_code')
        ->where(['document.purchase_order.page_code_destination' => $input['page_code']])
        ->whereRaw("document.purchase_order_detail.supplier_code IS NOT NULL AND document.purchase_order_detail.po_date_delivery IS NOT NULL AND document.purchase_order_detail.stock_price <> 0")
        ->whereIn('document.purchase_order.status', ['ST02']);

        // condition for date range
        if(isset($input['query']['start_date']))
            $sup->whereRaw("document.purchase_order.po_date >= '".$input['query']['start_date']." 00:00:00'");
        if(isset($input['query']['end_date']))
            $sup->whereRaw("document.purchase_order.po_date <= '".$input['query']['end_date']." 23:59:59'");

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('supplier_code')))
                        $sup->where("document.purchase_order_detail.".$field,($val=="null"?NULL:$val));
                    else if($field == 'find'){
                        if(!empty($val)){
                            $sup->where(function($sup) use($column_search,$val){
                                foreach($column_search as $row)
                                    $sup->orWhere($row,'like',(in_array($row,['master.master_stock.stock_name','master.master_stock.stock_brand','master.master_stock.stock_size'])?"":"%").$val."%");
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

    public function print_grid(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'document.purchase_order_detail.po_code',
            'document.purchase_order_detail.po_notes',
            'document.purchase_order.po_date',
            'stock.stock_name',
            'stock.stock_spec',
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
        $sup = PODetail::selectRaw('ROW_NUMBER() OVER(ORDER BY '.$input['sort']['field'].' '.$input['sort']['sort'].') as id, document.purchase_order_detail.po_code, document.purchase_order_detail.po_qty, document.purchase_order_detail.po_notes, document.purchase_order.po_date, stock.stock_spec, stock.stock_name')
        ->join('document.purchase_order', 'document.purchase_order.po_code', '=', 'document.purchase_order_detail.po_code')
        ->join(DB::raw("(
            SELECT stock.stock.main_stock_code, master.master_stock.stock_name, (
                CASE WHEN master.master_stock.stock_size IS NOT NULL THEN master.master_stock.stock_size ELSE '' END +
                CASE WHEN master.master_stock.stock_type IS NOT NULL THEN master.master_stock.stock_type ELSE '' END +
                CASE WHEN master.master_stock.stock_brand IS NOT NULL THEN master.master_stock.stock_brand ELSE '' END +
                CASE WHEN master.master_stock.stock_color IS NOT NULL THEN master.master_stock.stock_color ELSE '' END
            ) AS stock_spec FROM stock.stock
            JOIN master.master_stock ON master.master_stock.stock_code = stock.stock.stock_code
        ) AS stock"),'stock.main_stock_code', '=', 'document.purchase_order_detail.main_stock_code')
        ->where(['document.purchase_order.page_code_destination' => $input['page_code']])
        ->whereRaw("document.purchase_order_detail.supplier_code IS NOT NULL AND document.purchase_order_detail.po_date_delivery IS NOT NULL AND document.purchase_order_detail.stock_price <> 0")
        ->whereIn('document.purchase_order.status', ['ST02']);

        // condition for date range
        if(isset($input['query']['start_date']))
            $sup->whereRaw("document.purchase_order.po_date >= '".$input['query']['start_date']." 00:00:00'");
        if(isset($input['query']['end_date']))
            $sup->whereRaw("document.purchase_order.po_date <= '".$input['query']['end_date']." 23:59:59'");

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('supplier_code')))
                        $sup->where("document.purchase_order_detail.".$field,($val=="null"?NULL:$val));
                    else if($field == 'find'){
                        if(!empty($val)){
                            $sup->where(function($sup) use($column_search,$val){
                                foreach($column_search as $row)
                                    $sup->orWhere($row,'like',(in_array($row,['master.master_stock.stock_name','master.master_stock.stock_brand','master.master_stock.stock_size'])?"":"%").$val."%");
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

    public function supplier_process(Request $r){
        $q = PODetail::select('document.purchase_order_detail.supplier_code', 'supplier_name')
        ->join('document.purchase_order', 'document.purchase_order.po_code', '=', 'document.purchase_order_detail.po_code')
        ->join('master.master_supplier', 'master.master_supplier.supplier_code', '=', 'document.purchase_order_detail.supplier_code')
        ->where(['document.purchase_order.status' => 'ST02'])->groupBy(['document.purchase_order_detail.supplier_code', 'master.master_supplier.supplier_name']);
        if($q->count() > 0){
            $data = $q->get();
            return response()->json(Api::response(true,"Sukses",$data),200);
        }

        return response()->json(Api::response(false,"Gagal"),200);
    }

    public function supplier(Request $r){
        $q = PODetail::select('supplier_code')->where(['po_code' => $r->po_code])->groupBy(['supplier_code']);
        if($q->count() > 0){
            $data = [];
            foreach ($q->get() AS $row){
                $data[] = $row->supplier_code;
            }
            return response()->json(Api::response(true,"Sukses",$data),200);
        }

        return response()->json(Api::response(false,"Gagal"),200);
    }
}
