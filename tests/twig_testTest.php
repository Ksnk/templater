<?php
/* тырим тесты из тестового набора twig */

include_once '../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

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

class twig_testTest extends TestCase
{
    function _test_tpl($data, $show = false, $exception='')
    {
        static $classnumber = 340000;
        $classnumber++;
        $calc = new \Ksnk\templater\php_compiler();
        $calc->namespace = 'Ksnk\templates';
        $calc->basenamespace = 'Ksnk\templater';
        $calc->makelex($data['index']);
        if(!empty($exception)) {
            $this->expectExceptionMessage($exception);
        }
        $result = $calc->tplcalc('test' . $classnumber);

        if ($show) echo $result . "\n\n";
        //error_log($result."\n\n",3,'log.log');
        eval ('?>' . $result);
        $t = '\\'.$calc->namespace.'\test' . $classnumber;
        $t = new $t();
        $d=array(); if(isset($data['data'])) $d=$data['data'];
        if(isset($data['startpattern'])){
            $x=$t->_($d);
            $this->assertEquals( $data['startpattern'],substr($x,0,strlen($data['startpattern'])) );
        } else {
            $this->assertEquals( $data['pattern'],$t->_($d));
        }

    }

    function test2ndslice(){
        $this->_test_tpl(array(
            'index'=>'!{{data[0].index}} +{{data[0]["index"]}}',
            'data' => ['data'=>[['index'=>'xxx']]],
            'pattern' => '!xxx +xxx'));
    }

    function test_6(){
        $this->_test_tpl(array(
            'index' => '<select class="input" >
            {% for k,option in field.values %}
                {% if k!=\'id\' and k!=\'record\' and k!=\'name\' %}
                <option{% if value==option %} selected{% endif %}>{{ option }}</option>
                {% endif %}
            {% endfor %}
            </select>',
            'pattern' => '<select class="input" >
                <option selected>ulli</option>
                <option>select</option>
            </select>',
            'data'=>[
                'value'=>'ulli',
                'field'=>['values'=>['text','ulli','select']]
            ]));
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
            'index' => '{% set foo = "foo" %} {{ foo -}}
{% set foo = [1, 2] %} {% for index in foo %} {{index}}{% endfor %}
{% set foo = {"foo": "bar","foo1": "bar1"} %}  {% for index,value in foo %} {{index}}:{{value}}
{%- endfor -%}',
            'data' => array(),
            'pattern' => ' foo  1 2   foo:bar foo1:bar1'));
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
{%- endmacro -%}

{{ul_li(name,ulli)}}',
            'data' => array('name'=>'xxx','ulli'=>array('url'=>'xxx')),
            'pattern' => '<li><span class="_states treepoint"></span> <a href="xxx">xxx</a></li>'));
    }


function testMacroError(){  // после первого endif пропущено %
        $this->_test_tpl([
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
            'pattern' => '<li><span class="_states treepoint"></span> <a href="xxx">xxx</a></li>'
        ],
            false,
            'there is no endmacro tag');
    }
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

 function testLipsumError()
 {
     $this->_test_tpl(array(
         'data' => array('func' => 'fileman', 'data' => '<<<>>>'),
         'index' => '{{ lipsum() }}',
     'startpattern' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi malesuada '));

 }

    function testTextUtility(){
        $this->_test_tpl(array(
                'data' => array('number' => '25345', 'data' => '<<<>>>'),
                'index' => "{{number}} копе{{number|finnumb('ка','йки','ек')}}",
                'pattern' => '25345 копеек')
        );

    }
    function testTextUtility1(){
        $this->_test_tpl(array(
                'data' => array('number' => '2534', 'data' => '<<<>>>'),
                'index' => "{{ number }} огур{{ number | russuf('ец|ца|цов')}}",
                'pattern' => '2534 огурца')
        );

    }

    function testTextUtility3(){
        $this->_test_tpl(array(
                'data' => array('date' => '1/12/2020'),
                'index' => "{{ date|date('j F, Y г.')}} ",
                'pattern' => '12 января, 2020 г. ')
        );

    }

}

