<?php
/**
 * this file is created automatically at "04 Nov 2020 23:58". Never change anything,
 * for your changes can be lost at any time.
 */ 
class tpl_compiler extends tpl_base {
function __construct(){
parent::__construct();
}

function _ ($par){
$result=($this->_ ($par));
    return $result;
}
}