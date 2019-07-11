<?php 

namespace App\Model\Document;

use Illuminate\Database\Eloquent\Model;

class RequestToolsDetailModel extends Model {

    protected $table = 'document.request_tools_detail';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'req_tools_code',
        'stock_code',
        'req_tools_qty',
        'req_tools_notes'
    ];

    public $timestamps = false;

}
