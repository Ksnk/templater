<?php
/**
 * Jinja language parcer
 * <%=point('hat','jscomment');




%>
 */

//if (!defined('TEMPLATE_PATH')) define('TEMPLATE_PATH', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'templates');

class tpl_parser extends nat_parser
{
    // дополнительные константы типов
    const
        TYPE_SENTENSE = 101,
        TYPE_LITERAL = 102
;
    protected

        $locals = array(), // стек идентификаторов с областью видимости
        $ids_low = 0; // нижняя граница области видимости

    public
        $opensentence = array(), // комплект открытых тегов, для портирования
        /** @var string - скрипт для выполнения */
        $script,
        /** @var boolean - сохранять-несохранять */
        $storeparams;

    function __construct()
    {
        parent::__construct(array(
            // Enviroment setting
            'BLOCK_START' => '{%',
            'BLOCK_END' => '%}',
            'VARIABLE_START' => '{{',
            'VARIABLE_END' => '}}',
            'COMMENT_START' => '{#',
            'COMMENT_END' => '#}',
            'COMMENT_LINE' => '##',
            'trim' => true,
            'COMPRESS_START_BLOCK' => true
        ));
        $this->t_conv['E'] = self::TYPE_SENTENSE;
        $this->error_msg['unexpected construction'] = 'something strange catched.';

    }

    /**
     * Вызов функции op1 с параметрами op2
     * @param  operand $op1
     * @param  operand $op2
     * @return operand
     */
    function function_callstack($op1, $op2)
    {
        //вырезка из массива
        if ($op1->type == self::TYPE_OBJECT) {
            $op1 = call_user_func($op1->handler, $op1, $op2, 'call');
            if ($op1)
                $this->pushOp($op1);
        } elseif (isset($this->func[$op1->val])) {
            //$op2 схлоп
            $x = $this->func[$op1->val]->val;
            if (is_callable($x)) {
                $op = call_user_func($x, $op1, $op2);
                if ($op)
                    $this->pushOp($op);
            } elseif (is_string($x)) {
                $op1->val = sprintf($x, $this->to('S', $op2)->val);
                $op1->type = self::TYPE_OPERAND;
            } else
                $this->error('wtf3!!!');
        } else {
            //вызов макрокоманды
            if ($op2->type == self::TYPE_LIST) {
                // call macro
                $arr = array();
                $arrkeys = array();
                for ($i = 0, $max = count($op2->value['value']); $i < $max; $i++) {
                    if (is_null($op2->value['value'][$i])) {
                        $arr[] = $this->to('S', $op2->value['keys'][$i])->val;
                    } else {
                        $arrkeys[] = array(
                            'key' => $op2->value['value'][$i]->val,
                            'value' => $this->to('S', $op2->value['keys'][$i])->val
                        );
                    }
                }
                if($op1->type==self::TYPE_SLICE){
                    // вызов объектного макроса
                    $this->to('I',$op1->list[0]);
                    //$op1->val =$op1->list[0]->val;
                    $op1->val = $this->template('callmacroex', array('par1' => $op1->list[0]->val,'mm'=>$op1->list[1]->val, 'param' => $arr));
                }  else
                    $op1->val = $this->template('callmacro', array('name' => $op1, 'param' => $arr, 'parkeys' => $arrkeys));
                $op1->type = self::TYPE_SENTENSE;
                return $op1;
            } else
                $this->error('have no function ' . $op1);
        }
        return $op1;
    }

