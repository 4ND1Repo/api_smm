<?php

namespace App\Model\Document;

use Illuminate\Database\Eloquent\Model;

class BorrowModel extends Model {

    protected $table = 'document.borrowed';
    // protected $primaryKey = 'id_authorization_company';

    protected $fillable = [
      'borrowed_code',
      'main_stock_code',
      'borrowed_qty',
      'borrowed_date',
      'borrowed_long_term',
      'borrowed_notes',
      'nik',
      'take_nik',
      'create_by',
      'create_date',
      'finish_by',
      'finish_date',
      'status'
    ];

    public $timestamps = false;

}
