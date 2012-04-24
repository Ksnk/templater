<?php

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

class templateTest extends PHPUnit_Framework_TestCase
{

    function compress($s)
    {
        return preg_replace('/\s+/s', ' ', $s);
    }

    /**
     * тестирование шаблона с генерацией нового класса
     */
    function _test_cmpl($tpl, $data, $show = false)
    {
        static $classnumber = 10;
        $classnumber++;
        $calc = new php_compiler();
        $calc->makelex($tpl);

//        foreach($calc->lex as $v) echo $v->val.'
//';

//		try {
        $result = $calc->tplcalc('test' . $classnumber);

        if ($show) echo $result . "\n\n";
        //error_log($result."\n\n",3,'log.log');
        eval ('?>' . $result);
        $t = 'tpl_test' . $classnumber;
        $t = new $t();
        return $t->_($data);
        /*		} catch(Exception $e) {
              print_r($e->getMessage());echo'</pre>';
              return 'XXX';
          }*/
    }

    /**
     * тестирование шаблона без трансляции нового класса
     */
    function _test_tpl($tpl, $data, $show = false)
    {
        $calc = new php_compiler();
        $calc->makelex($tpl);
//        foreach($calc->lex as $v) echo $v->val.'
//';
//		try {
        $result = $calc->block_internal();
        $x = '$result="";' . $calc->popOp()->val . ' return $result;';
        if ($show) echo $x . "\n\n";
        //if (isset($_GET['debug']))
        //	error_log($x,3,'log.log');
        $fnc = create_function('&$par', $x);
        return $fnc($data);
        /*		} catch(Exception $e){
              print_r($e->getMessage());echo'</pre>';
              return 'XXX';
          }*/
    }

    function test_test13()
    {
        $data = array();
        $s = '
        {%- for item in ["on\\\\e\'s ","one\"s "] -%}
    {{ loop.index }}{{ item }}{{ loop.revindex }}{{ item }}
{%- endfor %}';
        $pattern = '1on\\e\'s 2on\\e\'s 2one"s 1one"s ';
        $this->assertEquals(
            $this->_test_tpl($s, $data), $pattern
        );
    }

    function test_test25()
    {
        $data = array(
            'topics' => array(
                'topic1' => array('Message 1 of topic 1', 'Message 2 of topic 1'),
                'topic2' => array('Message 1 of topic 2', 'Message 2 of topic 2'),
            ));
        $s = '{% for topic, messages in topics %}
       * {{ loop.index }}: {{ topic }}
     {% for message in messages %}
         - {{ loop.parent.loop.index }}.{{ loop.index }}: {{ message }}
     {% endfor %}
   {% endfor %}';
        $pattern = '
       * 1: topic1
         - 1.1: Message 1 of topic 1
         - 1.2: Message 2 of topic 1
       * 2: topic2
         - 2.1: Message 1 of topic 2
         - 2.2: Message 2 of topic 2';
        $this->assertEquals(
            $this->_test_cmpl($s, $data), $pattern
        );
    }


    function test_test18()
    {
        $data = array('data' => '<<<>>>');
        $s = ' <table>
	{% for x in [1,2] %}
	<tr class="{{loop.cycle(\'odd\',\'even\')}}"><td>{{x}}</td><td>
	one</td><td>two</td></tr>
	{% endfor %}
	</table>';
        $pattern = ' <table>
	<tr class="odd"><td>1</td><td>
	one</td><td>two</td></tr>
	<tr class="even"><td>2</td><td>
	one</td><td>two</td></tr>
	</table>';
        $this->assertEquals(
            $this->_test_cmpl($s, $data), $pattern
        );
    }

