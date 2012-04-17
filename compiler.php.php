<?php
/**
 * PHP templates creator. 
 * ----------------------------------------------------------------------------
 * $Id:Jinja4PHP. Jinja2 language template, written by Ksnk (sergekoriakin@gmail.com)
 * Ver : 0.8(beta), Rev: 1376, Modified: 2010/12/02 17:07:47$
 * ----------------------------------------------------------------------------
 * License GNU/LGPL - Serge Koriakin - october 2010
 * ----------------------------------------------------------------------------
 */
class php_compiler extends tpl_parser {
	function __construct($options=array()){
		parent::__construct();
		$this
			->newOp2('- +',4)
			->newOp2('* / %',5)
			->newOp2('//',5,'ceil(%s/%s)')
			->newOp2('**',7,'pow(%s,%s)') 
			->newOp2('.',12,array($this,'function_point')) 
			->newOp2('|',11,array($this,'function_filter')) 
			->newOp2('is',11,array($this,'function_filter'),11) 
			->newOp2('== != > >= < <=',2,null,'B**')
			->newOp2('and',3,'(%s) && (%s)','BBB')
			->newOp2('or',3,'(%s) || (%s)','BBB')
			->newOp2('~',2,'(%s).(%s)','SSS')
			->newOp1('not','!(%s)','BB')
			->newOp2('& << >>',3)
			// однопараметровые фильтры
			// ну очень служебные функции
            ->newFunc('defined','defined(%s)','SB')
            ->newOpR('loop',array($this,'operand_loop'))
            ->newOpR('self','self','TYPE_XID')
            ->newOpR('_self','self','TYPE_XID')
            ->newOp1('now','date(%s)')
			// фильтры и тесты
            ->newFunc('e','htmlspecialchars(%s)','SS')
            ->newFunc('raw','%s','SS')
            ->newFunc('escape','htmlspecialchars(%s)','SS')
			->newFunc('replace',array($this,'function_replace'),'SSSS')
			->newFunc('length','count(%s)','DI')
			->newFunc('lipsum','$this->func_lipsum(%s)')
			->newFunc('join','$this->filter_join(%s)')
			->newFunc('default','$this->filter_default(%s)')
			->newFunc('justifyleft','$this->func_justifyL(%s)')
			->newFunc('slice','$this->func_slice(%s)')
			->newFunc('range','$this->func_range(%s)')
			->newFunc('keys','$this->func_keys(%s)')
            ->newFunc('callex','$this->callex(%s)')
            ->newFunc('call','$this->call($par,%s)')
            ->newFunc('translit','translit(%s)')
            ->newFunc('format','sprintf(%s)')
			->newFunc('truncate','$this->func_truncate(%s)')
			->newFunc('date','$this->func_date(%s)')
			->newFunc('finnumb','$this->func_finnumb(%s)')
			->newFunc('right','$this->func_rights(%s)')
            ->newFunc('russuf','$this->func_russuf(%s)')
            ->newFunc('in_array','$this->func_in_array(%s)')
			;
		
	}
	
