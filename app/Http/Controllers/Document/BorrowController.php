<?php

namespace App\Http\Controllers\Document;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Document\BorrowModel AS Borrow;
use App\Model\Master\StockModel AS MasterStock;
use App\Model\Stock\StockModel AS Stock;
use App\Model\Stock\QtyModel AS Qty;
use App\Model\Stock\QtyOutModel AS QtyOut;

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

    public function find(Request $r, $id){
      $borrowed_qty = "";
        if($r->has('return'))
          $borrowed_qty = ", (borrowed_qty - (CASE WHEN (SELECT SUM(qty) FROM stock.qty WHERE stock_notes = 'Pengembalian (".$id.")') IS NOT NULL THEN (SELECT SUM(qty) FROM stock.qty WHERE stock_notes = 'Pengembalian (".$id.")') ELSE 0 END)) AS borrowed_last_qty";
        $qty = "(qty.stock_qty - CASE WHEN (SELECT SUM(borrowed_qty) FROM document.borrowed WHERE main_stock_code=stock.stock.main_stock_code AND status = 'ST02') IS NOT NULL THEN (SELECT SUM(borrowed_qty) FROM document.borrowed WHERE main_stock_code=stock.stock.main_stock_code AND status = 'ST02') ELSE 0 END) AS stock_qty";

        $data = Borrow::selectRaw("document.borrowed.*, master.master_stock.stock_code, master.master_stock.stock_name, master.master_stock.stock_type, master.master_stock.stock_size, ".$qty.$borrowed_qty)
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
      $borrow->borrowed_req_name = $r->borrowed_req_name;
      $borrow->borrowed_notes = $r->borrowed_notes;
      $borrow->nik = $r->nik;
      $borrow->company_code = $r->company_code;
      $borrow->create_by = $r->has('create_by')?$r->create_by:$r->nik;
      if(!empty($r->borrowed_take_name) && !is_null($r->borrowed_take_name)){
        $borrow->borrowed_take_name = $r->borrowed_take_name;
        if(!empty($r->take_nik) && !is_null($r->take_nik))
          $borrow->take_nik = $r->take_nik;
        $borrow->status = 'ST02';
      }
      if($borrow->save()){
        // stock out
        if($borrow->status == 'ST02'){
          $stk = Stock::where(['main_stock_code' => $r->main_stock_code])->first();
          DB::select(DB::raw("EXEC stock.stock_out @stcode='".$stk->stock_code."', @qty='".$r->borrowed_qty."',@nik='".($r->has('create_by')?$r->create_by:$r->nik)."', @notes='Peminjaman (".$borrow->borrowed_code.")', @page='".$r->page_code."' "));
        }
        return response()->json(Api::response(1,'Sukses'),200);
      }

      return response()->json(Api::response(0,'Gagal simpan'),200);
    }

    public function edit(Request $r){
      $data = [
        'main_stock_code' => $r->main_stock_code,
        'borrowed_date' => $r->borrowed_date,
        'borrowed_long_term' => $r->borrowed_long_term,
        'borrowed_qty' => $r->borrowed_qty,
        'nik' => $r->has('nik')?(!empty($r->nik)?$r->nik:NULL):NULL,
        'take_nik' => $r->has('take_nik')?(!empty($r->take_nik)?$r->take_nik:NULL):NULL,
        'borrowed_req_name' => $r->has('borrowed_req_name')?(!empty($r->borrowed_req_name)?$r->borrowed_req_name:NULL):NULL,
        'borrowed_take_name' => $r->has('borrowed_take_name')?(!empty($r->borrowed_take_name)?$r->borrowed_take_name:NULL):NULL
      ];
      if(!empty($r->borrowed_take_name) && !is_null($r->borrowed_take_name))
        $data['status'] = 'ST02';

      if(Borrow::where(['borrowed_code' => $r->borrowed_code])->update($data)){
        // stock out
        if($data['status'] == 'ST02'){
          $stk = Stock::where(['main_stock_code' => $r->main_stock_code])->first();
          DB::select(DB::raw("EXEC stock.stock_out @stcode='".$stk->stock_code."', @qty='".$r->borrowed_qty."',@nik='".($r->has('create_by')?$r->create_by:$r->nik)."', @notes='Peminjaman (".$r->borrowed_code.")', @page='".$r->page_code."' "));
        }
        return response()->json(Api::response(1,'Sukses'),200);
      }

      return response()->json(Api::response(0,'Gagal simpan'),200);
    }

    public function delete(Request $r){
      if(Borrow::where(['borrowed_code' => $r->borrowed_code])->delete())
        return response()->json(Api::response(1,'Sukses'),200);

      return response()->json(Api::response(0,'Gagal simpan'),200);
    }

    public function return_qty(Request $r){
      $date = date('Y-m-d H:i:s');
      // check qty by borrowed_code in qty in and out if balance don't process but change flag only
      $query_in = DB::select(DB::raw("SELECT SUM(qty) AS qty FROM stock.qty WHERE main_stock_code='".$r->main_stock_code."' AND stock_notes = 'Pengembalian (".$r->borrowed_code.")'"));
      $query_out = DB::select(DB::raw("SELECT SUM(qty) AS qty FROM stock.qty_out WHERE main_stock_code='".$r->main_stock_code."' AND stock_notes = 'Peminjaman (".$r->borrowed_code.")'"));

      if(!is_null($query_out[0]->qty) && !is_null($query_in[0]->qty)){
        if($query_in[0]->qty == $query_out[0]->qty){
          Borrow::where(['borrowed_code' => $r->borrowed_code])->update(['status' => 'ST05']);
        }
        // add qty if not balance
        else {
          // get all stock out
          $out = QtyOut::where(['main_stock_code' => $r->main_stock_code, 'stock_notes' => 'Peminjaman ('.$r->borrowed_code.')'])->orderBy('stock_out_date', 'ASC')->get();
          // remove qty in qty_out was insert into qty table
          $tmp_in = $query_in[0]->qty;
          $qty_return = $r->returned_qty;
          foreach ($out as $i => $rows) {
            if($tmp_in <= 0){
              $tmp_in = 0;
            }

            if($tmp_in > 0)
              $tmp_in -= $rows->qty;

            // insert -qty if value qty_in less than 0
            if($tmp_in == 0){
              $last_qty = $qty_return;
              $qty_return -= $rows->qty;
              // if qty return > 0 after min by qty out
              if($qty_return > 0)
                $qty = $rows->qty;
              else
                $qty = $last_qty;

              // saving into qty
              $query = new Qty;
              $query->main_stock_code = $rows->main_stock_code;
              $query->supplier_code = $rows->supplier_code;
              $query->stock_price = $rows->stock_price;
              $query->stock_date = $date;
              $query->stock_notes = 'Pengembalian ('.$r->borrowed_code.')';
              $query->qty = $qty;
              $query->nik = $r->nik;
              $query->save();

              if($qty_return <= 0)
                break;
            } else if($tmp_in < 0){
              $last_qty = $qty_return;
              $qty_return -= ($rows->qty - abs($tmp_in));
              // if qty return > 0 after min by qty out
              if($qty_return > 0)
                $qty = ($rows->qty - abs($tmp_in));
              else
                $qty = $last_qty;

              // saving into qty
              $query = new Qty;
              $query->main_stock_code = $rows->main_stock_code;
              $query->supplier_code = $rows->supplier_code;
              $query->stock_price = $rows->stock_price;
              $query->stock_date = $date;
              $query->stock_notes = 'Pengembalian ('.$r->borrowed_code.')';
              $query->qty = $qty;
              $query->nik = $r->nik;
              $query->save();

              if($qty_return <= 0)
                break;
            }
          }
        }
      }
      // if not balance insert in qty in and prefix "Pengembalian (borrowed_code)"
      else {
        // get all stock out
        $out = QtyOut::where(['main_stock_code' => $r->main_stock_code, 'stock_notes' => 'Peminjaman ('.$r->borrowed_code.')'])->orderBy('stock_out_date', 'ASC')->get();
        $qty_return = $r->returned_qty;
        foreach ($out as $i => $rows) {
          $last_qty = $qty_return;
          $qty_return -= $rows->qty;
          // if qty return > 0 after min by qty out
          if($qty_return > 0)
            $qty = $rows->qty;
          else
            $qty = $last_qty;

          // saving into qty
          $query = new Qty;
          $query->main_stock_code = $rows->main_stock_code;
          $query->supplier_code = $rows->supplier_code;
          $query->stock_price = $rows->stock_price;
          $query->stock_date = $date;
          $query->stock_notes = 'Pengembalian ('.$r->borrowed_code.')';
          $query->qty = $qty;
          $query->nik = $r->nik;
          $query->save();

          if($qty_return <= 0)
            break;
        }
      }
      // check again if balance flag it
      $query_in = DB::select(DB::raw("SELECT SUM(qty) AS qty FROM stock.qty WHERE main_stock_code='".$r->main_stock_code."' AND stock_notes = 'Pengembalian (".$r->borrowed_code.")'"));
      $query_out = DB::select(DB::raw("SELECT SUM(qty) AS qty FROM stock.qty_out WHERE main_stock_code='".$r->main_stock_code."' AND stock_notes = 'Peminjaman (".$r->borrowed_code.")'"));

      if($query_in[0]->qty == $query_out[0]->qty){
        Borrow::where(['borrowed_code' => $r->borrowed_code])->update(['status' => 'ST05']);
      }

      return response()->json(Api::response(1,'Sukses'),200);
    }

    public function grid(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'stock_name',
            'stock.stock.stock_code',
            'stock_type',
            'stock_size',
            'company_name',
            'borrowed_req_name',
            'borrowed_take_name'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'desc',
                'field' => 'borrowed_code'
            );

        // whole query
        $query = Borrow::selectRaw('document.borrowed.*, master.master_stock.stock_name, master.master_stock.stock_code, master.master_stock.stock_type, master.master_stock.stock_size, master.master_status.status_label, company_name, DATEADD(day, borrowed_long_term, borrowed_date) AS borrowed_end_date')
        ->join('stock.stock', 'stock.stock.main_stock_code', '=', 'document.borrowed.main_stock_code')
        ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code')
        ->join('master.master_status', 'master.master_status.status_code', '=', 'document.borrowed.status')
        ->leftJoin('master.master_company', 'master.master_company.company_code', '=', 'document.borrowed.company_code')
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
                            $query->where(function($query) use($column_search,$val){
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
