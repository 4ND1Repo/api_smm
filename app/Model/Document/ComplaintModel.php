<?php

namespace App\Model\Document;

use Illuminate\Database\Eloquent\Model;

class ComplaintModel extends Model {

    protected $table = 'document.complaint';
    protected $primaryKey = 'complaint_id';

    protected $fillable = [
        'complaint_to',
        'complaint_type',
        'complaint_description',
        'complaint_anonymous',
        'create_by',
        'create_date',
        'status'
    ];

    public $timestamps = false;

}
