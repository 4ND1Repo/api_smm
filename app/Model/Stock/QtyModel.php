<?php

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class QtyModel extends Model {

    protected $table = 'stock.qty';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'main_stock_code',
        'qty',
        'nik',
        'stock_date',
        'stock_price',
        'supplier_code',
        'do_code',
        'stock_notes'
    ];

    public $timestamps = false;

    public function stock(){
        return $this->belongsTo('App\Model\Stock\StockModel','main_stock_code','main_stock_code');
    }

    public function user(){
        return $this->belongsTo('App\Model\Account\UserModel','nik','nik');
    }

}
