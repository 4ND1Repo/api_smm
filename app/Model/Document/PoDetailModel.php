<?php

namespace App\Model\Document;

use Illuminate\Database\Eloquent\Model;

class PoDetailModel extends Model {

    protected $table = 'document.purchase_order_detail';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'po_code',
        'main_stock_code',
        'po_qty',
        'supplier_code',
        'stock_price',
        'stock_delivery_price',
        'po_old_qty',
        'po_notes'
    ];

    public $timestamps = false;

}
