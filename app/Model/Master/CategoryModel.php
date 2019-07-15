<?php 

namespace App\Model\Master;

use Illuminate\Database\Eloquent\Model;

class CategoryModel extends Model {

    protected $table = 'master.master_category';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'category_code',
        'category_name'
    ];

    public $timestamps = false;

}
