<?php

namespace App\Model\Account;

use Illuminate\Database\Eloquent\Model;

class UserBiodataModel extends Model {

    protected $table = 'account.user_biodata';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'nik',
        'first_name',
      	'last_name',
      	'call_name',
      	'birth_date',
      	'marital_code',
      	'child',
      	'email',
      	'address',
      	'phone',
        'handphone'
    ];

    public $timestamps = false;

}