    function test_test16()
    {
        $data = array('data' => array());
        $s = ' {% macro input(name, value=\'\', type=\'text\', size=20) -%}
    <input type="{{ type }}" name="{{ name }}" value="{{
        value|e }}" size="{{ size }}">
{%- endmacro -%}
<p>{{ input(\'username\') }}</p>
<p>{{ input(\'password\', type=\'password\') }}</p>';
        $pattern = '<p><input type="text" name="username" value="" size="20"></p>
<p><input type="password" name="password" value="" size="20"></p>';
        $this->assertEquals(
            $this->_test_cmpl($s, $data), $pattern
        );
    }

    function test_test1()
    {
        $data = array('if' => "'hello'", 'then' => 'world');
        $s = 'if( {{if}} ){ {{then }} };';
        $this->assertEquals(
            $this->_test_tpl($s, $data),
            'if( \'hello\' ){ world };'
        );
    }

    function test_test10()
    {
        $data = array();
        //$data=array('users'=>array(array('username'=>'one'),array('username'=>'two')));
        $s = '{#
        it\'s a test
        #}

        {% for item in [1,2,3,4,5,6,7,8,9] -%}
    {{ item }}
{%- endfor %}';
        $pattern = '123456789';
        $this->assertEquals(
            $this->_test_tpl($s, $data), $pattern
        );
    }

    /**
     * тесты на тег FOR
     */
    function test_test12()
    {
        $data = array();
        $s = '
        {%- for item in ["on\\\\e\'s ","one\"s "] -%}
    {% if loop.first %}{{ item }}{% endif -%}
    {% if loop.last %}{{ item }}{% endif -%}
    {{ item }}
{%- endfor %}';
        $pattern = 'on\\e\'s on\\e\'s one"s one"s ';
        $this->assertEquals(
            $this->_test_tpl($s, $data), $pattern
        );
    }

    function testLipsum()
    {
        $data = array('func' => 'fileman', 'data' => '<<<>>>');
        $s = '{{ lipsum(1,0,10,10)}}';
        $pattern = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi malesuada ';
        $this->assertEquals(
            $this->_test_cmpl($s, $data), $pattern
        );
    }

    /*
 function testLipsumError()
 {
     $data = array('func' => 'fileman', 'data' => '<<<>>>');
     $s = '{{ lipsum }}';
     $pattern = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi malesuada ';
     $this->assertEquals(
         $this->_test_cmpl($s, $data), $pattern
     );
 }
    */
    /**
     * тестируем уже готовые шаблоны
     */

    function test_test2()
    {
        $data = array('user' => array('username' => '111')); //,array('username'=>'one'),array('username'=>'two')));
        $s = 'hello {{ user.username }}!';
        $this->assertEquals(
            $this->_test_tpl($s, $data),
            'hello 111!'
        );
    }

    function test_test3()
    {
        $data = array('users' => array(array('username' => 'one'), array('username' => 'two')));
        $s = '<h1>Members</h1>
<ul>
{% for user in users %}
  <li>{{ user.username|e }}</li>
{% endfor %}
</ul>';
        $pattern = '<h1>Members</h1>
<ul>
  <li>one</li>
  <li>two</li>
</ul>';
        $this->assertEquals(
            $this->_test_tpl($s, $data), $pattern
        );
    }

    function test_test4()
    {
        $data = array('users' => array(array('username' => 'one'), array('username' => 'two')));
        $s = '<h1>Members</h1>
<ul>
    {%- for user in users %}
  <li>{{ user.username|e }}</li>
{%- endfor %}
</ul>';
        $pattern = '<h1>Members</h1>
<ul>
  <li>one</li>
  <li>two</li>
</ul>';
        $this->assertEquals(
            $this->_test_tpl($s, $data), $pattern
        );
    }

    function test_test5()
    {
        $data = array('users' => array(array('username' => 'one'), array('username' => 'two')));
        $s = '<h1>Members</h1>
   <ul>
    {%- for user in users -%}
  <li>{{ user.username|e }}</li>
{%- endfor -%}
</ul>';
        $pattern = '<h1>Members</h1>
   <ul><li>one</li><li>two</li></ul>';
        $this->assertEquals(
            $this->_test_tpl($s, $data), $pattern
        );
    }

    function test_test6()
    {
        $data = array(
            'navigation' => array(array('href' => 'one', 'caption' => 'two'), array('href' => 'one', 'caption' => 'two'), array('href' => 'one', 'caption' => 'two')),
            'a_variable' => 'hello!',
        );
        //$data=array('users'=>array(array('username'=>'one'),array('username'=>'two')));
        $s = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">
<html lang="en">
<head>
    <title>My Webpage</title>
</head>
<body>
    <ul id="navigation">
    {%- for item in navigation %}
        <li><a href="{{ item.href }}">{{ item.caption }}</a></li>
    {%- endfor %}
    </ul>

    <h1>My Webpage</h1>
    {{ a_variable }}
</body>
</html>';
        $pattern = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">
<html lang="en">
<head>
    <title>My Webpage</title>
</head>
<body>
    <ul id="navigation">
        <li><a href="one">two</a></li>
        <li><a href="one">two</a></li>
        <li><a href="one">two</a></li>
    </ul>

    <h1>My Webpage</h1>
    hello!
</body>
</html>';
        $this->assertEquals(
            $this->_test_tpl($s, $data), $pattern
        );
    }

    function test_test7()
    {
        $data = array('seq' => array(1, 2, 3, 4, 5, 6, 7, 8, 9));
        //$data=array('users'=>array(array('username'=>'one'),array('username'=>'two')));
        $s = '{% for item in seq -%}
    {{ item }}
{%- endfor %}';
        $pattern = '123456789';
        $this->assertEquals(
            $this->_test_tpl($s, $data), $pattern
        );
    }

    function test_test8()
    {
        $data = array('seq' => array(1, 2, 3, 4, 5, 6, 7, 8, 9));
        //$data=array('users'=>array(array('username'=>'one'),array('username'=>'two')));
        $s = '{#
        it\'s a test 
        #} 
        
        {% for item in seq -%}
    {{ item }}
{%- endfor %}';
        $pattern = '123456789';
        $this->assertEquals(
            $this->_test_tpl($s, $data), $pattern
        );
    }

    function test_test9()
    {
        $data = array('foo' => array('bar' => 'xxx'));
        //$data=array('users'=>array(array('username'=>'one'),array('username'=>'two')));
        $s1 = ' {{ foo.bar }} ';
        $s2 = ' {{ foo[\'bar\'] }} ';

        $pattern = ' xxx ';
        $this->assertEquals(
            $this->_test_tpl($s1, $data), $pattern
        );
        $this->assertEquals(
            $this->_test_tpl($s2, $data), $pattern
        );
    }

    function test_test11()
    {
        $data = array();
        //$data=array('users'=>array(array('username'=>'one'),array('username'=>'two')));
        $s = '{#
        it\'s a test 
        #} 
        
        {%- for item in ["on\\\\e\'s ","one\"s "] -%}
    {{ item }}
{%- endfor %}';
        $pattern = 'on\\e\'s one"s ';
        $this->assertEquals(
            $this->_test_tpl($s, $data), $pattern
        );
    }

    function test_test14()
    {
        $data = array('data' => array());
        $s = '
        {%- for item in data -%}
    {{ loop.index }}{{ item }}{{ loop.revindex }}{{ item }}
    {% else -%}
    nothing
{%- endfor %}';
        $pattern = 'nothing';
        $this->assertEquals(
            $this->_test_tpl($s, $data), $pattern
        );
    }

    function test_test15()
    {
        $data = array('data' => array());
        $s = ' {{ "Hello World"|replace("Hello", "Goodbye") }}';
        $pattern = ' Goodbye World';
        $this->assertEquals(
            $this->_test_cmpl($s, $data), $pattern
        );
    }

    function test_test17()
    {
        $data = array('data' => '<<<>>>');
        $s = ' <p>{{data|e|default(\'nothing\')}}</p>';
        $pattern = ' <p>&lt;&lt;&lt;&gt;&gt;&gt;</p>';
        $this->assertEquals(
            $this->_test_cmpl($s, $data), $pattern
        );
    }

    function test_test19()
    {
        $data = array('data' => '<<<>>>');
        $s = '{% set ZZZ=[\'Табличная форма\',\'Блочная форма\',\'Галерея\'] -%}
		<table class="align_left">{% for x in ZZZ %}<col>{%endfor -%}
		<tr>{% for x in ZZZ -%}
		<td><input type="radio" name="kat_form_{{loop.index}}"><b> {{x}}</b><br>
		<input type="text" class="digit2" name="kat_col_{{loop.index}}"> Кол-во столбцов<br>
		</td>{% endfor -%}
		</tr>
		</table>';
        $pattern = '<table class="align_left"><col><col><col><tr>' .
            '<td><input type="radio" name="kat_form_1"><b> Табличная форма</b><br>
		<input type="text" class="digit2" name="kat_col_1"> Кол-во столбцов<br>
		</td>' .
            '<td><input type="radio" name="kat_form_2"><b> Блочная форма</b><br>
		<input type="text" class="digit2" name="kat_col_2"> Кол-во столбцов<br>
		</td>' .
            '<td><input type="radio" name="kat_form_3"><b> Галерея</b><br>
		<input type="text" class="digit2" name="kat_col_3"> Кол-во столбцов<br>
		</td>' .
            '</tr>
		</table>';
        $this->assertEquals(
            $this->_test_cmpl($s, $data), $pattern
        );
    }

    function test_test20()
    {
        $data = array('rows' => array(
            array(
                array('id' => 1, 'text' => 'one'),
                array('id' => 2, 'text' => 'two'),
                array('id' => 3, 'text' => 'three'),
                array('id' => 4, 'text' => 'four'),
                array('id' => 5, 'text' => 'five'),
                array('id' => 6, 'text' => 'six')
            ),
            array(
                array('id' => 1, 'text' => 'one'),
                array('id' => 2, 'text' => 'two'),
                array('id' => 3, 'text' => 'three'),
                array('id' => 4, 'text' => 'four'),
                array('id' => 5, 'text' => 'five'),
                array('id' => 6, 'text' => 'six')
            ),
            array(
                array('id' => 1, 'text' => 'one'),
                array('id' => 2, 'text' => 'two'),
                array('id' => 3, 'text' => 'three'),
                array('id' => 4, 'text' => 'four'),
                array('id' => 5, 'text' => 'five'),
                array('id' => 6, 'text' => 'six')
            ),
            array(
                array('id' => 1, 'text' => 'one'),
                array('id' => 2, 'text' => 'two'),
                array('id' => 3, 'text' => 'three'),
                array('id' => 4, 'text' => 'four'),
                array('id' => 5, 'text' => 'five'),
                array('id' => 6, 'text' => 'six')
            ),
        ));
        $s = '{% for  rr in rows %} {% if not loop.first %}
<tr>
<td style="height:35px;"></td>
{% set bg=loop.cycle(\'bglgreen\',\'bggreen\') %}
{% if loop.last %}{%set last=\'border-bottom:none;\' %}
{% else %}{%set last=\'\' %}{% endif %}
<th class="{{bg}}" style="border-left:none;{{last}}">{{loop.index0}}</th>
{% for  r in rr %}
<td class="{{bg}}" {% if last %} style="{{last}}"{%endif%}>
<div id="item_text_{{r.id}}" class="text_edit">{{r.text|default(\'&nbsp;\')}}</div></td>
{% endfor %}
<td class="bgdray">
<input type="button" class="arrowup"
><input type="text" class="digit2"
><input type="button" class="arrowdn"
></td>
<td class="bgdray">
<input type="button" class="remrec">
</td>
</tr>
{% endif %}
{% endfor %}';
        $pattern = '
<tr>
<td style="height:35px;"></td>
<th class="bglgreen" style="border-left:none;">1</th>
<td class="bglgreen">
<div id="item_text_1" class="text_edit">one</div></td>
<td class="bglgreen">
<div id="item_text_2" class="text_edit">two</div></td>
<td class="bglgreen">
<div id="item_text_3" class="text_edit">three</div></td>
<td class="bglgreen">
<div id="item_text_4" class="text_edit">four</div></td>
<td class="bglgreen">
<div id="item_text_5" class="text_edit">five</div></td>
<td class="bglgreen">
<div id="item_text_6" class="text_edit">six</div></td>
<td class="bgdray">
<input type="button" class="arrowup"
><input type="text" class="digit2"
><input type="button" class="arrowdn"
></td>
<td class="bgdray">
<input type="button" class="remrec">
</td>
</tr>
<tr>
<td style="height:35px;"></td>
<th class="bggreen" style="border-left:none;">2</th>
<td class="bggreen">
<div id="item_text_1" class="text_edit">one</div></td>
<td class="bggreen">
<div id="item_text_2" class="text_edit">two</div></td>
<td class="bggreen">
<div id="item_text_3" class="text_edit">three</div></td>
<td class="bggreen">
<div id="item_text_4" class="text_edit">four</div></td>
<td class="bggreen">
<div id="item_text_5" class="text_edit">five</div></td>
<td class="bggreen">
<div id="item_text_6" class="text_edit">six</div></td>
<td class="bgdray">
<input type="button" class="arrowup"
><input type="text" class="digit2"
><input type="button" class="arrowdn"
></td>
<td class="bgdray">
<input type="button" class="remrec">
</td>
</tr>
<tr>
<td style="height:35px;"></td>
<th class="bglgreen" style="border-left:none;border-bottom:none;">3</th>
<td class="bglgreen" style="border-bottom:none;">
<div id="item_text_1" class="text_edit">one</div></td>
<td class="bglgreen" style="border-bottom:none;">
<div id="item_text_2" class="text_edit">two</div></td>
<td class="bglgreen" style="border-bottom:none;">
<div id="item_text_3" class="text_edit">three</div></td>
<td class="bglgreen" style="border-bottom:none;">
<div id="item_text_4" class="text_edit">four</div></td>
<td class="bglgreen" style="border-bottom:none;">
<div id="item_text_5" class="text_edit">five</div></td>
<td class="bglgreen" style="border-bottom:none;">
<div id="item_text_6" class="text_edit">six</div></td>
<td class="bgdray">
<input type="button" class="arrowup"
><input type="text" class="digit2"
><input type="button" class="arrowdn"
></td>
<td class="bgdray">
<input type="button" class="remrec">
</td>
</tr>';
        $this->assertEquals(
            $this->_test_cmpl($s, $data), $pattern
        );
    }

    function test_test21()
    {
        $data = array();
        //$data=array('users'=>array(array('username'=>'one'),array('username'=>'two')));
        $s = '{%- for d in [1,2,3,4] %}
  <li><a href="?do=showtour&amp;id={{d.ID}}">{{d.name}}</a></li>
  {%- endfor %}
		</ul>
	</li>
	<li>
		<span>Игроки</span>
	  <ul>
  {%- for d in [1,2,3,4] %}
  <li><a href="?do=player&amp;id={{d.ID}}">{{d.name}}</a></li>
  {%- endfor %}';
        $pattern = '
  <li><a href="?do=showtour&amp;id="></a></li>
  <li><a href="?do=showtour&amp;id="></a></li>
  <li><a href="?do=showtour&amp;id="></a></li>
  <li><a href="?do=showtour&amp;id="></a></li>
		</ul>
	</li>
	<li>
		<span>Игроки</span>
	  <ul>
  <li><a href="?do=player&amp;id="></a></li>
  <li><a href="?do=player&amp;id="></a></li>
  <li><a href="?do=player&amp;id="></a></li>
  <li><a href="?do=player&amp;id="></a></li>';
        $this->assertEquals(
            $this->_test_cmpl($s, $data), $pattern
        );
    }

    function test_test22()
    {
        $data = array('data' => '<<<>>>');
        $s = '{% macro Regnew(invite=1,error=3,error2=4,error3=5) -%}
        <table style="table-layout:fixed;">
			<tr><td >
            {%- if error %}<em>{{error}}</em><br>{% endif -%}
            {{error2}} {{error3}} {{invite}}</td></tr></table>
                    {%-endmacro -%}
             {{ Regnew(1,2,error3=6) }}';
        $pattern = '<table style="table-layout:fixed;">
			<tr><td ><em>2</em><br>4 6 1</td></tr></table>';
        $this->assertEquals(
            $this->_test_cmpl($s, $data), $pattern
        );
    }

    function test_test23()
    {
        $data = array('data' => '<<<>>>');
        $s = '{% macro fileman(list=1,pages=1,type,filter) -%}
{{list~pages~type~filter}}
        {% endmacro -%}
{{fileman()}} {{fileman(pages=3)}} {{fileman(1,2,3)}}';
        $pattern = '1100 1300 1230';
        $this->assertEquals(
            $this->_test_cmpl($s, $data), $pattern
        );
    }

    function test_test24()
    {
        $data = array('user' => array('right' => array('*' => 1027)), 'right' => array('1' => 1027));
        $s = '{%if not user.right["*"] %}1{%endif-%}
{%if not right["*"] %}2{%endif-%}
{%if not right[1] %}3{%endif%}';
        $pattern = '12';
        $this->assertEquals(
            $this->_test_cmpl($s, $data), $pattern
        );
    }

    function testSelf_Self()
    {
        $data = array('form' => array(

            'elements' => array('type' => 'fieldset', 'attributes' => ' style="width:100px;"', 'label' => 'Hello',
                'elements' => array(
                    array('id' => 'input', 'required' => true, 'label' => 'Hello world', 'html' => '<input type="text">')
                )
            )));
        $s = file_get_contents(dirname(__FILE__) . '/quick.form.tpl');

        $pattern = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"> <html xmlns="http://www.w3.org/1999/xhtml"> <head> <title>Using Twig template engine to output the form</title> <style type="text/css"> /* Set up custom font and form width */ body { margin-left: 10px; font-family: Arial,sans-serif; font-size: small; } . quickform { min-width: 500px; max-width: 600px; width: 560px; } </style> </head> <body> <div class="quickform"> <form> <div class="row"> <label for="f" class="element"><span class="required">* </span> f </label> <div class="element error"><span class="error">f<br/></span> f </div> </div> <div class="row"> <label for=" " class="element"><span class="required">* </span> </label> <div class="element error"><span class="error"> <br/></span> </div> </div> <div class="row"> <label for="H" class="element"><span class="required">* </span> H </label> <div class="element error"><span class="error">H<br/></span> H </div> </div> <div class="row"> <label for="" class="element"> </label> <div class="element"> </div> </div> </form> </div> </body> </html>';
        $this->assertEquals(
            $this->compress($this->_test_cmpl($s, $data)), $pattern
        );
    }

    function testCall()
    {
        $data = array('func' => 'fileman', 'data' => '<<<>>>');
        $s = '{% macro fileman(list=1,pages=1,type,filter) -%}
{{list~pages~type~filter}}
        {%- endmacro -%}
        {{ call (func,1,2,3) }} {{fileman()}} {{fileman(pages=3)}} {{fileman(1,2,3)}}';
        $pattern = '1230 1100 1300 1230';
        $this->assertEquals(
            $this->_test_cmpl($s, $data), $pattern
        );
    }

}

if (!defined('PHPUnit_MAIN_METHOD')) {
    $suite = new PHPUnit_Framework_TestSuite('templateTest');
    PHPUnit_TextUI_TestRunner::run($suite);
}
?>