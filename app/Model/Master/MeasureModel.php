<?php 

namespace App\Model\Master;

use Illuminate\Database\Eloquent\Model;

class MeasureModel extends Model {

    protected $table = 'master.master_measure';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'measure_code',
        'measure_type'
    ];

    public $timestamps = false;

}
