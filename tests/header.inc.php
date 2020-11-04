<?php

define('SYSTEM_PATH', str_replace('\\','/',dirname(dirname(__FILE__))) . '/build/lib');
/*
ini_set('include_path',
ini_get('include_path')
. ';' . dirname(dirname(__FILE__)) . '\templates'
. ';' . dirname(dirname(dirname(__FILE__))) . '\nat2php;' // windows only include!
);
 */
function autoload($x){
    if(is_file($x.'.php'))
        include($x.'.php');
    elseif(is_file('../'.$x.'.php'))
        include('../'.$x.'.php');
    elseif(is_file('../templates/'.$x.'.php'))
        include('../templates/'.$x.'.php');
    elseif(is_file(SYSTEM_PATH.'/../render/'.$x.'.php'))
        include(SYSTEM_PATH.'/../render/'.$x.'.php');
}

spl_autoload_register('autoload');
//  echo 'getcwd-'.getcwd();
//require_once(SYSTEM_PATH.'/nat2php.class.php');
//require_once(SYSTEM_PATH.'/compiler.class.php');
//require_once(SYSTEM_PATH.'/template_parser.class.php');
//require_once(SYSTEM_PATH.'/compiler.php.php');
