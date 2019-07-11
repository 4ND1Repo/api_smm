<?php 

namespace App\Model\Account;

use Illuminate\Database\Eloquent\Model;

class UserModel extends Model {

    protected $table = 'account.user';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'nik',
        'pwd_hash',
        'company_code',
        'department_code',
        'division_code',
        'last_login',
        'status_code'
    ];

    public $timestamps = false;

    public function status(){
        return $this->belongsTo('App\Model\Account\StatusModel','status_code','status_code');
    }

    public function company(){
        return $this->belongsTo('App\Model\Account\CompanyModel','company_code','company_code');
    }

    public function department(){
        return $this->belongsTo('App\Model\Account\DepartmentModel','department_code','department_code');
    }

    public function division(){
        return $this->belongsTo('App\Model\Account\DivisionModel','division_code','division_code');
    }

}
