<?php

namespace App\Http\Controllers\General;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Master\StockModel AS MasterStock;
use App\Model\Stock\StockModel AS Stock;
use App\Model\Stock\QtyModel AS Qty;
use App\Model\Stock\QtyOutModel AS QtyOut;
use App\Model\Document\RequestToolsModel AS ReqTools;
use App\Model\Document\PoModel AS PO;
use App\Model\Document\DoModel AS Delivery;
use App\Model\Document\ComplaintModel AS Complaint;
use App\Model\Document\RequestToolsDetailModel AS ReqToolsDetail;

// Embed a Helper
use DB;
use App\Helpers\Api;
use Illuminate\Http\Request;
use DateTime;
use DateInterval;



class ChartController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
        $this->month = [
          'id' => ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
        ];
    }

    public function getMonth($r, $a=0){
      $tmp = [];
      if($a>0)
        for($i=0; $i<$a; $i++){
          $date = new DateTime();
          $date->add(new DateInterval('P'.($i+1).'M'));
          $tmp[] = $date->format('Y|m');
        }
      for($i=0; $i<$r; $i++){
        $date = new DateTime();
        $date->sub(new DateInterval('P'.$i.'M'));
        $tmp[] = $date->format('Y|m');
      }
      $ret = [];
      foreach ($tmp as $i => $row) {
        list($year, $m) = explode('|',$row);
        $ret[] = $this->month['id'][((int)$m-1)].' '.$year;
      }
      return $ret;
    }

    public function reverseArray($d){
      $ret = [];
      krsort($d);
      foreach ($d as $i => $v) {
        $ret[] = $v;
      }
      return $ret;
    }

    public function stock_out(Request $r){
      $index = 8;
      $data = [
        'label' => $this->reverseArray($this->getMonth($index,1)),
        'data'=> [0]
      ];

      for ($i=0; $i < $index; $i++) {
          $date = new DateTime();
          $date->sub(new DateInterval('P'.$i.'M'));
          $start = $date->format('Y-m-01 00:00:00');
          $end = date("Y-m-t 23:59:59", strtotime($start));
          // query get data
          $query = QtyOut::selectRaw('COUNT(stock.qty_out.stock_out_date) AS cnt')->join('stock.stock', 'stock.stock.main_stock_code', '=', 'stock.qty_out.main_stock_code')->whereBetween('stock_out_date', [$start, $end]);
          if($r->has('page_code'))
            $query->where('page_code', $r->page_code);

          $data['data'][] = $query->first()->cnt;
      }
      $data['data'] = $this->reverseArray($data['data']);
      return response()->json(Api::response(true, 'sukses', $data),200);
    }

    public function request_tools(Request $r){
      $start = date("Y-m-01 00:00:00");
      $end = date("Y-m-t 23:59:59");
      $data = ReqTools::selectRaw("COUNT(create_date) AS cnt")->where(['page_code' => $r->page_code])->whereBetween('create_date', [$start, $end])->first()->cnt;
      return $data;
    }

    public function po(Request $r){
      $start = date("Y-m-01 00:00:00");
      $end = date("Y-m-t 23:59:59");
      $data = PO::selectRaw("COUNT(create_date) AS cnt")->where(['page_code' => $r->page_code])->whereBetween('create_date', [$start, $end])->first()->cnt;
      return $data;
    }

    public function complaint(Request $r){
      $start = date("Y-m-01 00:00:00");
      $end = date("Y-m-t 23:59:59");
      $data = Complaint::selectRaw("COUNT(create_date) AS cnt")->where(['complaint_to' => $r->nik])->whereBetween('create_date', [$start, $end])->first()->cnt;
      return $data;
    }

    public function delivery(Request $r){
      $start = date("Y-m-01 00:00:00");
      $end = date("Y-m-t 23:59:59");
      $data = Delivery::selectRaw("COUNT(create_date) AS cnt")->where(['page_code' => $r->page_code])->whereBetween('create_date', [$start, $end])->first()->cnt;
      return $data;
    }

    public function dashboard_data(Request $r){
      return response()->json(Api::response(true,'sukses',[
        'request_tools' => $this->request_tools($r),
        'po' => $this->po($r),
        'complaint' => $this->complaint($r),
        'do' => $this->delivery($r),
      ]),200);
    }

}
