<?php
/**
 * helper class to check template modification time
 * <%=point('hat','jscomment');
// эти пустые строки оставлены для того, чтобы номера строк совпадали   
  
  
  
 %>
 */

/**
 * helper function to check if value is empty
 */
if(!function_exists('pps')){
function pps(&$x,$default=''){if(empty($x))return $default; else return $x;}
}

class template_compiler {

    static $filename='';
	
	static function do_prepare(){
		static $done;
		if(!empty($done)) return;
		require_once 'nat2php.class.php' ;
		require_once 'template_parser.class.php' ;
		require_once 'compiler.php.php' ;
		$done = true;
	} 
	
	/**
	 * функция компиляции текста. Результатом будет 
	 * текст функции, для вставки в шаблон 
	 * @param string $tpl
	 */
	static function compile_tpl($tpl,$name='compiler'){
		static $calc;
		if (empty($calc)){
      		$calc=new php_compiler();
		}
		//compile it;
		$result = '';
		try{
			$calc->makelex($tpl);
			$result=$calc->tplcalc($name);
		} catch(Exception $e){
			echo $e->getMessage();
			echo '<pre> filename:'.self::$filename.'<br>';print_r($calc);echo'</pre>';
			return null;
		}
		//execute it
		return $result;
		
	} 
	
	/**
	 * проверка даты изменения шаблона-образца
	 */
	static function checktpl(){
		static $include_done;
		$time=microtime(true);
		$templates=glob(TEMPLATE_PATH.DIRECTORY_SEPARATOR.'*.jtpl');
		//print_r('xxx'.$templates);echo " !";
		$xtime=filemtime(__FILE__);
		if(!empty($templates)){
			foreach($templates as $v){
				$name=basename($v,".jtpl");
				$phpn='tpl_'.$name;
				//echo($phpn.' '.$v);
				if( !file_exists(TEMPLATE_PATH.DIRECTORY_SEPARATOR.$phpn.'.php')
					||
				  	(max($xtime,filemtime($v))>filemtime(TEMPLATE_PATH.DIRECTORY_SEPARATOR.$phpn.'.php'))
				){
					if(empty($include_done)) {
						$include_done=true;
						require_once 'nat2php.class.php' ;
						require_once 'template_parser.class.php' ;
						require_once 'compiler.php.php' ;
					}
					self::$filename=$v;
					$x=self::compile_tpl(file_get_contents($v),$name);
					if(!!$x)
						file_put_contents(TEMPLATE_PATH.DIRECTORY_SEPARATOR.$phpn.'.php'
							,$x
						);
				}
			}
		}
		$time = microtime(true) - $time;
		//echo $time.' sec spent';
	}

}