    /**
     * отработка тега block, блок верхнего уровня является "корневым" элементом
     * @param array|null $tag_waitingfor
     * @param null $tag
     */
    function block_internal($tag_waitingfor = array(), &$tag = null)
    {
        if (empty($tag))
            $tag = array('tag' => 'block', 'operand' => count($this->operand));
        $data = array();
        $this->newop('(');
        do {
            if ($this->getNext() == self::TYPE_EOF) {
                $this->back();
                break;
            }
            if (!empty($tag_waitingfor) && in_array($this->op->val, $tag_waitingfor)) {
                $this->back();
                break;
            }
            if ($this->op->type == self::TYPE_COMMA && empty($this->op->val)) {

            } elseif ($this->exec_tag($this->op->val)) {
                $op = $this->popOp();
                if (!empty($op)) {
                    $op = $this->to('S', $op);
                    if ($op->type == self::TYPE_SENTENSE)
                        $data[] = array('data' => $op->val);
                    else {
                        $data[] = array('string' => '(' . $op->val . ')');
                    }
                }
                //              break;
            } else {
                $this->back(); // goto TYPE_ECHO
                $this->getExpression();
                /* if ($this->op->val != '') {
                   $this->error('unexpected construction2');
               } */
                if(!($op = $this->popOp())) break;
                $op = $this->to('S', $op);
                if ($op->type == self::TYPE_SENTENSE)
                    $data[] = array('data' => $op->val);
                else if ($op->type == self::TYPE_XSTRING) {
                    if (!empty($op->val))
                        $data[] = array('string' => $op->val);
                } else {
                    $data[] = array('string' => '(' . $op->val . ')');
                }
                //$this->getNext();
            }
        } while (true);
        $this->newop(')'); // свернуть все операции

        /**
         * оптимизация данных для вывода блока в шаблоне
         */
        array_unshift($data, array('string' => "''"));
        array_push($data, array('string' => "''"));
        $tag['data'] = array();
        $laststring = false;
        $lastidx = -1;
        foreach ($data as $d) {
            if (empty($d['data'])) {
                if ($laststring) {
                    if ($d['string'] != '' && $d['string'] != "''") {
                        if (is_array($tag['data'][$lastidx]['string'])) {
                            array_push($tag['data'][$lastidx]['string'], $d['string']);
                        } else {
                            if ($tag['data'][$lastidx]['string'] == "''")
                                $tag['data'][$lastidx]['string'] = array(
                                    $d['string']
                                );
                            else
                                $tag['data'][$lastidx]['string'] = array(
                                    $tag['data'][$lastidx]['string'],
                                    $d['string']
                                );
                        }
                    }
                } else {
                    $laststring = true;
                    $tag['data'][] = $d;
                    $lastidx++;
                }
            } else {
                $laststring = false;
                $tag['data'][] = $d;
                $lastidx++;
            }
        }

        if (!empty($tag['name'])) {
            $t =& $this->opensent('class');
            $t['data'][] = $this->template('block', $tag);
            $this->pushOp($this->oper($this->template('callblock', $tag), self::TYPE_OPERAND));
        } else
            $this->pushOp($this->oper($this->template('block', $tag), self::TYPE_SENTENSE));
    }

    /**
     * функция проверяем комплект локальных переменных
     * @param $id
     * @return bool
     */
    function checkId($id)
    {
        for ($i = $this->ids_low; $i < count($this->locals); $i++) {
            if ($this->locals[$i] == $id)
                return true;
        }
        return false;
    }

    protected function get_reg(&$types)
    {
        /**
         * массив типов, определенных регуляркой. Повязан на суб-номер в регулярке
         */
        $types = array(0 //0 - just skip
        , 0, self::TYPE_STRING
        , self::TYPE_DIGIT
        , self::TYPE_COMMA
        , self::TYPE_OPERATION //5
        , self::TYPE_ID //6  TYPE_OPERAND
        , self::TYPE_OPERATION //7
        , self::TYPE_COMMA //8
        );
        return '#[\n\r\s]*' // # - пропуск пробелов
            . '(?:' #1#2 - заквоченные без слеша на конце - TYPE_OPERAND
            . '([\'`"])((?:[^\\1\\\\]|\\\\.)*?)\\1'
            . '|(\d\w*)' //	#6 - цифры это слова, начинающиеся с цифры - TYPE_DIGITS
            . '|(-?' . $this->options['VARIABLE_END'] . '|-?' . $this->options['BLOCK_END'] . ')' //	Хвосты тегов
            . '|' #4,5 - многобуквенные операции - TYPE_OPERATION
            . '((?:' . implode('|', $this->cake['WORD_OP']) . ')(?=\b)|' . implode('|', $this->cake['MC_JUST_OP']) . ')'
            . '|(\w+)' #6 - просто слова TYPE_OPERAND
            . '|' #7 - однобуквенные операции - TYPE_OPERATION
            . '([' . implode('', $this->cake['JUST_OP']) . '\!])'
            . '|(.)' #8 - однобуквенные знаки препинания - TYPE_COMMA
            . ')#si';

    }

