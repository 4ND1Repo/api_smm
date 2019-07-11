<?php 

namespace App\Model\Document;

use Illuminate\Database\Eloquent\Model;

class RequestToolsModel extends Model {

    protected $table = 'document.request_tools';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'req_tools_code',
        'menu_page',
        'req_tools_date',
        'req_nik',
        'create_by',
        'create_date',
        'status',
        'finish_by',
        'finish_date'
    ];

    public $timestamps = false;

}
