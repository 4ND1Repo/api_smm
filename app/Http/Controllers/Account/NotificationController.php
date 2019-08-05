<?php

namespace App\Http\Controllers\Account;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Document\NotificationModel AS Notification;
use App\Model\Master\PageModel AS Page;

// Embed a Helper
use DB;
use Illuminate\Http\Request;
use App\Helpers\Api;

class NotificationController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    public function read(Request $r){
      Notification::find($r->id)->update(['notification_read' => 1]);
      return response()->json(Api::response(true, 'Sukses'),200);
    }

    public function add(Request $r){
      $to = $r->notification_to;
      // $to = [];
      // $tmp = [];
      // $q = Page::select('page_code')->all()
      // foreach ($q as $i => $r) {
      //   $tmp[] = $r->page_code;
      // }
      // if(in_array($r->notification_to,$tmp)){
      //
      // }

      $q = new Notification;
      $q->notification_to = $to;
      if($q->has('notification_from'))
        $q->notification_from = (!empty($r->notification_from)?$r->notification_from:NULL);
      if($q->has('notification_title'))
        $q->notification_title = $r->notification_title;
      if($q->has('notification_url'))
        $q->notification_url = $r->notification_url;
      if($q->has('notification_icon'))
        $q->notification_icon = $r->notification_icon;

      $q->notification_content = $r->notification_content;
      $q->save();
      return response()->json(Api::response(true, 'Sukses'),200);
    }

    public function user(Request $r){
      $data = [
        'count' => 0,
        'content' => []
      ];
      if($r->has('nik')){
        $where = ['notification_to' => $r->nik];
        if($r->init == 0)
        $where['notification_send'] = 0;

        $query = Notification::where($where);
        $query2 = Notification::where($where)->take(10)->orderBy('notification_id', 'DESC');

        $data['count'] = $query->count();
        $data['content'] = ($query->count() > 0 || $r->init == 1)? $query2->get() : [];
        if($data['count'] > 0){
          $arrId = [];
          foreach ($data['content'] as $id => $row) {
            $arrId[] = $row->notification_id;
          }
          Notification::whereIn('notification_id', $arrId)->update(['notification_send' => 1]);
        }
      }
      return response()->json(Api::response(true, 'Sukses',$data),200);
    }

    public function infinite($id, Request $r){
        $r->last=!is_null($r->last)?(int)$r->last:0;

        $q = Notification::selectRaw("*");

        if($id != "0")
          $q->where('notification_to', $id);

        // Order By
        $q->skip((int)$r->last)->take((int)$r->length)->orderBy('notification_id', 'DESC');

        $content = $q->get();

        $data = [
          'last' => ((int)$r->last + (int)$r->length),
          'content' => $content
        ];
        return response()->json(Api::response(true,'sukses', $data), 200);
    }

}
