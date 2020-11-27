<?php
/**
 * PHP templates creator.
 * <%=point('hat','jscomment');
 *
 *
 *
 *
 * %>
 */

namespace Ksnk\templater;

use Ksnk\templates\base as tpl_base;

class php_compiler extends tpl_parser
{

    static $filename = '';

    function __construct($options = array())
    {
        parent::__construct();
        $this
            ->newOp2('- +', 4)
            ->newOp2('* / %', 5)
            ->newOp2('//', 5, 'ceil(%s/%s)')
            ->newOp2('**', 7, 'pow(%s,%s)')
            ->newOp2('..', 3, array($this, 'reprange'))
            ->newOp2('.', 12, array($this, 'function_point'))
            ->newOp2('|', 11, array($this, 'function_filter'))
            ->newOp2('is', 11, array($this, 'function_filter'), 11)
            ->newOp2('== != > >= < <=', 2, null, 'B**')
            ->newOp2('and', 1, '(%s) && (%s)', 'BBB')
            ->newOp2('&&', 1, '(%s) && (%s)', 'BBB')
            ->newOp2('or', 1, '(%s) || (%s)', 'BBB')
            ->newOp2('||', 1, '(%s) || (%s)', 'BBB')
            ->newOp2('~', 2, '(%s).(%s)', 'SSS')
            ->newOp1('not', '!(%s)', 'BB')
            ->newOp2('&', 3, '$this->_int(%s)& $this->_int(%s)', '*DD')
            ->newOp2('<< >>', 3)
            // однопараметровые фильтры
            // ну очень служебные функции
            ->newFunc('defined', 'defined(%s)', 'SB')
            //->newOpR('loop', array($this, 'operand_loop'))
            ->newOpR('self', 'self', self::TYPE_XID)
            ->newOpR('_self', 'self', self::TYPE_XID)
            ->newOpR('true', 'true', self::TYPE_XBOOLEAN)
            ->newOpR('false', 'false', self::TYPE_XBOOLEAN)
            ->newOp1('now', 'date(%s)')
            // фильтры и тесты
            ->newFunc('e', 'htmlspecialchars(%s)', 'SS')
            ->newFunc('raw', '%s', 'SS')
            ->newFunc('escape', 'htmlspecialchars(%s)', 'SS')
            ->newFunc('replace', array($this, 'function_replace'), 'SSSS')
            ->newFunc('is_dir', 'is_dir(%s)', 'SI')
            ->newFunc('length', '$this->func_count(%s)', 'DI')
            ->newFunc('lipsum', '$this->func_lipsum(%s)')
            ->newFunc('round', 'round(%s)')
            ->newFunc('min')
            ->newFunc('max')
            ->newFunc('trim')
            ->newFunc('join', '$this->filter_join(%s)')
            ->newFunc('repeat', '$this->func_repeat(%s)')
            ->newFunc('json_encode', '$this->func_json_encode(%s)')
            ->newFunc('explode', 'explode(%s)')
            ->newFunc('price', 'number_format(%s,0,"."," ")')
            ->newFunc('default', '$this->filter_default(%s)')
            ->newFunc('var_export', array($this, 'function_var_export'))
            ->newFunc('justifyleft', '$this->func_justifyL(%s)')
            ->newFunc('slice', '$this->func_slice(%s)')
            ->newFunc('range', '$this->func_range(%s)')
            ->newFunc('keys', '$this->func_keys(%s)')
            ->newFunc('callex', '$this->callex(%s)')
            ->newFunc('attribute', '$this->attr(%s)')
            ->newFunc('call', '$this->call($par,%s)')
            ->newFunc('translit', 'translit(%s)')
            ->newFunc('shortcode', '$this->shortcode(%s)')
            ->newFunc('format', 'sprintf(%s)')
            ->newFunc('setarray', '$this->func_setarray(%s)')
            ->newFunc('link', '$this->func_enginelink(%s)')
            ->newFunc('fileurl', '$this->func_fileurl(%s)')
            ->newFunc('truncate', '$this->func_truncate(%s)')
            ->newFunc('tourl', '$this->func_2url(%s)')
            ->newFunc('date', '$this->func_date(%s)')
            ->newFunc('finnumb', '$this->func_finnumb(%s)')
            ->newFunc('right', '$this->func_rights(%s)')
            ->newFunc('russuf', '$this->func_russuf(%s)')
            ->newFunc('reg', '$this->func_reg(%s)')
            ->newFunc('in_array', '$this->func_in_array(%s)')
            ->newFunc('in_array', '$this->func_in_array(%s)')
            ->newFunc('is_array', '$this->func_is_array(%s)')
            // ->newFunc('parent', 'parent::_styles(%s)')
            ->newFunc('parent', array($this, 'function_parent'))
            ->newFunc('debug', '\ENGINE::debug(%s)')
            ->newOp1('_echo_', array($this, '_echo_'))
            ->newFunc('external', array($this, '_external_'));

    }

