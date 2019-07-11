<?php 

namespace App\Model\Master;

use Illuminate\Database\Eloquent\Model;

class SupplierModel extends Model {

    protected $table = 'master.master_supplier';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'supplier_code',
        'supplier_name',
        'supplier_address',
        'city_code',
        'supplier_phone',
        'supplier_category',
        'status_code'
    ];

    public $timestamps = false;

}
