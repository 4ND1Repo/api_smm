<?php 

namespace App\Model\Master;

use Illuminate\Database\Eloquent\Model;

class StockModel extends Model {

    protected $table = 'master.master_stock';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'stock_code',
        'stock_name',
        'stock_size',
        'stock_brand',
        'stock_type',
        'stock_color',
        'measure_code',
        'stock_price',
        'stock_deliver_price',
        'stock_qty',
        'stock_daily_use'
    ];

    public $timestamps = false;

    public function measure(){
        return $this->belongsTo('App\Model\Master\MeasureModel','measure_code','measure_code');
    }

}