    /**
     * @param $msgId
     * @param object|null $lex
     * @throws CompilationException
     */
    function error($msgId, $lex = null)
    {
        $mess = tpl_base::pps($this->error_msg[$msgId], $msgId);
        if (is_null($lex)) {
            $lex = $this->op;
        }
        if (!is_null($lex)) {
            // count a string
            $lexpos = 0;
            $line = 0;
            $this->scaner->refillNL();
            foreach ($this->scaner->lines as $k => $v) {
                if ($k >= $lex->pos) break;
                $lexpos = $k;
                $line = $v;
            }

            $mess .= sprintf("\n" . 'file: %s<br>line:%s, pos:%s lex:"%s"'
                , self::$filename
                , $line + 1, tpl_base::pps($lex->pos, -1) - $lexpos, tpl_base::pps($lex->val, -1));
        }
        throw new CompilationException($mess);
    }

    /**
     * конвертирование операнда в то или иное состояние
     * @param array $types - массив с именами типов для конвертирвоания
     * @param operand $res - операнд
     * @return operand
     * @throws CompilationException
     */
    function to($types, &$res)
    {
        //конвертер операнда в то или иное состояние
        if (!is_array($types)) $types = array($types);
        if (is_string($res)) {
            $res = $this->oper($res, self::TYPE_STRING);
        }
        if (!is_object($res)) return $res;

        foreach ($types as $type)
            switch ($type) {
                /*
                * служебные операции
                */
                case 'trimln': // удалить первый NewLine из строки
                    $res->val = preg_replace('/^[ \t]*\r?\n/', '', $res->val);
                    break;
                case 'triml':
                    $res->val = preg_replace("/^\s*/s", '', $res->val);
                    break;
                case 'value':
                    if ($res->type == self::TYPE_OBJECT) {
                        return call_user_func($res->handler, $res, '', 'value');
                    }
                    return $res->val;
                /*
                * операции внешнего уровня
                */
                case self::TYPE_SENTENSE:
                    if ($res->type == self::TYPE_SENTENSE) break;
                    $this->to('*', $res);
                    if ($res->val == "''") $res->val = '';
                    else
                        $res->val = '$result.=' . $res->val . ';'; //TODO: завязка на порождаемый язык
                    $res->type = self::TYPE_SENTENSE;
                    break;
                /*
                * просто литерал
                */
                case self::TYPE_LITERAL:
                    if ($res->type == self::TYPE_ID || $res->type == self::TYPE_STRING2) {
                    } else {
                        $this->error('plain literal expected');
                    }
                    break;
                /*
                * преобразования
                */
                case 'I':
                case self::TYPE_XID:
                    if ($res->type == self::TYPE_ID || $res->type == self::TYPE_STRING2) {
                        if ($this->checkId($res->val)) {
                            $res->val = '$' . $res->val;
                            $res->type = self::TYPE_OPERAND;
                        } else {
                            $this->_store_external($res->val);
                            $res->val = '$par[\'' . $res->val . '\']';
                            $res->type = self::TYPE_XID;
                        };
                    } elseif ($res->type == self::TYPE_SLICE) {
                        if (!empty($res->list)) {
                            $this->to('I', $res->list[0]);
                            $res->val = $res->list[0]->val;
                            $condition = sprintf('$this->func_bk(%s', $res->list[0]->val);

                            array_shift($res->list);
                            foreach ($res->list as $el) {
                                if ($el->type == self::TYPE_ID) {
                                    $el->type = self::TYPE_STRING;// вырезка через точку - это вырезка через индекс
                                }
                                $this->to('S', $el);
                                $condition .= sprintf(',%s', $el->val);
                            }
                            $res->val = $condition . ')';
                            unset($res->list);
                        }
                        $res->type = self::TYPE_XSTRING;
                    } elseif ($res->type == self::TYPE_LIST) {
                        $this->to(self::TYPE_XLIST, $res);
                    } /*elseif ($res->type!=self::TYPE_XID){
	    		$this->error('waiting for ID')	;
	    	}*/;
                    break;
                case self::TYPE_XLIST:
                    $this->to('L', $res);
                    $res->val = 'array(' . $res->val . ')';
                    $res->type = self::TYPE_XID;
                    break;

                case 'B':
                case self::TYPE_XBOOLEAN:
                    if ($res->type == self::TYPE_ID || $res->type == self::TYPE_STRING2) {
                        $this->to('I', $res);
                    } elseif ($res->type == self::TYPE_STRING) {
                        $this->to('S', $res);
                    }
                    if ($res->type == self::TYPE_XID) {
                        $res->val = '(isset(' . $res->val . ') && !empty(' . $res->val . '))';
                        break;
                    } elseif ($res->type == self::TYPE_XSTRING) {
                        $res->val = '!empty(' . $res->val . '))';
                        break;
                    }
                // продолжаем то, что ниже!!!!!!!!!
                case 'L':
                    if ($res->type == self::TYPE_LIST) {
                        $op = array();
                        for ($i = 0; $i < count($res->value['keys']); $i++) {
                            $x = '';
                            if (!empty($res->value['value'][$i])) {
                                $x .= $this->to('*', $res->value['value'][$i])->val . '=>';
                            }
                            $x .= $this->to('*', $res->value['keys'][$i])->val;
                            $op[] = $x;
                        }
                        $res->val = implode(',', $op);
                        $res->type = self::TYPE_XLIST;
                    }
                // break не нeнужен !!!
                case '*':
                case 'S':
                case 'D':
                case self::TYPE_OPERAND:
                case self::TYPE_STRING:
                    if ($res->type == self::TYPE_ID || $res->type == self::TYPE_STRING2) $this->to('I', $res);
                    if ($res->type == self::TYPE_OBJECT) {
                        $res->val = call_user_func($res->handler, $res, '', 'value');
                        $res->type = self::TYPE_OPERAND;
                    }
                    if ($res->type == self::TYPE_SLICE) $this->to('I', $res);
                    if ($res->type == self::TYPE_LIST) {
                        $arr = array();
                        if (isset($res->value['keys'])) {
                            for ($i = 0; $i < count($res->value['keys']); $i++) {
                                $arr[] = $this->to('S', $res->value['keys'][$i])->val;
                            }
                        } else {
                            for ($i = 0; $i < count($res->value); $i++) {
                                $arr[] = $this->to('S', $res->value[$i])->val;
                            }
                        }
                        $res->val = implode(',', $arr);
                        $res->type = self::TYPE_XSTRING;
                    }
                    if ($res->type == self::TYPE_STRING || $res->type == self::TYPE_STRING1) {
                        $res->val = "'" . addcslashes($res->val, "'\\") . "'";
                        $res->type = self::TYPE_XSTRING;
                    }
                    if ($res->type == self::TYPE_XID) {
                        $res->val = '(isset(' . $res->val . ')?' . $res->val . ':"")';
                        $res->type = self::TYPE_XSTRING;
                    }
                    //

                    break;
            }
        return $res;
    }

