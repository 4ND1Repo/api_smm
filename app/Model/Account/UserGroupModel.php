<?php

namespace App\Model\Account;

use Illuminate\Database\Eloquent\Model;

class UserGroupModel extends Model {

    protected $table = 'account.user_group';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'group_code',
        'group_name',
        'page_code',
        'company_code',
        'department_code',
        'division_code'
    ];

    public $timestamps = false;

}
