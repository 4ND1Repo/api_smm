<?php

namespace App\Model\Document;

use Illuminate\Database\Eloquent\Model;

class DoModel extends Model {

    protected $table = 'document.delivery_order';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'do_code',
        'po_code',
        'page_code',
        'main_stock_code',
        'do_qty',
        'create_by',
        'create_date'
    ];

    public $timestamps = false;

}