    /**
     * первый проход компилятора - свертка лексем;
     * Здесь и только здесь определяется внешний вид оформления тегов шаблонизатора;
     * регулярные пляски тоже только здесь
     * @param $script
     */
    function makelex($script)
    {
        $this->script = $script;
        $this->operand = array();
        $this->operation = array();
        $this->lex = array();
        $this->curlex = 0;
        $types = array();
        $curptr = 0;

        // привязываем номер строки к позиции транслятора
        $this->scanNl($script);

        // забираем лексемную регулярку
        $reg = $this->get_reg($types);
        $reg0 = "~(.*?)(";
        foreach (array('COMMENT_LINE', 'VARIABLE_START', 'COMMENT_START') as $r)
            $reg0 .= preg_quote($this->options[$r], '#~') . '|';
        $reg0 .= preg_quote($this->options['BLOCK_START'], '#~') . '|$)(\-?)~si';

        $total = strlen($script);

        $triml = false;
        $strcns = '';
        // найти начало следующего тега шаблонизатора
        while ($curptr < $total && preg_match($reg0, &$script, $m, 0, $curptr)) {
            if ($m[0] == '')
                break; // что-то незаладилось в реге
            $strcns .= $m[1];
            if (!empty($strcns)) {
                if ($m[3] == '-') {
                    $strcns = preg_replace('/\s+$/s', '', $strcns);
                } else if ($m[2] == $this->options['BLOCK_START'] && $this->isOption('COMPRESS_START_BLOCK')) {
                    $strcns = preg_replace('/(\s*\r?\n?|^)\s*$/', '', $strcns);
                }
            }

            if ($triml) {
                $strcns = preg_replace('/^\s+/s', '', $strcns);
                $triml = false;
            }

            if ($m[1] !== '') {
                if ($m[2] != $this->options['COMMENT_LINE'] && $m[2] != $this->options['COMMENT_START']) {
                    $this->lex[] = $this->oper('_echo_', self::TYPE_OPERATION, $curptr);
                    $this->lex[] = $this->oper($strcns, self::TYPE_STRING, $curptr);
                    $this->lex[] = $this->oper('', self::TYPE_COMMA, $curptr);
                    $strcns = '';
                } else {
                    $strcns=preg_replace('/\s\s+$/m'," ",$strcns);
                }
            }

            $curptr += strlen($m[0]);

            if ($m[2] == "") break; // нашли финальный кусок

            if ($m[2] == $this->options['COMMENT_LINE']) { // комментарий на всю линию
                if (preg_match('~(.*?)\r?\n~i', &$script, $mm, 0, $curptr)) {
                    $curptr += strlen($mm[1]);
                    continue;
                }
            } elseif ($m[2] == $this->options['COMMENT_START']) { // комментарий? - ищем пару и продолжаем цирк
                //$rreg='~.*?'.preg_quote($this->options['COMMENT_END'],'#~').'~si';
                if (preg_match('~.*?' . preg_quote($this->options['COMMENT_END'], '#~') . '~si', &$script, $m, 0, $curptr)) {
                    $curptr += strlen($m[0]);
                    continue;
                }
            } else {
                if ($m[2] != $this->options['BLOCK_START']) {
                    $this->lex[] = $this->oper('_echo_', self::TYPE_OPERATION, $curptr);
                    //   $this->lex[] = $this->oper('(', self::TYPE_COMMA, $curptr);
                }
            }

            // отрезаем следующую лексему шаблонизатора
            $first = true;
            while ($curptr < $total && preg_match($reg, &$script, $m, 0, $curptr)) {
                $pos = $curptr;
                $curptr += strlen($m[0]);
                if (!empty($m[1])) {
                    $op = $this->oper(stripslashes($m[2]), self::TYPE_STRING, $pos);
                    if ($m[1] == "'")
                        $op->type = self::TYPE_STRING1;
                    elseif ($m[1] == "`")
                        $op->type = self::TYPE_STRING2;

                    $this->lex[] = $op;
                } else {
                    for ($x = count($types) - 1; $x > 2; $x--) {
                        if (isset($m[$x]) && $m[$x] != "") {
                            if ($types[$x] == self::TYPE_COMMA && strlen($m[$x]) > 1) {
                                if ($m[$x]{0} == '-') {
                                    $triml = true;
                                    $m[$x] = substr($m[$x], 1);
                                }
                                if ($m[$x] == $this->options['VARIABLE_END']) {
                                    // $this->lex[] = $this->oper(')', self::TYPE_COMMA, $curptr);
                                }
                                $this->lex[] = $this->oper('', self::TYPE_COMMA, $curptr);
                                break 2;
                            }
                            $op = $this->oper(strtolower($m[$x]), $types[$x], $pos);
                            $op->orig = $m[$x];
                            $this->lex[] = $op;

                            // разбираемся с тегом RAW
                            if ($first && $m[$x] == 'raw') {
                                // ищем закрывающий тег raw
                                if (!preg_match('~.*?'
                                        . preg_quote($this->options['BLOCK_END'], '#~')
                                        . '(.*?)'
                                        . preg_quote($this->options['BLOCK_START'], '#~')
                                        . '\s*endraw\s*'
                                        . preg_quote($this->options['BLOCK_END'], '#~')
                                        . '~si',
                                    &$script, $m, 0, $curptr)
                                )
                                    $this->error('endraw missed');
                                $curptr += strlen($m[0]);
                                array_pop($this->lex);
                                array_pop($this->lex);
                                $this->lex[] = $this->oper($m[1], self::TYPE_STRING, $curptr);
                                break 2;
                            } else
                                break;
                        }
                    }
                }
                $first = false;
            }
        }
        $this->lex[] = $this->oper("\x1b", self::TYPE_EOF, $curptr);
    }

