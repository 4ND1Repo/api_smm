<?php

namespace App\Helpers;

Class Converter {
    public static function fromID($dt){
        if(strpos($dt, '/') != -1){
            list($date, $month, $year) = explode("/",$dt);
            return implode("-",[$year, $month, $date]);
        }
    }
}
