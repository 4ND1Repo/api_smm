<?php 

namespace App\Model\Master;

use Illuminate\Database\Eloquent\Model;

class CompanyModel extends Model {

    protected $table = 'master.master_company';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'company_code',
        'company_name',
        'company_description'
    ];

    public $timestamps = false;

}
