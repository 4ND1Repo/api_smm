<?php

namespace App\Helpers;

Class Api {

    // response default format
    public static function response($s=true,$m=null,$d=[]){
        return ['status'=>is_bool($s)?($s?1:0):($s==1?1:0),'message'=>is_string($m)?$m:null,'data'=>$d];
    }

}