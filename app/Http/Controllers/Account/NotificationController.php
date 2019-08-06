<?php

namespace App\Http\Controllers\Account;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Document\NotificationModel AS Notification;
use App\Model\Master\PageModel AS Page;
use App\Model\Account\UserGroupModel AS UserGroup;
use App\Model\Account\UserModel AS User;

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
      $to = [];

      if(!is_array($r->notification_to)){
        $q = UserGroup::where(function($q) use($r){
          $q->where('group_code', $r->notification_to);
          $q->orWhere('page_code', $r->notification_to);
          $q->orWhere('company_code', $r->notification_to);
          $q->orWhere('department_code', $r->notification_to);
          $q->orWhere('division_code', $r->notification_to);
        })->get();
        if($q->count() > 0){
          $tmp = [];
          foreach ($q as $i => $dt) {
            $tmp[] = $dt->group_code;
          }

          $q = User::whereIn('group_code',$tmp)->get();
          if($q->count() > 0){
            foreach ($q as $i => $dt) {
              $to[] = $dt->nik;
            }
          }
        } else
          $to[] = $r->notification_to;
      } else{
        $to = $r->notification_to;
      }

      if(count($to) > 0) {
        foreach ($to as $i => $nik) {
          $q = new Notification;
          $q->notification_to = $nik;
          if($r->has('notification_from'))
          $q->notification_from = (!empty($r->notification_from)?$r->notification_from:NULL);
          if($r->has('notification_title'))
          $q->notification_title = $r->notification_title;
          if($r->has('notification_url'))
          $q->notification_url = $r->notification_url;
          if($r->has('notification_icon'))
          $q->notification_icon = $r->notification_icon;

          $q->notification_content = $r->notification_content;
          $q->save();
        }
      }
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
        $cnt = $query->count();
        $query2 = Notification::where($where);
        if($r->init == 1)
          $query2->skip(($cnt-10));
        $query2->take(10)->orderBy('notification_id', 'ASC');

        $data['count'] = $cnt;
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
