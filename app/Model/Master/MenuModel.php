<?php 

namespace App\Model\Master;

use Illuminate\Database\Eloquent\Model;

class MenuModel extends Model {

    protected $table = 'master.master_menu';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'id_menu',
        'menu_page',
        'menu_name',
        'menu_url',
        'menu_icon',
        'id_parent'
    ];

    public $timestamps = false;

}
