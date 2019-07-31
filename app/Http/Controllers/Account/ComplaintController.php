<?php

namespace App\Http\Controllers\Account;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model
use App\Model\Document\ComplaintModel AS Complaint;

// Embed a Helper
use DB;
use Illuminate\Http\Request;
use App\Helpers\Api;

class ComplaintController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    public function add(Request $r){
      $query = new Complaint;
      if(!empty($r->complaint_to))
        $query->complaint_to = $r->complaint_to;
      $query->complaint_type = $r->complaint_type;
      $query->complaint_description = htmlentities($r->complaint_description);
      if($r->has('complaint_anonymous'))
        $query->complaint_anonymous = $r->complaint_anonymous;
      $query->create_by = $r->nik;
      $query->save();
      return response()->json(Api::response(true, 'Sukses'),200);
    }

    public function infinite($id, Request $r){
        $r->last=!is_null($r->last)?(int)$r->last:0;

        $q = Complaint::selectRaw("*");

        if($id != "0")
          $q->where('create_by', $id);

        // Order By
        $q->skip((int)$r->last)->take((int)$r->length)->orderBy('complaint_id', 'DESC');

        $content = $q->get();

        $data = [
          'last' => ((int)$r->last + (int)$r->length),
          'content' => $content
        ];
        return response()->json(Api::response(true,'sukses', $data), 200);
    }

}