    function newId($op)
    {
        // установить ID как новый идентификатор
        if (is_string($op))
            $this->locals[] = $op;
        else
            $this->locals[] = $op->val;
        return $op;
    }

    function &opensent($sent)
    {
        for ($i = count($this->opensentence) - 1; $i >= 0; $i--) {
            if ($this->opensentence[$i]['tag'] == $sent)
                return $this->opensentence[$i];
        }
        return null;
    }

    function function_filter($op1, $op2)
    {
        $this->pushOp($op2);
        $this->pushOp($op1);
        $this->storeparams = 1;
        return false;
    }

    function function_point($op1, $op2)
    {
        //вырезка из массива
        if ($op1->type == self::TYPE_XID && $op1->val == 'self') {
            // игнорируем self как вредный  элемент
            $op2->type = self::TYPE_XID;
            return $op2;
        }
        if ($op1->type == self::TYPE_OBJECT) {
            // вызов.
            return call_user_func($op1->handler, $op1, $op2, 'attr');
        }
        return $this->function_scratch($op1,$op2);
    }

    /**
     * разрешить неизвестный ID.
     * @param operand $op
     * @return mixed|\operand
     */
    function &resolve_id(&$op)
    {
        return $this->pushOp($op);
    }

    /**
     * отрабoтка тега macros
     * @example
     * // функция - описание макрокоманды
     * function _macroname($namedpar, //noname section
     *         $par1=null,$par2=1,,) {
     *   if(!empty($namedpar)) export($namedpar);
     *   ...
     * }
     * @example
     * // вызов макрокоманды
     * if(!empty($this->macros[macroname]))
     * call_user_func($this->macros[macroname],$namedpar,$par1,$par2,...);
     *
     * неопределенные функции автоматически становятся макрами! Регистрировать не надо
     *
     * @example
     * // конструктор класса
     * if(!empty($this->macros[macroname]))
     * $this->macros[macroname]=array($this,'_'.macroname);
     *
     * @example
     * // импорт
     * $this->imported['TEMPLATENAME']=new TEMPLATENAME();
     * array_merge($this->macros,$this->imported['TEMPLATENAME']->macros)
     *
     */
    function tag_macro()
    {
        $tag = array('tag' => 'macros', 'operand' => count($this->operand), 'data' => array());
        $this->getNext(); // name of macros
        // зарегистрировать как функцию
        $tag['name'] = $this->to(self::TYPE_LITERAL, $this->op)->val;
        $this->getNext(); // name of macros
        if ($this->op->val != '(') {
            $this->error('expected macro parameters');
        }
        $par = $this->get_Parameters_list('=');
        $arr = array();
        for ($i = 0, $max = count($par['value']); $i < $max; $i++) {
            if (is_null($par['value'][$i])) {
                $arr[] = array('name' => $this->to(self::TYPE_LITERAL, $par['keys'][$i])->val);
            } else {
                $v = $this->to('S', $par['keys'][$i])->val;
                if (!$v) $v = '0 ';
                $arr[] = array(
                    'name' => $this->to(self::TYPE_LITERAL, $par['value'][$i])->val,
                    'value' => $v
                );
            }
        }
        // $this->op->param=$arr;
        $tag['param'] = $arr;
        $this->getNext();
        if ($this->op->val != ')')
            $this->error('expected )');
        //       $this->getNext();
        $id_count = count($this->locals);
        foreach ($tag['param'] as $v) {
            $this->newId($v['name']);
        }
        $this->block_internal(array('endmacro'), $tag);
        /*$op=*/
        $this->popOp();
        $tag['body'] = $this->template('block', $tag);
        array_splice($this->locals, $id_count);
        $this->getNext();
        if ($this->op->val != 'endmacro')
            $this->error('there is no endmacro tag');
        // добавляем в открытый класс определение нового метода
        $sent =& $this->opensent('class');
        if (!empty($sent)) {
            if (empty($sent['macro'])) {
                $sent['macro'] = array();
            }
            $sent['macro'][] = $tag['name'];
        }
    }