    function _echo_($op)
    {
        return $this->to('S', $op);
    }

    /**
     * фильтр - replace
     * @param operand $op1 - TYPE_ID - имя функции
     * @param operand $op2 - TYPE_LIST - параметры функции
     */
    function function_replace($op1, $op2)
    {
        $op1->val = '$this->func_replace(' . $this->to('S', $op2->value['keys'][0])->val
            . ',' . $this->to('S', $op2->value['keys'][1])->val
            . ',' . $this->to('S', $op2->value['keys'][2])->val
            . ')';
        $op1->type = "TYPE_OPERAND";
        return $op1;
    }

    /**
     * фильтр - replace
     * @param operand $op1 - TYPE_ID - имя функции
     * @param operand $op2 - TYPE_LIST - параметры функции
     * @return operand
     * @throws CompilationException
     */
    function function_var_export($op1, $op2)
    {
        //{# '.preg_replace(['/\s*array\s*\(\s*/s','/\s*\)\s*/s'],['[',']'],var_export($par['extern'],true)).';#}
        $value = array();
        // foreach($op2->value['keys'] as &$v){
        $value = $this->to('S', $op2)->val;
        //  }
        //array_unshift($value,'$par');
        $op1->val = "preg_replace(['/\s*array\s*\(\s*/s','/\s*\)\s*/s'],['[',']'],var_export(" . $value . ',true))';
        $op1->type = "TYPE_OPERAND";
        return $op1;
    }

