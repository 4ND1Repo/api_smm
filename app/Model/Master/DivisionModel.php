<?php 

namespace App\Model\Master;

use Illuminate\Database\Eloquent\Model;

class DivisionModel extends Model {

    protected $table = 'master.master_division';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'division_code',
        'department_code',
        'company_code',
        'division_name',
        'division_description'
    ];

    public $timestamps = false;

}
