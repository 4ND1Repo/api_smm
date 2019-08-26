<?php

namespace App\Model\Master;

use Illuminate\Database\Eloquent\Model;

class ActivityModel extends Model {

    protected $table = 'master.master_activity';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'activity_code',
        'activity_type'
    ];

    public $timestamps = false;

}
