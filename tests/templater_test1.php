<?php

include_once '../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

include_once 'header.inc.php';


function pps(&$x, $default = '')
{
    if (empty($x)) return $default; else return $x;
}

class engine
{
    function export($class, $method, $par1 = null, $par2 = null, $par3 = null)
    {
        return sprintf('calling %s::%s(%s)', $class, $method, array_diff(array($par1, $par2, $par3), array(null)));
    }
}

$GLOBALS['engine'] = new engine();

class Test_Templater extends TestCase {

	var $this_template='../templates/compiler.jtpl';

	function compile_it($class_name='compiler'){
		static $compiler;
		if(empty($compiler))
			$compiler=new \Ksnk\templater\template_compiler();
		if(!class_exists($t='tpl_'.$class_name, false)){
			if(is_file($this->this_template)){
				$x=$compiler->compile_tpl(file_get_contents($this->this_template),$class_name);
                file_put_contents($t.'.php',str_replace('class tpl_test extends','class '.$t.' extends',$x));
                include($t.'.php');

                echo'<pre>'.htmlspecialchars($x).'</pre>';
				return $x;
			}
		}
		return '';
	}

	// тестируемые данные

	/**
	 * тестируем оттранслированный шаблон
	 */
	function test_tpl_set(){
		$compiler=$this->compile_it('test');
		$compiler=$this->compile_it('test1');
		$compiler1=$this->compile_it('test2');
		//if(!empty($_GET['compile'])){
			$s=str_replace('class tpl_test2','class tpl_compiler',$compiler1);
			if(!empty($s)){
		        echo 'xxx!';
		        file_put_contents ('../templates/tpl_compiler.php',$s);
			}
		//}
		$this->assertEquals(
			 str_replace('tpl_test1','tpl_test2',$compiler)
			,$compiler1
		);
	}
}
