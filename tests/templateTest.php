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

    function test($a,$b,$c){
        return $a.'+'.$b.'+'.$c;
    }
}

$GLOBALS['engine'] = new engine();

class tpl_test extends tpl_base{

    function __construct(){

    }
    function _($par=0){
        return $this->_test($par).' Calling parent_ method';
    }

    function _test($par){
        return 'Calling parent test! Ok! ';
    }
}

class templateTest extends TestCase
{

    function compress($s)
    {
        return preg_replace('/\s+/s', ' ', $s);
    }

    /**
     * тестирование шаблона с генерацией нового класса
     */
    function _test_cmpl($tpl, $data=array(), $show = false,$macro='_')
    {
        static $classnumber = 10;
        while(class_exists('tpl_test' . $classnumber, false))
           $classnumber++;
        $calc = new \Ksnk\templater\php_compiler();
        $calc->makelex($tpl);
        $result = $calc->tplcalc('test' . $classnumber);
        if(empty($result)) return null;
        $t = 'tpl_test' . $classnumber;
        if ($show) echo $result . "\n\n";
        file_put_contents($t.'.php',$result); include($t.'.php');
//        eval ('?'.'>' . $result);
        $tt = new $t();
        $x= $tt->$macro($data);
        unlink($t.'.php');
        return $x;
    }

