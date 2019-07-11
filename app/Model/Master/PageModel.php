<?php 

namespace App\Model\Master;

use Illuminate\Database\Eloquent\Model;

class PageModel extends Model {

    protected $table = 'master.master_page';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'page_code',
        'page_name'
    ];

    public $timestamps = false;

}