    /**
     * отрабoтка тега block
     */
    function tag_block()
    {
        $tag = array('tag' => 'block', 'operand' => count($this->operand), 'data' => array());
        $this->getExpression(); // получили имя идентификатора
        $tag['name'] = $this->popOp()->val;
        $this->getNext();
        $this->block_internal(array('endblock'), $tag);
        $this->getNext();
        if ($this->op->type != self::TYPE_COMMA)
            $this->getNext();
    }

    /**
     * отрабoтка тега for
     * @internal param $id
     */

    function tag_extends()
    {
        $this->getExpression();
        $op = $this->popOp();
        $sent =& $this->opensent('class');
        if (!empty($sent)) {
            $sent['extends'] = preg_replace('~\..*$~', '', basename($op->val));
        }
        // $this->getNext(); // съели символ, закрывающий тег
    }

    /**
     * отрабoтка тега if
     * @return
     */
    function tag_if()
    {
        // парсинг тега for
        // полная форма:
        // if EXPRESSION
        // elif EXPRESSION
        // elif EXPRESSION
        // else EXPRESSION
        // endif
        $tag = array('tag' => 'if', 'operand' => count($this->operand), 'data' => array());

        do {
            // сюда входим с уже полученым тегом if или elif
            $this->getExpression();
            $op = $this->popOp();
            $data = array(
                'if' => $this->to(array('B', 'value'), $op)
            );
            // $this->getNext(); // выдали таг
            $this->block_internal(array('elseif', 'elif', 'else', 'endif'));
            $op = $this->popOp();
            $data['then'] = $this->to(array(self::TYPE_SENTENSE, 'value'), $op);
            $tag['data'][] = $data;
            $this->getNext(); // выдали таг
            if ($this->op->val == 'endif')
                break;
            if ($this->op->val == 'else') {
                // $this->getNext();
                $this->block_internal(array('endif'));
                $op = $this->popOp();
                $data = array(
                    'if' => false,
                    'then' => $this->to(array(self::TYPE_SENTENSE, 'value'), $op)
                );
                $tag['data'][] = $data;
                $this->getNext(); // выдали таг
                break;
            }
        } while (true);
        // $this->getNext(); // съели символ, закрывающий тег
        $this->pushOp($this->oper($this->template('if', $tag), self::TYPE_SENTENSE));
        return;
    }

