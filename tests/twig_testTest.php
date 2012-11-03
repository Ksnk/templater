<?php
/* тырим тесты из тестового набора twig */

if (!defined('PHPUnit_MAIN_METHOD')) {
    ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . dirname(dirname(__FILE__)));
    require 'PHPUnit/Autoload.php';
}

include_once 'header.inc.php';

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

class twig_testTest extends PHPUnit_Framework_TestCase
{
    function _test_tpl($data, $show = false)
    {
        static $classnumber = 340000;
        $classnumber++;
        $calc = new php_compiler();
        $calc->makelex($data['index']);

//        foreach($calc->lex as $v) echo $v->val.'
//';

        $result = $calc->tplcalc('test' . $classnumber);

        if ($show) echo $result . "\n\n";
        //error_log($result."\n\n",3,'log.log');
        eval ('?>' . $result);
        $t = 'tpl_test' . $classnumber;
        $t = new $t();
        $d=array(); if(isset($data['data'])) $d=$data['data'];
        $this->assertEquals( $t->_($d), $data['pattern']);
    }

    function test_5()
    {
        $this->_test_tpl(array(
            'index' => "{{ true? 3+4 :3 }}",
            'pattern' => '7'));
    }

    function test_4()
    {
        $this->_test_tpl(array(
            'index' => "{% set x= { (1 + 1): 'foo', (a ~ 'b'): 'bar' } %} {% for k,i in x -%}
{{ k~' : '~ i }}{% if not loop.last%}, {% endif %}
{% endfor %}",
            'data'=>array('a'=>4),
            'pattern' => '2 : foo,4b : bar'));
    }

    function test_3()
    {
        $this->_test_tpl(array(
            'index' => '{% for i in ["А".."Е","Ё","Ж".."Я"] -%}
{{ i }}{% if not loop.last%},{% endif %}
{% endfor %}',
            'pattern' => 'А,Б,В,Г,Д,Е,Ё,Ж,З,И,Й,К,Л,М,Н,О,П,Р,С,Т,У,Ф,Х,Ц,Ч,Ш,Щ,Ъ,Ы,Ь,Э,Ю,Я'));
    }


    function test_2()
    {
        $this->_test_tpl(array(
            'index' => '{% for i in [5,"A".."K",7] -%}
{{ i }}{% if not loop.last%},{% endif %}
{% endfor %}',
            'pattern' => '5,A,B,C,D,E,F,G,H,I,J,K,7'));
    }

    function test_1()
    {
        $this->_test_tpl(array(
            'index' => '{% for i in [5,0..3,7] -%}
{{ i }}{% if not loop.last%},{% endif %}
{% endfor %}',
            'data' => array(),
            'pattern' => '5,0,1,2,3,7'));
    }

    function testVariables()
    {
        $this->_test_tpl(array(
            'index' => '{% set foo = "foo" %} {{ foo }}
{% set foo = [1, 2] %} {% for index in foo %} {{index}}{% endfor %}
{% set foo = {"foo": "bar","foo1": "bar1"} %}  {% for index,value in foo %} {{index}}:{{value}}
{% endfor %}',
            'data' => array(),
            'pattern' => ' foo 1 2 foo:bar foo1:bar1'));
    }

    function testMacro(){
        $this->_test_tpl(array(
                'index'=>'{%- macro ul_li(name,item) -%}

<li{% if item.active %} class="active"{% endif %}><span class="_states treepoint"></span> <a href="{{item.url}}">{{name}}</a>
    {%- if item.childs %}
    <ul>
        {%- for name1,item1 in item.childs %}
        {{ ul_li(name1,item1) }}
        {% endfor -%}
    </ul>
    {% endif -%}
</li>
{% endmacro -%}

{{ul_li(name,ulli)}}',
            'data' => array('name'=>'xxx','ulli'=>array('url'=>'xxx')),
            'pattern' => '<li><span class="_states treepoint"></span> <a href="xxx">xxx</a></li>'));
    }
    /*
     *
function testMacroError(){  // после первого endif пропущено %
        $this->_test_tpl(array(
                'index'=>'{% macro ul_li(name,item) -%}

<li{% if item.active %} class="active"{% endif }><span class="_states treepoint"></span> <a href="{{item.url}}">{{name}}</a>
    {%- if item.childs %}
        <ul>
         {{ul_li(item.childs)}}
    </ul>
    {% endif -%}
</li>
{% endmacro -%}

{{ul_li(name,ulli)}}',
            'data' => array('name'=>'xxx','ulli'=>array('url'=>'xxx')),
            'pattern' => '<li><span class="_states treepoint"></span> <a href="xxx">xxx</a></li>'));
    }
     */
    /* TODO: сообщение об ошибке, хочиццо
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
     /* TODO: сообщение об ошибке, хочиццо
 function testLipsumError()
 {
     $this->_test_tpl(array(
         'data' => array('func' => 'fileman', 'data' => '<<<>>>'),
         'index' => '{{ lipsum }}',
     'pattern' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi malesuada '),true);

 }
   */
}

if (!defined('PHPUnit_MAIN_METHOD')) {
    $suite = new PHPUnit_Framework_TestSuite('twig_testTest');
    PHPUnit_TextUI_TestRunner::run($suite);
}
?>