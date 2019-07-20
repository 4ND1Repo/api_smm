<?php

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class OpnameModel extends Model {

    protected $table = 'stock.opname';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'main_stock_code',
        'opname_qty_from',
        'opname_qty_date',
        'opname_qty',
        'opname_notes',
        'create_by',
        'create_date',
        'approve_by',
        'approve_date',
        'reject_by',
        'reject_date'
    ];

    public $timestamps = false;

}