  protected function error($msgId,$lex=null){
    $mess=pps($this->error_msg[$msgId],$msgId);
    if(is_null($lex)){
      $lex=$this->op;
    }
    if(!is_null($lex)){
    	// count a string
    	$lexpos=0;$line=0;
    	foreach($this->lines as $k=>$v){
    	  if($k>=$lex->pos) break;
    	  $lexpos=$k;$line=$v; 
    	}
    	
      $mess.=sprintf("\n".'line:%s, pos:%s lex:"%s"'
        ,$line+1,pps($lex->pos,-1)-$lexpos, pps($lex->val,-1));
    }
    throw new Exception($mess);
  } 
  
/**
 * конвертиование операнда в то или иное состояние 
 * @param array $types - массив с именами типов для конвертирвоания
 * @param operand $res - операнд 
 * @see nat2php/parser::to()
 */
	function to($types,&$res){
    	//конвертер операнда в то или иное состояние
    	if(!is_array($types)) $types=array($types);
    	if(is_string($res)) {
    		$res=$this->oper($res,'TYPE_STRING');
    	} ;
    	if(!is_object($res)) return $res;
    	foreach($types as $type)
    	switch($type){
    /*
     * служебные операции
     */
    	case 'trimln':// удалить первый NewLine из строки
    		$res->val=preg_replace('/^[ \t]*\r?\n/','',$res->val);
    		break;	
    	case 'triml':	
    		$res->val=preg_replace("/^\s*/s",'',$res->val);
    		break;	
    	case 'value':
    		return $res->val;
    /*
     * операции внешнего уровня
     */		
    	case 'TYPE_SENTENSE':
    		if($res->type=='TYPE_SENTENSE') break;
    		$this->to('*',$res);
    		if($res->val=="''")$res->val='';
    		else
    			$res->val='$result.='.$res->val.';'; //TODO: завязка на порождаемый язык
    		$res->type='TYPE_SENTENSE'	;
    		break;
    /*
     * просто литерал
     */		
    	case 'TYPE_LITERAL':
    		if($res->type=='TYPE_ID' || $res->type=='TYPE_STRING2'){
    		} else {
    			$this->error('plain literal expected');
    		}
    		break;
    /*
     * преобразования
     */		
    	case 'I':
    	case 'TYPE_XID':
    		if($res->type=='TYPE_ID' || $res->type=='TYPE_STRING2'){
	 			if($this->checkId($res->val)){
					$res->val='$'.$res->val;
					$res->type='TYPE_OPERAND';
				} else {	
					$res->val='$par[\''.$res->val.'\']';
					$res->type='TYPE_XID';
				};
    		} elseif($res->type=='TYPE_LIST'){
       			$this->to('TYPE_XLIST',$res);
	   		} /*elseif ($res->type!='TYPE_XID'){
	    		$this->error('waiting for ID')	;
	    	}*/;
     		break;
     		
    	case 'TYPE_XLIST':	
    		$this->to('L',$res);
    		$res->val='array('.$res->val.')';
			$res->type='TYPE_XID';
			break; 
			      		
     	case 'B':	
    	case 'TYPE_XBOOLEAN':
    		if ($res->type=='TYPE_ID' || $res->type=='TYPE_STRING2') {
    			$this->to('I',$res);
    		} elseif ($res->type=='TYPE_STRING'){
    			$this->to('S',$res);
    		}
    		if ($res->type=='TYPE_XID') {
    			$res->val='(isset('.$res->val.') && !empty('.$res->val.'))';
    			break;
       		} elseif($res->type=='TYPE_XSTRING'){
    			$res->val='!empty('.$res->val.'))';
    			break;
       		}
       		// продолжаем то, что ниже!!!!!!!!!
       	case 'L':
       		if($res->type=='TYPE_LIST'){	
	       		$op=array();
	    		for($i=0;$i<count($res->value['keys']);$i++){
	    			$op[]=$this->to('*',$res->value['keys'][$i])->val;
	    		}
				$res->val=implode(',',$op);
				$res->type='TYPE_XLIST';
       		}// break не нeнужен !!!
    	case '*':	
       	case 'S':	
       	case 'D':	
       	case 'TYPE_OPERAND':
    	case 'TYPE_STRING':
    		if ($res->type=='TYPE_ID') $this->to('I',$res);
       		if($res->type=='TYPE_LIST'){
     			$arr=array();
     			if(isset($res->value['keys'])){
					for($i=0;$i<count($res->value['keys']);$i++){
						$arr[]=$this->to('S',$res->value['keys'][$i])->val;
					}
     			} else {
	     			for($i=0;$i<count($res->value);$i++){
						$arr[]=$this->to('S',$res->value[$i])->val;
					}
     			}
				$res->val=implode(',',$arr);
				$res->type='TYPE_XSTRING';	
     		}
     		if($res->type=='TYPE_STRING' || $res->type=='TYPE_STRING1'){
    			$res->val="'".addcslashes($res->val,"'")."'";
    			$res->type='TYPE_XSTRING';
    		}
   			if($res->type=='TYPE_XID'){
    			$res->val='(isset('.$res->val.')?'.$res->val.':"")';
    			$res->type='TYPE_XSTRING';
       		}
       		//
    		
       		break;
    	}
    	return $res;
    }  
      
	/**
	 * Описание хелпера loop для тега for
	 */
	function operand_loop($op1=null,$attr=null,$reson='attr'){
		// найти ближайший открытый for и отметить, что loop там используется.
		if($reson=='call') {
			// рекурсивный вызов цикла еще раз
			if($op1->val=='loop')
				$this->error('recursively loop not allowed a while');
			else {
				$t=&$this->opensent('for');
				$t['loop_cycle']='array('.$this->to('S',$attr)->val.')';
				return null;
			}	
//			$op= $this->oper('$loop','TYPE_OPERAND');
//			return $op;
		} else	if(is_null($attr)){
			$op= $this->oper('loop','TYPE_OBJECT');
			$op->handler=array($this,'operand_loop');
			return $op;
		} else {
			$t=&$this->opensent('for');
			$t['loopdepth']=$loopdepth=count($this->opensentence);
			if($attr=='first'){
				$t['loop_index']=true;
				return $this->oper('$loop'.$loopdepth.'_index==1','TYPE_OPERAND');
			} elseif ($attr=='last'){
				$t['loop_index']=true;
				$t['loop_last']=true;
				return $this->oper('$loop'.$loopdepth.'_index==$loop'.$loopdepth.'_last','TYPE_OPERAND');
			} elseif ($attr=='cycle'){
				$t['loop_cycle']=true;
				$op=$this->oper('$this->loopcycle($loop'.$loopdepth.'_cycle)','TYPE_OBJECT');
				$op->handler=array($this,'operand_loop');
				return $op;
			} elseif ($attr=='index0'){
				$t['loop_index']=true;
				return $this->oper('($loop'.$loopdepth.'_index-1)','TYPE_OPERAND');
			} elseif ($attr=='revindex'){
				$t['loop_revindex']=true;
				$t['loop_last']=true;
				return $this->oper('$loop'.$loopdepth.'_revindex','TYPE_OPERAND');
			} elseif ($attr=='revindex0'){
				$t['loop_revindex']=true;
				$t['loop_last']=true;
				return $this->oper('($loop'.$loopdepth.'_revindex-1)','TYPE_OPERAND');
			} else {
				$t['loop_'.$attr]=true;
				if(in_array($attr,array('length','index'))){
					return $this->oper('$loop'.$loopdepth.'_'.$attr,'TYPE_OPERAND');
				} else
					$this->error('undefined loop attribute-"'.$attr.'"!');
			}
		}
	}
	/**
   * фильтр - replace
   * @param operand $op1 - TYPE_ID - имя функции
   * @param operand $op2 - TYPE_LIST - параметры функции
   */     
	function function_replace($op1,$op2){
		$op1->val= 'str_replace('.$this->to('S',$op2->value['keys'][1])->val
			.','.$this->to('S',$op2->value['keys'][2])->val
			.','.$this->to('S',$op2->value['keys'][0])->val
		.')';
		$op1->type="TYPE_OPERAND";
		return $op1;
	}
}
