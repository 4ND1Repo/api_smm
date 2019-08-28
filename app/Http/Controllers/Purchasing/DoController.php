<?php

namespace App\Http\Controllers\Purchasing;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Document\DoModel AS Delivery;

// Embed a Helper
use DB;
use App\Helpers\{Api, Log};
use Illuminate\Http\Request;



class DoController extends Controller
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

    public function get(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'document.delivery_order.do_code',
            'document.delivery_order.po_code',
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
                'field' => 'document.delivery_order.create_date'
            );

        // whole query
        $query = Delivery::selectRaw('document.delivery_order.*, master.master_stock.*')
            ->join('stock.stock', 'stock.stock.main_stock_code', '=', 'document.delivery_order.main_stock_code')
            ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code');
        if(isset($input['query']['start_date']))
          $query->where('document.delivery_order.create_date', '>=', $input['query']['start_date']." 00:00:00");
        if(isset($input['query']['end_date']))
          $query->where('document.delivery_order.create_date', '<=', $input['query']['end_date']." 23:59:59");

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('stock_brand','stock_type','stock_size','stock_color')) && (!is_null($val) && !empty($val)))
                        $query->where("master.master_stock.".$field,($val=="null"?NULL:urldecode($val)));
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

        $query->orderBy($input['sort']['field'],$input['sort']['sort']);

        $data = $query->get();

        return response()->json($data,200);
    }

    public function grid(Request $r){
        // collect data from post
        $input = $r->input();

        $column_search = [
            'document.delivery_order.do_code',
            'document.delivery_order.po_code',
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
                'field' => 'document.delivery_order.create_date'
            );

        // whole query
        $query = Delivery::selectRaw('document.delivery_order.*, master.master_stock.*')
            ->join('stock.stock', 'stock.stock.main_stock_code', '=', 'document.delivery_order.main_stock_code')
            ->join('master.master_stock', 'master.master_stock.stock_code', '=', 'stock.stock.stock_code');
        if(isset($input['query']['start_date']))
          $query->where('document.delivery_order.create_date', '>=', $input['query']['start_date']." 00:00:00");
        if(isset($input['query']['end_date']))
          $query->where('document.delivery_order.create_date', '<=', $input['query']['end_date']." 23:59:59");

        // where condition
        if(isset($input['query'])){
            if(!is_null($input['query']) and !empty($input['query'])){
                foreach($input['query'] as $field => $val){
                    if(in_array($field, array('stock_brand','stock_type','stock_size','stock_color')))
                        $query->where("master.master_stock.".$field,($val=="null"?NULL:urldecode($val)));
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

        // $query->where();
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
