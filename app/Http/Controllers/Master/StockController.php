<?php

namespace App\Http\Controllers\Master;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Master\StockModel AS Stock;
use App\Model\Master\PageModel AS Page;
use App\Model\Stock\QtyModel AS Qty;

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

    private function _generate_prefix($prefix = "STK"){
        $SP = Stock::select('stock_code')->where('stock_code','LIKE',$prefix.'%')->orderBy('stock_code', 'DESC')->get();
        if($SP->count() > 0){
            $SP = $SP->first();
            $tmp = explode($prefix, $SP->stock_code);
            $count = ((int)$tmp[1])+1;
        } else
            $count = 1;

        return $prefix.sprintf("%07d",$count);
    }

    public function index(){
        return Api::response(true,"Sukses",Stock::all());
    }

    public function find($id){
        return Api::response(true,"Sukses",Stock::where('stock_code',$id)->first());
    }

    public function add(Request $r){
        // add stock
        $res = false;

        $stock = new Stock;
        $stock->stock_code = $this->_generate_prefix($r->input('category_code'));
        $stock->stock_name = $r->input('stock_name');
        $stock->stock_size = $r->input('stock_size');
        $stock->stock_brand = $r->input('stock_brand');
        $stock->stock_type = $r->input('stock_type');
        $stock->stock_color = $r->input('stock_color');
        $stock->measure_code = $r->input('measure_code');
        $stock->stock_min_qty = $r->input('stock_min_qty')?$r->input('stock_min_qty'):0;
        $stock->stock_max_qty = $r->input('stock_max_qty')?$r->input('stock_max_qty'):0;
        $stock->stock_daily_use = $r->has('stock_daily_use')?1:0;
        $res = $stock->save();

        return response()->json(Api::response($res,$res?"Sukses":"Gagal",$stock),200);
    }

    public function edit(Request $r){
        // edit stock
        $old = Stock::where(['stock_code'=>$r->input('stock_code')]);
        if($old->count() > 0){
            $old->first();

            $old->update([
                'stock_name' => $r->input('stock_name'),
                'stock_size' => $r->input('stock_size'),
                'stock_brand' => $r->input('stock_brand'),
                'stock_type' => $r->input('stock_type'),
                'stock_color' => $r->input('stock_color'),
                'measure_code' => $r->input('measure_code'),
                'stock_min_qty' => $r->input('stock_min_qty'),
                'stock_max_qty' => $r->input('stock_max_qty'),
                'stock_daily_use' => $r->has('stock_daily_use')?1:0
            ]);
        } else 
            return response()->json(Api::response(false,"Data stok tidak ada"),200);

        return response()->json(Api::response(true,"Sukses"),200);
    }

    public function delete(Request $r){
        $stock = Stock::where('stock_code',$r->input('stock_code'))->delete();
        return response()->json(Api::response(true,"Sukses"),200);
    }

    public function grid(Request $r){
        // collect data from post
        $input = $r->input();
        $column_search = [
            'stock_code',
            'stock_name',
            'stock_size',
            'stock_brand',
            'stock_type',
            'stock_color',
            'master_measure.measure_type'
        ];

        // generate default
        if(!isset($input['sort']))
            $input['sort'] = array(
                'sort' => 'asc',
                'field' => 'stock_name'
            );

        // whole query
        $sup = Stock::selectRaw('master.master_stock.*, master.master_measure.measure_type')
        ->join('master.master_measure','master.master_measure.measure_code','=','master.master_stock.measure_code');

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field,['measure_code','stock_daily_use','stock_brand']))
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

    public function brand(){
        return response()->json(Api::response(true,"Sukses",Stock::selectRaw("DISTINCT stock_brand")->get()),200);
    }

    public function autocomplete(Request $r){
        $return = [];
        $stock = ($r->has("find"))?Stock::where('stock_name','LIKE',"%".$r->find."%")->get():Stock::all();
        if($stock->count() > 0)
            foreach($stock as $row){
                $return[] = [
                    'id' => $row->stock_code,
                    'label' => $row->stock_name." - ".$row->stock_size." - ".$row->stock_type
                ];
            }
        return $return;
    }
}