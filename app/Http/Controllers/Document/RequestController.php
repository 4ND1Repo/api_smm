<?php

namespace App\Http\Controllers\Document;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Document\RequestToolsModel AS ReqTools;
use App\Model\Document\RequestToolsDetailModel AS ReqToolsDetail;
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

    private function _generate_prefix(){
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
        $data['request_tools_detail'] = ReqToolsDetail::join('master.master_stock', 'master.master_stock.stock_code', '=', 'document.request_tools_detail.stock_code')
                ->where('req_tools_code',$id)->get();
        return Api::response(true,"Sukses",$data);
    }

    public function add_tools(Request $r){
        $drt = new ReqTools;
        $drt->req_tools_code = $this->_generate_prefix();
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

}