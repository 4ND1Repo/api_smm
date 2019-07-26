<?php

namespace App\Model\Master;

use Illuminate\Database\Eloquent\Model;

class IconModel extends Model {

    protected $table = 'master.master_icon';
    protected $primaryKey = 'icon_id';

    protected $fillable = [
        'icon_name'
    ];

    public $timestamps = false;

}