    function test31(){
        $s='####################################################################
##
##  файл шаблонов для шаблонизатора
##
####################################################################

####################################################################
## class
##
{%- block class -%}
<?php
/**
 * this file is created automatically at "{{ now(\'d M Y G:i\') }}". Never change anything,
 * for your changes can be lost at any time.
 */
{# ## don\'t need includes any more
{% if extends %}
include_once TEMPLATE_PATH.DIRECTORY_SEPARATOR.\'tpl_{{extends}}.php\';
{% else %}
include_once \'tpl_base.php\';
{% endif %}

{% for imp in import -%}
require_once TEMPLATE_PATH.DIRECTORY_SEPARATOR.\'tpl_{{imp}}.php\';
{% endfor %}
#}
class tpl_{{name}} extends tpl_{{`extends` |default(\'base\') }}
{%endblock%}';
        $pattern = '
<?php
/**
 * this file is created automatically at "'.date('d M Y G:i').'". Never change anything,
 * for your changes can be lost at any time.
 */

class tpl_xxx extends tpl_yyy';
        $this->assertEquals( $pattern,
            $this->_test_cmpl($s, ['name'=>'xxx', 'extends'=>'yyy'],false)
        );
    }


    function test_test29(){
        $s="tpl_{{`extends` |default('base') }}";
        $pattern = 'tpl_yyy';
        $this->assertEquals(
            $this->_test_cmpl($s, ['name'=>'xxx', 'extends'=>'yyy'],false), $pattern
        );
    }

    function test_test30(){
        $s="class tpl_{{ext+min(5,4,3)}}";
        $pattern = 'class tpl_9';
        $this->assertEquals( $pattern,
            $this->_test_cmpl($s, ['name'=>'xxx', 'ext'=>'6'],false)
        );
    }

    function test_test28(){
        $s="class tpl_{{5+min(5,4,3)}}";
        $pattern = 'class tpl_8';
        $this->assertEquals( $pattern,
            $this->_test_cmpl($s, ['name'=>'xxx', 'extends'=>'6'],false)
        );
    }

    /** test extends-parent  */
    function test_test27()
    {
        $s = '##
##   Генерация страничной адресации
##
{% macro paging(pages)%}
{% if pages -%}
<div class="paging">
        {%- set maxnumb= (pages.total) // pages.perpage
           set start = pages.page-3
           if start>0 -%}
            <a href="{{pages.url}}page=prev">&lt;&lt;</a>&nbsp;
        {%- endif -%}

        {%- for xpage in range(7,1) -%} {% set page=start+xpage -%}
        {% if page>0 and page <= maxnumb -%}
        {% if page == pages.page -%}
        <span>{{page}}</span>&nbsp;
        {%- else -%}
        <a href="{{pages.url}}page={{page}}">{{page}}</a>&nbsp;
        {%-  endif endif  endfor -%}
        {%- if page < maxnumb -%}
            <a href="{{pages.url}}?page=next">&gt;&gt;</a>&nbsp;
        {%- endif -%}
        {%- if pages.total%}<span>Всего: {{pages.total}}</span>{% endif -%}
        </div>
{% endif -%}
{% endmacro -%}';
        $pattern = '<div class="paging"><a href="http:xxx.com/xxx?page=prev">&lt;&lt;</a>&nbsp;<a href="http:xxx.com/xxx?page=2">2</a>&nbsp;<a href="http:xxx.com/xxx?page=3">3</a>&nbsp;<a href="http:xxx.com/xxx?page=4">4</a>&nbsp;<span>5</span>&nbsp;<a href="http:xxx.com/xxx?page=6">6</a>&nbsp;<a href="http:xxx.com/xxx?page=7">7</a>&nbsp;<a href="http:xxx.com/xxx?page=8">8</a>&nbsp;<a href="http:xxx.com/xxx??page=next">&gt;&gt;</a>&nbsp;<span>Всего: 144</span></div>';
        $this->assertEquals(
            $this->_test_cmpl($s, array('pages'=>array(
                'total'=>144,
                'perpage'=>15,
                'url'=>"http:xxx.com/xxx?",
                'page'=>5,
            )),false,'_paging'), $pattern
        );
    }


    /** test extends-parent  */
    function test_test26()
    {
        $data = array('data' => '<<<>>>');
        $s = '
{% extends "test.php"%}
{% block test %} <table>
	{% for x in [1,2] %}
	<tr class="{{loop.cycle(\'odd\',\'even\')}}"><td>{{x}}</td><td>
	one</td><td>two</td></tr>
	{% endfor %}
	</table> {{parent()}}{% endblock %}
	{{test()}} ';
        $pattern = ' <table>
	<tr class="odd"><td>1</td><td>
	one</td><td>two</td></tr>
	<tr class="even"><td>2</td><td>
	one</td><td>two</td></tr>
	</table> Calling parent test! Ok! ';
        $this->assertEquals(
            $this->_test_cmpl($s, $data,false,'_test'), $pattern
        );
    }

    function testCallObject()
    {
        $data = array('main' => $GLOBALS['engine'], 'data' => '<<<>>>');
        $s = '
        {{ main.test (1,2,3) }} ';
        $pattern = '
        1+2+3 ';
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

    function test_test13()
    {
        $data = array();
        $s = '
        {%- for item in ["on\\\\e\'s ","one\"s "] -%}
    {{ loop.index }}{{ item }}{{ loop.revindex }}{{ item }}
{%- endfor %}';
        $pattern = '1on\\e\'s 2on\\e\'s 2one"s 1one"s ';
        $this->assertEquals(
            $this->_test_cmpl($s, $data), $pattern
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

/*
    function test_test1()
    {
        $data = array('if' => "'hello'", 'then' => 'world');
        $s = 'if( {{if}} ){ {{then }} };';
        $this->assertEquals(
            $this->_test_cmpl($s, $data),
            'if( \'hello\' ){ world };'
        );
    }
*/
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
            $this->_test_cmpl($s, $data), $pattern
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
            $this->_test_cmpl($s, $data), $pattern
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

    /**
     * тестируем уже готовые шаблоны
     */

    function test_test2()
    {
        $data = array('user' => array('username' => '111')); //,array('username'=>'one'),array('username'=>'two')));
        $s = 'hello {{ user.username }}!';
        $this->assertEquals(
            $this->_test_cmpl($s, $data),
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
            $this->_test_cmpl($s, $data), $pattern
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
            $this->_test_cmpl($s, $data), $pattern
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
            $this->_test_cmpl($s, $data), $pattern
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
            $this->_test_cmpl($s, $data), $pattern
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
            $this->_test_cmpl($s, $data), $pattern
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
            $this->_test_cmpl($s, $data), $pattern
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
            $this->_test_cmpl($s1, $data), $pattern
        );
        $this->assertEquals(
            $this->_test_cmpl($s2, $data), $pattern
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
            $this->_test_cmpl($s, $data), $pattern
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
            $this->_test_cmpl($s, $data), $pattern
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
        $data = array('user' => array('right' => array('*' => 1027)), 'right' => array(1 => 1027));
        $s = '{%if not user.right["*"] %}1{%endif-%}
{%if not right["*"] %}2{%endif-%}
{%if not right[1] %}3{%endif%}';
        $pattern = '2';
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
        $s = file_get_contents(dirname(__FILE__) . '/quick.form.twig');

        $pattern = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"> <html xmlns="http://www.w3.org/1999/xhtml"> <head> <title>Using Twig template engine to output the form</title> <style type="text/css"> /* Set up custom font and form width */ body { margin-left: 10px; font-family: Arial, sans-serif; font-size: small; } .quickform { min-width: 500px; max-width: 600px; width: 560px; } </style> </head> <body> <div class="quickform"> <form> <div class="row"> <label for="" class="element"> </label> <div class="element"> </div> </div> <div class="row"> <label for="" class="element"> </label> <div class="element"> </div> </div> <div class="row"> <label for="" class="element"> </label> <div class="element"> </div> </div> <div class="row"> <label for="" class="element"> </label> <div class="element"> </div> </div> </form> </div> </body> </html>';
        $this->assertEquals( $pattern,
            $this->compress($this->_test_cmpl($s, $data))
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

    /**
     * неправильно записан цикл. При трансляции выходит лажа
     * todo: исправить!
     */
    function testFor()
    {
        $data = array('func' => 'fileman', 'data' => '<<<>>>');
        $s = '<th>id</th>
{% for data[0] as name,cell %}{% if name != \'id\' %}
<th>{{ name }}</th>
            {% endif %}{% endfor %}';
        $pattern = '1230 1100 1300 1230';
        $this->expectException(\Ksnk\templater\CompilationException::class);
        $this->_test_cmpl($s, $data);
    }


    /**
     *  2 ошибки.
     * поставить = вместо ==
     * закомментировать endif
     */

    function testAbsentEndif()
    {
        $data = array('func' => 'fileman', 'data' => '<<<>>>');
        $s = "<div class='body'>
{% for elem in data %}
{% if elem.type=='text' %}
{% elseif elem.type=='foto' %}
{% else %}
       unsupported type <br>
    {% endif %}
    {% endfor %}
</div> ";
        $pattern = '<div class=\'body\'>
</div> ';
        $this->assertEquals(
            $this->_test_cmpl($s, $data), $pattern
        );
    }

}
