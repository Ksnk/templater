<?php
ini_set('include_path',
  ini_get('include_path')
  .';../../nat2php;..' // windows only include!
);
require_once('nat2php.class.php');
require_once('compiler.class.php');
require_once('template_parser.class.php');
require_once('compiler.php.php');

class engine {
	function export($class,$method,$par1=null,$par1=null,$par1=null){
		return sprintf('calling %s::%s(%s)',$class,$method,array_diff(array($par1,par2,par3),array(null)));
	}
}

$GLOBALS['engine']=&new engine();

function pps(&$x,$default=''){if(empty($x))return $default; else return $x;}

	function test_tpl($tpl,$data){
		$calc=new php_compiler();
		$calc->makelex($tpl);
		try {
			$result=$calc->block_internal();
			$x='$result="";'.$calc->popOp()->val.' return $result;'; 
			echo'<pre>'.htmlspecialchars($x).'</pre>';
			$fnc=create_function('&$par',$x);
			echo'<pre>'.htmlspecialchars(print_r($calc,true)).'</pre>';
			return $fnc($data);
		} catch(Exception $e){
			print_r($e->getMessage());echo'</pre>';
			echo'<pre>'.htmlspecialchars(print_r($calc,true)).'</pre>';
			return 'XXX';
		}
	}
	function test_cmpl($tpl,$data){
		$calc=new php_compiler();
		$calc->makelex($tpl);
		try {
			$result=$calc->tplcalc('test1');
			echo'<pre>'.htmlspecialchars($result).'</pre>';
			eval ('?>'.$result);
			//$fnc=create_function('&$par',$x);
			//echo'<pre>'.htmlspecialchars(print_r($calc,true)).'</pre>';
			$t=new tpl_test1();
			return $t->_($data);
		} catch(Exception $e) {
			print_r($e->getMessage());echo'</pre>';
			echo'<pre>'.htmlspecialchars(print_r($calc,true)).'</pre>';
			return 'XXX';
		}
	}
	function test_file($filename,$data){
		$calc=new php_compiler();
		$calc->makelex(file_get_contents($filename));
		try {
			$result=$calc->tplcalc('test1');
			echo'<pre>'.htmlspecialchars($result).'</pre>';
			eval ('?>'.$result);
			//$fnc=create_function('&$par',$x);
			//echo'<pre>'.htmlspecialchars(print_r($calc,true)).'</pre>';
			$t=new tpl_test1();
			return $t->_($data);
		} catch(Exception $e) {
			print_r($e->getMessage());echo'</pre>';
			echo'<pre>'.htmlspecialchars(print_r($calc,true)).'</pre>';
			return 'XXX';
		}
	}
	
//*	
$data=array('users'=>array(array('username'=>'one'),array('username'=>'two')));
 $data=array('foo'=>array('bar'=>'xxx'));
		//$data=array('users'=>array(array('username'=>'one'),array('username'=>'two')));
		$data=array();
/**!!!!!!!!!!!!!!!!!!! не работает!!!!!!!!!!!!!!!!!! */
$s='{%if not user.right["*"] %}*{%endif%}
{%if not right["*"] %}*{%endif%}
{%if not right[1] %}*{%endif%}';	  
		/**!!!!!!!!!!!!!!!!!!!  ошибка трансл€ции split - макра!!!!!!!!!!!!!!!!!! */
	$s=	'{% for column in list|split(columns) %}
{% for l in column %}
{{l.url}}
{#<table class="tahoma thetable"><tr>
<th>»м€</th><th>размер</th><th>info</th>
</tr><tr><td>%data%</td></tr>
</table>
</div>
<div id="fman_column">
<table>
<tr class="%odd%">
<td ><nobr><label><input type="checkbox" class="glass select" value="%url%" name="ff[]">%name%</label></nobr></td><td>%size%</td><td>%info%</td>
</tr>
</table>#}

{% endfor %}
{% endfor %}'  ;    
/**!!!!!!!!!!!!!!!!!!! вставл€ть тесты сюда !!!!!!!!!!!!!!!!! */  
	echo '<pre>'.htmlspecialchars(test_cmpl($s,$data)).'</pre>';/**/
	
//_template();
//echo '<pre>'.htmlspecialchars(test_file('elements.jtpl',array())).'</pre>';/**/
/*        $data=array('users'=>array(array('username'=>'one'),array('username'=>'two')));
        $s='{% for i in [1,2,3] %} {{ lipsum (1,0,2,3,4) }} {{loop.cycle('odd','even')}}{% endfor %}';
			echo '<pre>'.htmlspecialchars(test_tpl($s,$data)).'</pre>';/**/

