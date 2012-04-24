<?php
/* тырим тесты из тестового набора twig */

if (!defined('PHPUnit_MAIN_METHOD')) {
    ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . dirname(dirname(__FILE__)));
    require 'PHPUnit/Autoload.php';
}

ini_set('include_path',
    ini_get('include_path')
        . ';' . dirname(dirname(__FILE__)) . '\templates'
        . ';' . dirname(dirname(dirname(__FILE__))) . '\nat2php;' // windows only include!
);

require_once('nat2php.class.php');
require_once('compiler.class.php');
require_once('template_parser.class.php');
require_once('compiler.php.php');
if (!function_exists('ps')) {
    function pps(&$x, $default = '')
    {
        if (empty($x)) return $default; else return $x;
    }
}
if (!class_exists('engine')) {
    class engine
    {
        function export($class, $method, $par1 = null, $par2 = null, $par3 = null)
        {
            return sprintf('calling %s::%s(%s)', $class, $method, array_diff(array($par1, $par2, $par3), array(null)));
        }
    }
}

class twig_testTest //extends PHPUnit_Framework_TestCase
{

/*
    public function testTwigExceptionAddsFileAndLineWhenMissing()
    {
        $loader = new Twig_Loader_Array(array('index' => "\n\n{{ foo.bar }}"));
        $twig = new Twig_Environment($loader, array('strict_variables' => true, 'debug' => true, 'cache' => false));

        $template = $twig->loadTemplate('index');

        try {
            $template->render(array());

            $this->fail();
        } catch (Twig_Error_Runtime $e) {
            $this->assertEquals('Variable "foo" does not exist in "index" at line 3', $e->getMessage());
            $this->assertEquals(3, $e->getTemplateLine());
            $this->assertEquals('index', $e->getTemplateFile());
        }
    } */
}

if (!defined('PHPUnit_MAIN_METHOD')) {
    $suite = new PHPUnit_Framework_TestSuite('twig_testTest');
    PHPUnit_TextUI_TestRunner::run($suite);
}
?>