    function tag_import()
    {
        //$set =array('tag'=>'import','operand'=>count($this->operand));
        $this->getExpression(); // получили имя файла для импорта
        $op = $this->popOp();
        $t =& $this->opensent('class');
        $t['import'][] = basename($op->val, '.jtpl');
        //   $this->getNext();
        return false;
    }

    /**
     *
     *  тег SET
     *
     */
    function tag_set()
    {
        $set = array('tag' => 'set', 'operand' => count($this->operand));
        $this->getExpression(); // получили имя идентификатора
        $id = $this->newId($this->popOp());
        $set['id'] = $this->to(array('I', 'value'), $id);
        //$set['id'] = $this->to(array('I', 'value'), $set['id']);
        $this->getNext();
        if ($this->op->val != '=')
            $this->error('unexpected construction9');
        $this->getExpression();
        $set['res'] = $this->popOp();
        if ($set['res']->type == self::TYPE_LIST)
            $set['res'] = $this->to(self::TYPE_XLIST, $set['res'])->val;
        else
            $set['res'] = $this->to('*', $set['res'])->val;
        // $this->getNext();
        $this->pushOp($this->oper($this->template('set', $set), self::TYPE_SENTENSE));
        return;
    }


    /**
     * функция компиляции одного подшаблона.
     * + сборка на стеке операндов готовой конструкции
     * + лексический анализ
     * @param string $class
     * @return mixed|string
     */
    function tplcalc($class = 'compiler')
    {
        $tag = array('tag' => 'class', 'import' => array(), 'macro' => array(), 'name' => $class, 'data' => array());
        $this->opensentence[] = &$tag;

        $tagx = array('tag' => 'block', 'name' => ' ', 'operand' => count($this->operand), 'data' => array());

        $this->block_internal(array(), $tagx);


        array_pop($this->opensentence);
        // TODO: разобраться с правильным наследованием _
        // сейчас просто удаляем метод _ из отнаследованного шаблона
        if (!empty($tag['extends'])) {
            for ($x = 0; $x < count($tag['data']); $x++) {
                if (preg_match('/function\s+_\s*\(/i', $tag['data'][$x]))
                    break;
            }
            unset($tag['data'][$x]);
        }
        return $this->template('class', $tag);
    }

    /**
     * Встроенные в класс рендерер ...
     * @param string $idx - имя подшаблона "" - корневой подшаблон
     * @param array $par - данные для рендеринга
     * @param string $tpl_class - имя базового шаблона
     * @return mixed|string
     */
    function template($idx = null, $par = null, $tpl_class = 'compiler')
    {
        static $tpl_compiler;
        if (!is_null($tpl_class) || empty($tpl_compiler)) {
            $tpl_compiler = 'tpl_' . pps($tpl_class, 'compiler');
            if (!class_exists($tpl_compiler)) {
                // попытка включить файл
                include_once (template_compiler::options('templates_dir') . $tpl_compiler . '.php');
            }
            $tpl_compiler = new $tpl_compiler();
        }

        if (!is_null($par)) {
            if (method_exists($tpl_compiler, '_' . $idx))
                return call_user_func(array($tpl_compiler, '_' . $idx), &$par);
            else
                printf('have no template "%s:%s"', 'tpl_compiler', '_' . $idx);
        }
        return '';
    }

    /**
     * {% macro | for |
     * проверить, есть ли зарезервированные обработчики этого тега
     * Проверяем
     * - список классов-расширений
     * - список собственных методов
     * @param string $tag
     * @return bool
     */
    function exec_tag($tag)
    {
        $name = 'tag_' . $tag;
        if (class_exists($name, false)) {
            $tag = new $name();
            $tag->execute($this);
        } else if (method_exists($this, $name)) {
            call_user_func(array($this, $name));
        } else
            return false;

        return true;
    }
}

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
            while ($loopdepth-- > 0 && $this->parcer->opensentence[$loopdepth]['tag'] != 'for') {
            }
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
     */
    function execute($parcer)
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
