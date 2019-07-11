<?php 

namespace App\Model\Account;

use Illuminate\Database\Eloquent\Model;

class UserMenuModel extends Model {

    protected $table = 'account.user_menu';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'company_code',
        'department_code',
        'division_code',
        'id_menu',
    ];

    public $timestamps = false;

}
