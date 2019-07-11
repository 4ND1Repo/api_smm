<?php 

namespace App\Model\Master;

use Illuminate\Database\Eloquent\Model;

class CabinetModel extends Model {

    protected $table = 'master.master_cabinet';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'cabinet_code',
        'cabinet_name',
        'cabinet_description',
        'menu_page',
        'status_code'
    ];

    public $timestamps = false;

}
