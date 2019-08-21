<?php

namespace App\Http\Controllers\Document;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Document\BorrowModel AS Borrow;
use App\Model\Master\StockModel AS MasterStock;
use App\Model\Stock\StockModel AS Stock;
use App\Model\Stock\QtyModel AS Qty;

// Embed a Helper
use DB;
use App\Helpers\Api;
use Illuminate\Http\Request;



class BorrowController extends Controller
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
        $prefix = "BRG";
        $q = Borrow::select('borrowed_code')->orderBy('borrowed_code', 'DESC')->get();
        if($q->count() > 0){
            $q = $q->first();
            $tmp = explode($prefix, $q->borrowed_code);
            $count = ((int)$tmp[1])+1;
        } else
            $count = 1;

        return $prefix.sprintf("%07d",$count);
    }

    public function find($id){
        $qty = "(qty.stock_qty - CASE WHEN (SELECT SUM(borrowed_qty) FROM document.borrowed WHERE main_stock_code=stock.stock.main_stock_code AND status = 'ST02') IS NOT NULL THEN (SELECT SUM(borrowed_qty) FROM document.borrowed WHERE main_stock_code=stock.stock.main_stock_code AND status = 'ST02') ELSE 0 END) AS stock_qty";

        $data = Borrow::selectRaw("document.borrowed.*, master.master_stock.stock_code, master.master_stock.stock_name, master.master_stock.stock_type, master.master_stock.stock_size, ".$qty)
        ->join('stock.stock','stock.stock.main_stock_code','=','document.borrowed.main_stock_code')
        ->join('master.master_stock','master.master_stock.stock_code','=','stock.stock.stock_code')
        ->leftJoin(DB::raw("(SELECT DISTINCT main_stock_code, SUM(qty) AS stock_qty FROM stock.qty GROUP BY main_stock_code ) AS qty"),'qty.main_stock_code','=','stock.stock.main_stock_code')
        ->where('document.borrowed.borrowed_code',$id)
        ->first();
        return response()->json(Api::response(true,"Sukses",$data),200);
    }

    public function add(Request $r){
      $borrow = new Borrow;
      $borrow->borrowed_code = $this->_generate_prefix();
      $borrow->main_stock_code = $r->main_stock_code;
      $borrow->borrowed_date = $r->borrowed_date;
      $borrow->borrowed_long_term = $r->borrowed_long_term;
      $borrow->borrowed_qty = $r->borrowed_qty;
      $borrow->nik = $r->nik;
      $borrow->create_by = $r->has('create_by')?$r->create_by:$r->nik;
      if($borrow->save())
        return response()->json(Api::response(1,'Sukses'),200);

      return response()->json(Api::response(0,'Gagal simpan'),200);
    }

    public function edit(Request $r){
      if(Borrow::where(['borrowed_code' => $r->borrowed_code])->update([
        'main_stock_code' => $r->main_stock_code,
        'borrowed_date' => $r->borrowed_date,
        'borrowed_long_term' => $r->borrowed_long_term,
        'borrowed_qty' => $r->borrowed_qty
      ]))
        return response()->json(Api::response(1,'Sukses'),200);

      return response()->json(Api::response(0,'Gagal simpan'),200);
    }

    public function delete(Request $r){
      if(Borrow::where(['borrowed_code' => $r->borrowed_code])->delete())
        return response()->json(Api::response(1,'Sukses'),200);

      return response()->json(Api::response(0,'Gagal simpan'),200);
    }

    public function grid(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'stock_name',
            'stock_code',
            'stock_type',
            'stock_size'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'desc',
                'field' => 'borrowed_code'
            );

        // whole query
        $query = Borrow::selectRaw('document.borrowed.*, master.master_stock.stock_name, master.master_stock.stock_code, master.master_stock.stock_type, master.master_stock.stock_size, master.master_status.status_label')
        ->join('stock.stock', 'stock.stock.main_stock_code', '=', 'document.borrowed.main_stock_code')
        ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
        ->join('master.master_status', 'master.master_status.status_code', '=', 'document.borrowed.status')
        ->where(function($query) use($input){
            if(isset($input['nik']))
              $query->where('document.borrowed.nik', $input['nik']);
        });

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('status')))
                        $query->where("document.borrowed.".$field,($val=="null"?NULL:$val));
                    else if($field == 'find'){
                        if(!empty($val)){
                            $query->where(function($sup) use($column_search,$val){
                                foreach($column_search as $row)
                                    $query->orWhere($row,'like',"%".$val."%");
                            });
                        }
                    }
                }
            }
        }
        // $sup->where();
        $count_all = $query->count();
        // get total page from count all
        $pages = (!empty($input['pagination']['perpage']) && !is_null($input['pagination']['perpage']))? ceil($count_all/$input['pagination']['perpage']):1;

        $query->orderBy($input['sort']['field'],$input['sort']['sort']);

        // skipping for next page
        $skip = (!empty($input['pagination']['perpage']) && !is_null($input['pagination']['perpage']))?($input['pagination']['page']-1)*$input['pagination']['perpage']:0;
        $query->skip($skip);
        if(!empty($input['pagination']['perpage']) && !is_null($input['pagination']['perpage']))
            $query->take($input['pagination']['perpage']);

        $row = $query->get();
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
