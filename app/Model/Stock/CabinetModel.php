<?php 

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class CabinetModel extends Model {

    protected $table = 'stock.cabinet';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'stock_cabinet_code',
        'page_code',
        'cabinet_code',
        'stock_code'
    ];

    public $timestamps = false;

    public function cabinet(){
        return $this->belongsTo('App\Model\Master\CabinetModel','cabinet_code','cabinet_code');
    }

    public function stock(){
        return $this->belongsTo('App\Model\Master\StockModel','stock_code','stock_code');
    }

}
