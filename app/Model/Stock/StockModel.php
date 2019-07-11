<?php 

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class StockModel extends Model {

    protected $table = 'stock.stock';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'main_stock_code',
        'stock_code',
        'menu_page',
        'nik',
        'main_stock_date'
    ];

    public $timestamps = false;

    public function stock(){
        return $this->belongsTo('App\Model\Master\StockModel','stock_code','stock_code');
    }

    public function user(){
        return $this->belongsTo('App\Model\Account\UserModel','nik','nik');
    }

    public function page(){
        return $this->belongsTo('App\Model\Master\PageModel','page_code','menu_page');
    }

}
