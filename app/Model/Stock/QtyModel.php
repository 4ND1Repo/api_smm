<?php 

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class QtyModel extends Model {

    protected $table = 'stock.qty';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'stock_code',
        'qty',
        'nik',
        'stock_date',
        'po_code',
        'stock_notes'
    ];

    public $timestamps = false;

    public function stock(){
        return $this->belongsTo('App\Model\Master\StockModel','stock_code','stock_code');
    }

    public function user(){
        return $this->belongsTo('App\Model\Account\UserModel','nik','nik');
    }

}
