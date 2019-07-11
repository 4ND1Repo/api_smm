<?php 

namespace App\Model\Master;

use Illuminate\Database\Eloquent\Model;

class CityModel extends Model {

    protected $table = 'master.master_city';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'city_code',
        'city_name',
        'status_code'
    ];

    public $timestamps = false;

}
