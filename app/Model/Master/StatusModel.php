<?php 

namespace App\Model\Master;

use Illuminate\Database\Eloquent\Model;

class StatusModel extends Model {

    protected $table = 'master.master_status';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'status_code',
        'status_label'
    ];

    public $timestamps = false;

}
