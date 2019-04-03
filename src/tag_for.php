<?php
/**
 * Jinja language parcer
 * <%=point('hat','jscomment');




%>
 */

namespace Ksnk\templater ;

/**
 * класс - for
 */

class tag_for
{

    /**
     * @var tpl_parser
     */
    private $parcer,
        /** array */
        $tag;

    /**
     * Описание хелпера loop для тега for
     */
    function operand_loop($op1 = null, $attr = null, $reson = 'attr')
    {
        $_attr = property_exists($op1, 'attr') ? $op1->attr : '';
        $tag =& $this->tag;
        $loopdepth = $tag['loopdepth'];
        while (strpos($_attr, '.parent.loop') !== false) {
            $_attr = substr($_attr, 12);
            while ($loopdepth-- > 0 && $this->parcer->opensentence[$loopdepth]['tag'] != 'for')
                ; // yes! empty body
            $tag = &$this->parcer->opensentence[$loopdepth];
        }
        // найти ближайший открытый for и отметить, что loop там используется.
        if ($reson == 'call') {
            // рекурсивный вызов цикла еще раз
            if ($_attr == '.cycle') {
                $tag['loop_cycle'] = 'array(' . $this->parcer->to('S', $attr)->val . ')';
                return $this->parcer->oper('$this->loopcycle($loop' . $loopdepth . '_cycle)', tpl_parser::TYPE_OPERAND);
            } else {
                $this->parcer->error('calling not a callable construction ' . $_attr);
            }
        } else if (is_null($attr) || $attr instanceof tpl_parser) {
            $op = $this->parcer->oper('loop', tpl_parser::TYPE_OBJECT);
            $op->attr = '';
            $op->handler = array($this, 'operand_loop');
            return $op;
        } else if ($reson == 'attr') {
            if (is_object($attr)) $attr = $attr->val;
            if (in_array($attr,
                array('first', 'cycle', 'last', 'index0', 'loop', 'parent', 'revindex', 'revindex0', 'length', 'index')
            )
            ) {
                $op1->attr .= '.' . $attr;
                return $op1;
            } else {
                $this->parcer->error('undefined loop attribute(1)-"' . $attr . '"!');
            }
        } else if ($reson == 'value') {
            switch ($_attr) {
                case '.first':
                    $tag['loop_index'] = true;
                    return '$loop' . $loopdepth . '_index==1';
                case '.last' :
                    $tag['loop_index'] = true;
                    $tag['loop_last'] = true;
                    return '$loop' . $loopdepth . '_index==$loop' . $loopdepth . '_last';
                case '.cycle':
                    $tag['loop_cycle'] = true;
                    return '';
                case '.index0':
                    $tag['loop_index'] = true;
                    return '($loop' . $loopdepth . '_index-1)';
                case '.revindex':
                    $tag['loop_revindex'] = true;
                    $tag['loop_last'] = true;
                    return '$loop' . $loopdepth . '_revindex';
                case '.revindex0':
                    $tag['loop_revindex'] = true;
                    $tag['loop_last'] = true;
                    return '($loop' . $loopdepth . '_revindex-1)';
                case '.length':
                case '.index':
                    $tag['loop_index'] = true;
                    return '$loop' . $loopdepth . '_' . substr($_attr, 1);
                default :
                    $this->parcer->error('undefined loop attribute-"' . $_attr . '"!');
            }
        }
    }

    /**
     * @param tpl_parser $parcer
     * @throws CompilationException
     */
    function execute($parcer, $pos=0)
    {
        // парсинг тега for
        // полная форма:
        // for OPERAND in EXPRESSION [if EXPRESSION]
        // промежуточный else
        // финишный endfor
        $this->tag = array('tag' => 'for',
            'operand' => count($parcer->operand),
            'loopdepth' => count($parcer->opensentence)
        );
        $parcer->opensentence[] = &$this->tag;
        $parcer->getExpression(); // получили имя идентификатора
        // elseif (empty($op)) {
        // $this->error('improper FOR declaration');
        if (count($parcer->operand) <= 0) {
            $parcer->error('improper FOR declaration');
        } else
            $id = $parcer->newId($parcer->popOp());
        $this->tag['index'] = $parcer->to(array('I', 'value'), $id);
        //
        if ($parcer->op->val == ',') { // key-value pair selected
            $parcer->getNext();
            $parcer->getExpression();
            $id = $parcer->newId($parcer->popOp());
            $this->tag['index2'] = $parcer->to(array('I', 'value'), $id);
        }

        $this->parcer = $parcer;
        /*       $op = $parcer->oper('loop', self::TYPE_OBJECT);
               $op->handler = array($this, 'operand_loop');
               $parcer->newOpr('loop', $op);  */

        $parcer->newOpr('loop', array($this, 'operand_loop'));

        //$parcer->newId($parcer->oper('loop', self::TYPE_ID));
        do {
            $parcer->getNext();
            switch (strtolower($parcer->op->val)) {
                case 'in':
                    $parcer->getExpression();
                    // $this->tag['in'] = $parcer->popOp();
                    $id = $parcer->popOp();
                    $this->tag['in'] = $parcer->to(array('I', 'value'), $id);
                    break;
                case 'if':
                    $parcer->getExpression();
                    $id = $parcer->popOp();
                    $this->tag['if'] = $parcer->to(array('*', 'value'), $id);
                    break;
                case 'recursive':
                    $this->tag['recursive'] = true;
                    break;
                default:
                    if ($parcer->op->type == tpl_parser::TYPE_COMMA)
                        break 2;
                    else
                        $parcer->error('unexpected construction1');
            }
        } while (true);
        //$parcer->opensentence[]=$this->tag;

        $this->tag['else'] = false;
        do {
            $parcer->block_internal(array('else', 'endfor'));
            $parcer->getNext();
            $op = $parcer->popOp();
            if ($parcer->op->val == 'else') {
                $this->tag['body'] = $parcer->to(array(tpl_parser::TYPE_SENTENSE, 'value'), $op);
                $this->tag['else'] = true;
                // $parcer->getNext(); // съели символ, закрывающий тег
            } elseif ($parcer->op->val == 'endfor') {
                $parcer->getNext(); // съели символ, закрывающий тег
                if ($this->tag['else']) {
                    $this->tag['else'] = $parcer->to(array(tpl_parser::TYPE_SENTENSE, 'value'), $op);
                } else {
                    $this->tag['body'] = $parcer->to(array(tpl_parser::TYPE_SENTENSE, 'value'), $op);
                }
                // генерируем все это добро
                $parcer->pushOp($parcer->oper($parcer->template('for', $this->tag), tpl_parser::TYPE_SENTENSE));
                do {
                    $op = array_pop($parcer->opensentence);
                    if ($op['tag'] == 'for') break;
                } while (!empty($op) && true);

                break;
            }
        } while (true);
        //$parcer->getNext(); // съели символ, закрывающий тег

    }
}

