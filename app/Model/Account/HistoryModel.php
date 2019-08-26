<?php

namespace App\Model\Account;

use Illuminate\Database\Eloquent\Model;

class HistoryModel extends Model {

    protected $table = 'account.history';
    protected $primaryKey = 'history_id';

    protected $fillable = [
        'history_id',
        'nik',
        'activity_code',
        'history_description',
        'history_date'
    ];

    public $timestamps = false;

}
