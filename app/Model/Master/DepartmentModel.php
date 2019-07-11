<?php 

namespace App\Model\Master;

use Illuminate\Database\Eloquent\Model;

class DepartmentModel extends Model {

    protected $table = 'master.master_department';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'department_code',
        'company_code',
        'department_name',
        'department_description'
    ];

    public $timestamps = false;

}
