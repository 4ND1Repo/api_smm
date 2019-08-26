<?php

namespace App\Helpers;

use DB;
use App\Model\Account\HistoryModel AS History;
use App\Model\Master\ActivityModel AS Act;

Class Log {

    private static $default_var = [
      'activity_code' => NULL,
      'nik' => NULL,
      'history_description' => NULL
    ];

    // private process on behind
    private static function process(){
      foreach (self::$default_var as $key) {
        if(is_null($key)){
          return false;
        }
      }

      if(History::insert(self::$default_var))
        return true;

      return false;
    }

    private static function check_var($opt){
      // check activity type
      if(isset($opt['type'])){
        $q = Act::where(['activity_type' => $opt['type']]);
        if($q->count() > 0){
          $tmp = $q->first();
          self::$default_var['activity_code'] = $tmp->activity_code;
        }
      }

      self::$default_var['nik'] = (isset($opt['nik'])?$opt['nik']: NULL);
      self::$default_var['history_description'] = (isset($opt['description'])?$opt['description']: NULL);
      return true;
    }

    public static function add($opt = []){
      // return self::check_var($opt);
      if(self::check_var($opt)){
        if(self::process()){
          return ['status' => 1, 'message' => 'success'];
        }
        return ['status' => 0, 'message' => 'Failed to process'];
      }
      return ['status' => 0, 'message' => 'Error data'];
    }
}