    function _store_external($ext_key, $ext_value = [])
    {
        // найдем opensentence уровня класс или макра
        // $block=& $this->opensent('block');
        $o =& $this->opensent('class');
        if (!isset($o['extern']))
            $o['extern'] = [];
        if (!isset($o['extern'][$ext_key]) || !empty($ext_value))
            $o['extern'][$ext_key] = $ext_value;
    }

    /**
     * Описатель внешней переменой, external(variable,a,b,c,d,e,f...) появляется external=['variable'=['a','b'...,'f'...]]
     * @param $op1
     * @param $op2
     * @return mixed
     */
    function _external_($op1, $op2 = [])
    {
        $ext_key = null;
        $ext_value = [];
        if (!empty($op2) && $op2->type == self::TYPE_LIST) {
            foreach ($op2->value['keys'] as $v) {
                if (is_null($ext_key)) {
                    $ext_key = $v->orig ?: $v->val;
                } else {
                    $ext_value[] = $v->orig ?: $v->val;
                }
            }
        } else {
            $ext_key = $op1->orig;
            $ext_value = [];
        }
        // найдем opensentence уровня класс или макра
        $this->_store_external($ext_key, $ext_value);
        return false;
    }

    /**
     * фильтр - replace
     * @param operand $op1 - TYPE_ID - имя функции
     * @param operand $op2 - TYPE_LIST - параметры функции
     * @return operand
     * @throws CompilationException
     */
    function function_parent($op1, $op2)
    {
        $value = array();
        foreach ($op2->value['keys'] as &$v) {
            $value[] = $this->to('S', $v)->val;
        }
        array_unshift($value, '$par');

        $op1->val = 'parent::_' . $this->currentFunction . '(' . implode(',', $value) . ')';
        $op1->type = "TYPE_OPERAND";
        return $op1;
    }

    function utford($c)
    {
        if (ord($c{0}) > 0xc0) {
            $x = unpack('N', mb_convert_encoding($c, 'UCS-4BE', 'UTF-8'));
            return $x[1];
            /*           $x = 0;
          $i = 0;
          while (isset($c{$i})) {
              $x += $x * 256 + ord($c{$i++});
          }
          return $x; */
        } else
            return ord($c{0});
    }

    function utfchr($i)
    {
        if ($i < 256) return chr($i);
        /* $x = '';
       while ($i > 0) {
           $x .= chr($i % 256);//.$x;
           $i=$i>>8;
       } */
        return mb_convert_encoding('&#' . $i . ';', 'UTF-8', 'HTML-ENTITIES');

    }

    function reprange($op1, $op2)
    {
        if ($op1->type == self::TYPE_DIGIT && $op2->type == self::TYPE_DIGIT) {
            $i = $op2->val;
            $y = $op1->val;
            $step = $i > $y ? -1 : 1;
            for (; $i != $y; $i += $step) {
                $this->pushOp($this->oper($i, self::TYPE_DIGIT));
            }
            $this->pushOp($this->oper($i, self::TYPE_DIGIT));
            return false;
        } elseif (($op1->type == self::TYPE_STRING && $op2->type == self::TYPE_STRING) or ($op1->type == self::TYPE_STRING1 && $op2->type == self::TYPE_STRING1)) {
            $i = $this->utford($op2->val);
            $y = $this->utford($op1->val);
            $step = $i > $y ? -1 : 1;
            for (; $i != $y; $i += $step) {
                $this->pushOp($this->oper($this->utfchr($i), self::TYPE_STRING));
            }
            $this->pushOp($this->oper($this->utfchr($i), self::TYPE_STRING));
            return false;
        } else {
            return $this->oper('$this->func_reprange(%s,%s)', self::TYPE_LIST);
        }
    }
}
