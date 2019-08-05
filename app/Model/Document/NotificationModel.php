<?php

namespace App\Model\Document;

use Illuminate\Database\Eloquent\Model;

class NotificationModel extends Model {

    protected $table = 'document.notification';
    protected $primaryKey = 'notification_id';

    protected $fillable = [
        'notification_to',
        'notification_from',
        'notification_read',
        'notification_send',
        'notification_time',
        'notification_icon',
        'notification_title',
        'notification_content'
    ];

    public $timestamps = false;

}
