<?php
//require_once('/simpletest/autorun.php');
    require_once('/simpletest/unit_tester.php');
    require_once('/simpletest/shell_tester.php');
    require_once('/simpletest/mock_objects.php');
    require_once('/simpletest/reporter.php');
    require_once('/simpletest/xml.php');

// define path to include all useful files
// windows only
ini_set('include_path',
  ini_get('include_path')
  .';../../nat2php;..' // windows only include!
);
require_once('nat2php.class.php');
require_once('compiler.class.php');
require_once('template_parser.class.php');
require_once('compiler.php.php');

function pps(&$x,$default=''){return empty($x)?$default:$x;}

class Test_Templater extends UnitTestCase {
	
	var $this_template='../templates/compiler.jtpl';

	function compile_it($class_name='compiler'){
		static $compiler;
		if(empty($compiler))
			$compiler=new template_compiler();
		if(!class_exists('tpl_'.$class_name)){
			if(is_file($this->this_template)){
				$x=$compiler->compile_tpl(file_get_contents($this->this_template),$class_name);
				echo'<pre>'.htmlspecialchars($x).'</pre>';
				eval('?>'.$x);
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
		if(!empty($_GET['compile'])){
			$s=str_replace('class tpl_test2','class tpl_compiler',$compiler1);
			if(!empty($s)){
		        echo 'xxx';
		        file_put_contents ('../templates/tpl_compiler.php',$s);
			}
		}
		$this->assertEqual(
			 str_replace('tpl_test1','tpl_test2',$compiler)
			,$compiler1
		);
	}
}

	$test = &new TestSuite('testing how engine dealing with compiler.jtpl ');
    $test->addTestCase(new Test_Templater());
    if (isset($_GET['xml']) || in_array('xml', (isset($argv) ? $argv : array()))) {
        $reporter = &new XmlReporter();
    } elseif (TextReporter::inCli()) {
        $reporter = &new TextReporter();
    } else {
        $reporter = &new HTMLReporter();
    }
    if (isset($_GET['dry']) || in_array('dry', (isset($argv) ? $argv : array()))) {
        $reporter->makeDry();
    }
    exit ($test->run($reporter) ? 0 : 1);
?>