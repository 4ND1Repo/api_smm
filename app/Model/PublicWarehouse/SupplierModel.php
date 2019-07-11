<?php 

namespace App\Model\PublicWarehouse;

use Illuminate\Database\Eloquent\Model;

class SupplierModel extends Model {

    protected $table = 'dbo.supplier';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'kode_sup',
        'nm_sup',
        'alamat',
        'status',
        'kota',
        'telepon',
        'katagori'
    ];

    public $timestamps = false;
}
