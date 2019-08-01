<?php

namespace App\Model\Document;

use Illuminate\Database\Eloquent\Model;

class PoModel extends Model {

    protected $table = 'document.purchase_order';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
        'po_code',
        'menu_page',
        'menu_page_destination',
        'po_date',
        'nik',
        'create_by',
        'create_date',
        'finish_by',
        'finish_date',
        'status',
        'reason'
    ];

    public $timestamps = false;

}
