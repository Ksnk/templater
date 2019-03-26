<?php
/**
 * base class to build sintax analizator.
 * ----------------------------------------------------------------------------
 * $Id: Templater engine v 2.0 (C) by Ksnk (sergekoriakin@gmail.com).
 *      based on Twig sintax,
 * ver: 1.1-10-gd5c11d9, Last build: 1309171559
 * GIT: origin	https://github.com/Ksnk/templater (push)$
 * ----------------------------------------------------------------------------
 * License MIT - Serge Koriakin - 2012
 * ----------------------------------------------------------------------------
 */

/**
 * класс для вывода ошибок компиляции
 */
class CompilationException extends Exception
{
}

/**
 * внутренний базовый класс для хранения операндов на стеках
 */
class operand
{
    var
        $val // значение операнда
    , $type // тип операнда
    , $handler = '' // функция
    , $pos // позиция курсора
    , $prio // приоритет операции для операций
    , $orig //
//		, $unop // признак унарной операции для унарных операций
    ;

    function __construct($val, $type = nat_parser::TYPE_NONE, $pos = 0)
    {
        $this->val = $val;
        $this->type = $type;
        $this->pos = $pos;
    }

    function __toString()
    {
        return $this->val;
    }

    /*	// функция конвертирования операнда в новый тип
     function &_to($type){
         return $this;
     }*/
}

/**
 * псевдоуниверсальный парсер выражений со скобками
 * с контролем синтаксиса .
 * @author ksnk
 *
 */
class nat_parser
{

    const
        TYPE_OPERAND = 1
    , TYPE_XBOOLEAN = 2
    , TYPE_NONE = 3
    , TYPE_XDIGIT = 4
    , TYPE_XSTRING = 5
    , TYPE_XID = 6
    , TYPE_XLIST = 7
    , TYPE_OBJECT = 8
    , TYPE_STRING = 9
    , TYPE_DIGIT = 10
    , TYPE_OPERATION = 11
    , TYPE_ID = 12
    , TYPE_COMMA = 13
    , TYPE_EOF = 14
    , TYPE_STRING1 = 15
    , TYPE_STRING2 = 16
    , TYPE_LIST = 17
    , TYPE_SLICE = 18;

    protected
        $t_conv = array(
        '*' => self::TYPE_OPERAND,
        'B' => self::TYPE_XBOOLEAN,
        'D' => self::TYPE_XDIGIT,
        'S' => self::TYPE_XSTRING,
        'I' => self::TYPE_XID,
        'L' => self::TYPE_XLIST, // список значений через запятую
    );

    public
        /**
         * стек операндов
         */
        $operand = array(),
        /**
         * массив лексем
         */
        $lex = array(),
        /**
         * текущая операция
         */
        $op = null;

    protected

        /**
         * стек операций
         */
        $operation = array()

        /**
         * каунтер лексем
         */
    , $curlex = 0


        /**
         * скрипт для парсинга
         */
//		, $script='' // считается, что не нужен...

        /**
         * приоритеты операций
         */
    , $prio = array('(' => -1, ',' => -1, ')' => 0, "\x1b" => -10) // приоритеты бинарных операций

        /**
         * определения бинарных операций
         */
    , $binop = array()

        /**
         * определения функций
         */
    , $func = array()

        /**
         * определения унарных операций
         */
    , $unop = array()

        /**
         * определения унарных операций-суффиксов
         */
    , $suffop = array()

        /**
         * определения идентификаторов
         */
    , $ids = array()

        /**
         * сообщения об ошибках - задел на локализацию сообщений об ошибках
         */
    , $error_msg = array(
        #### внутренние ошибки движка
        # чтение после окончания текста - скрипт пустой или ошибка логики синтаксического анализатора
        'toofar' => 'empty script'
        # слишком много операций на стеке, ошибка логики парсера
    , 'error happen' => 'error happen when executing'
        # неопределенный идентификатор - ошибка инициализации парсера
    , 'unknown id' => 'unknown id '
        # неопределенная операция - ошибка инициализации парсера
    , 'undefined operation' => 'undefined operation'

        #### синтаксические ошибки скрипта
        # wtf
    , 'wtf' => 'wtf'
        # операция на месте операнда
    , 'undefined operation' => 'undefined operation'
        # нет закрывающей скобки
    , 'closed brackets missed' => 'closed brackets missed'
        # синтаксическая ошибка - много операций и мало операндов
    , 'nooperand' => 'sintax error'
        # не хватает скобок или лишние скобки, синтаксическая ошибка скрипта
    , 'error with brackets' => 'error with brackets'
        # неописанная унарная операция - ак такое может быть?
    , 'misplased operation' => 'misplased operation'
        # слишком много операндов на стеке, синтаксическая ошибка скрипта
    , 'something wrong' => 'something wrong with expression'
        # слишком много закрывающих-открывающих скобок, синтаксическая ошибка скрипта
    , 'open bracker found' => 'open bracker found'
    )

        /**
         * кусочки для сборки регулярки парсинга лексем
         * собираются при определении разнокалиберных операций
         */
    , $cake = array(
        'WORD_OP' => array(), // бинарные операции словные
        'MC_JUST_OP' => array(), // многобуквенные бинарные операции
        'JUST_OP' => array(), // однобуквенные бинарные операции
    )

        /**
         * массив начал строк для операции вывода ошибки
         */
    , $lines = array()

        /**
         * массив опций для изменения на лету
         */
    , $options = array(
        // Enviroment setting
    );

    /**
     * стандартный констуктор умеет в дополнении к стандартной функциональности потенциально
     * парсить вырезки из массивов
     * и вызывать функции с большим количеством параметров
     */
    function __construct($options = null)
    {
        $this
            ->newOp2('_scratch_', 11, array($this, 'function_scratch'))
            ->newOp2('_stack_', 10, array($this, 'function_callstack'));
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
    }

    function isOption($option, $value = true)
    {
        return $this->options[$option] == $value;
    }

    /**
     * поддержка вызова функции
     * @param operand $op1
     * @param operand $op2
     */
    function function_callstack($op1, $op2)
    {
        //вырезка из массива
        if ($op1->type == self::TYPE_OBJECT) {
            $op = call_user_func($op1->handler, $op1, $op2, 'call');
            if ($op)
                $this->pushOp($op);
        } else
            $this->error('have no function ' . $op1);
        return $op1;
    }


    /**
     * вырезка из массива
     * @param operand $op1
     * @param operand $op2
     */
    function function_scratch($op1, $op2)
    {
        //вырезка из массива
 /*       $this->to('I', $op1);
        $this->to('L', $op2);
        $op1->val .= '[' . $op2->val . ']';
        $op1->type = self::TYPE_XID; */
        if($op1->type==self::TYPE_SLICE) {
            $op1->list[] = $op2;
            return $op1;
        }
        if($op2->type==self::TYPE_SLICE) {
            array_unshift($op2->list,$op1);
            return $op2;
        }
        $op= new operand('',self::TYPE_SLICE);
        $op->list=array($op1,$op2);
        return $op;
    }

    /**
     *
     * @param $op1
     * @param $op2
     * @return mixed
     */
    function function_iif()
    {
        $op_else = $this->popOp();
        $op_then = $this->popOp();
        $op_if = $this->popOp();
        //вырезка из массива
        $op_if->val = $this->to('X', $op_if)->val . ' ? ' . $this->to('X', $op_then)->val . ' : ' . $this->to('X', $op_else)->val;
        $op_if->type = self::TYPE_OPERAND;
        $op_if->notempty = true;
        return $op_if;
    }

    /**
     * just skip
     */
    function function_skip($op1)
    {
        // затычка
        return $op1;
    }

    /**
     * Создание нового операнда. Для возможности переопределения класса операнда
     * Единственное место, где встречается слово operand - имя класса
     */
    function oper($value, $type = self::TYPE_NONE, $pos = 0)
    {
        return new operand($value, $type, $pos);
    }

    /**
     * Создание новой операции.
     */
    protected function operat($value, $prio = 10, $pos = 0)
    {
        $op = $this->oper($value, self::TYPE_NONE, $pos);
        $op->prio = $prio;
        return $op;
    }

    /**
     * getter-setter для стека операндов
     * @param $op
     */
    function &pushOp($op, $type = self::TYPE_OPERAND)
    {
        if (is_string($op)) {
            $op = $this->oper($op, $type);
        }
        array_push($this->operand, $op);
        return $op;
    }

    /**
     * забрать операнд с крышки стека
     */
    function popOp($safe = false)
    {
        if (is_null($op = array_pop($this->operand))) {
            if ($safe) {
                $this->error('nooperand');
            } else {
                $op = false;
            }
        }
        return $op;
    }

    /**
     * универсальный сеттер унарных-бинарных операций
     */
    private function &new_Op($op, $prio, &$array, $phpeq, $types = '*')
    {
        if (strpos($op, ' ') !== false) {
            foreach (explode(' ', $op) as $oop) {
                $this->new_Op($oop, $prio, $array, $phpeq, $types);
            }
            return $this;
        }
        $ww = 'JUST_OP';
        if (preg_match('/^\w+$/', $op)) {
            $ww = 'WORD_OP';
        } else if (strlen($op) > 1) {
            $ww = 'MC_JUST_OP';
        }
        if ($prio < 10 || !isset($this->prio[$op])) $this->prio[$op] = $prio;
        if (is_string($phpeq)) {
            $array[$op] = $this->oper(str_replace('~~~', str_replace('%','%%',$op), $phpeq));
        } else {
            $array[$op] = $this->oper($phpeq);
        }
        $array[$op]->types = $types . '****';
        $op = preg_quote($op);
        if ($op != '' && $op{0} != '_') {
            if (!in_array($op, $this->cake[$ww]))
                $this->cake[$ww][] = $op;
        }
        return $this;
    }

    /**
     * определить функцию с параметрами
     * @param $op - имя операции
     * @param $phpeq - PHP эквивалент - паттерн для sprintf'а с одним параметром
     */
    function &newFunc($op, $phpeq = '(~~~(%s))', $types = '*')
    {
        $this->func[$op] = $this->oper(str_replace('~~~', $op, $phpeq));
        return $this;
        //return $this->new_Op($op,10,&$this->func,pps($phpeq,'~~~(%s)'),$types);
    }

    /**
     * определить унарную операциию
     * @param $op - имя операции
     * @param $phpeq - PHP эквивалент - паттерн для sprintf'а с одним параметром
     */
    function &newOp1($op, $phpeq = null, $types = '*')
    {
        return $this->new_Op($op, 10, $this->unop, pps($phpeq, '(~~~(%s))'), $types);
    }

    /**
     * определить унарную операциию - суффикс
     * @param $op - имя операции
     * @param $phpeq - PHP эквивалент - паттерн для spritf'а с одним параметром
     */
    function &newOpS($op, $phpeq = null, $prio = 10, $types = '*')
    {
        return $this->new_Op($op, $prio, $this->suffop, pps($phpeq, '(%s~~~)'), $types);
    }

    /**
     * определить операнд
     * @param $op - имя операнда
     * @param $phpeq - PHP эквивалент
     */
    function &newOpr($op, $phpeq = null, $type = self::TYPE_OPERAND)
    {
        if (strpos($op, ' ') !== false) {
            foreach (explode(' ', $op) as $oop)
                $this->newOpr($oop, $phpeq, $type);
            return $this;
        }
        if (is_object($phpeq))
            $this->ids[$op] = $phpeq;
        else
            $this->ids[$op] = $this->oper($phpeq, $type);
        return $this;
    }

    /**
     * определить бинарную операциию
     * @param $op - имя операции
     * @param $prio - приоритет операции
     * @param $phpeq - PHP эквивалент - паттерн для spritf'а с одним параметром
     */
    function &newOp2($op, $prio = 10, $phpeq = null, $types = '*')
    {
        return $this->new_Op($op, $prio, $this->binop, pps($phpeq, '((%s)~~~(%s))'), $types);
    }

    /**
     * Выдать регулярку, нарезающую текст на мелкое количество лексем
     * пример для простого формульного калькулятора
     * + потенция на парсер SQL конструкций
     * @param array $types установка массива типов лексем
     *     для автоматического парсинга
     */
    protected function get_reg(&$types)
    {
        /**
         * массив типов, определенных регуляркой. Повязан на суб-номер в регулярке
         */
        $types = array(0 //0 - just skip
        , 0, self::TYPE_STRING
        , self::TYPE_DIGIT
        , self::TYPE_OPERATION //5
        , self::TYPE_ID //6  TYPE_OPERAND
        , self::TYPE_OPERATION //7
        , self::TYPE_COMMA //8
        );
        return '#[\n\r\s]*' // # - пропуск пробелов
            . '(?:' #1#2 - заквоченные без слеша на конце - TYPE_OPERAND
            . '([\'`"])((?:[^\\1\\\\]|\\\\.)*?)\\1'
            . '|(\d\w*)' //	#6 - цифры это слова, начинающиеся с цифры - TYPE_DIGITS
            . '|' #4,5 - многобуквенные операции - TYPE_OPERATION
            . '((?:' . implode('|', $this->cake['WORD_OP']) . ')(?=\b)|' . implode('|', $this->cake['MC_JUST_OP']) . ')'
            . '|(\w+)' #6 - просто слова TYPE_OPERAND
            . '|' #7 - однобуквенные операции - TYPE_OPERATION
            . '([' . implode('', $this->cake['JUST_OP']) . '\!])'
            . '|(.)' #8 - однобуквенные знаки препинания - TYPE_COMMA
            . ')#si';

    }

    /**
     * сканирование скрипта для определения начал строк
     * @param string $script
     */
    protected function scanNl(&$script)
    {
        preg_match_all('/$/m', $script, $m, PREG_OFFSET_CAPTURE);
        foreach ($m[0] as $k => $v) {
            $this->lines[$v[1]] = $k;
        }
    }

    /**
     * первый проход компилятора - свертка лексем
     * @param $script
     */
    function makelex($script)
    {
        //$this->script=$script;
        $this->operand = array();
        $this->operation = array();
        $this->lex = array();
        $this->curlex = 0;
        $types = array();
        if (preg_match_all($this->get_reg($types), $script, $m)) {
            $curptr = 0;
            $vc = count($m[0]);
            for ($i = 0; $i < $vc; $i++) {
                if (empty($m[0][$i])) continue;
                $curptr += strlen($m[0][$i]);
                if (!empty($m[1][$i])) {
                    $op = $this->oper(stripslashes($m[2][$i]), self::TYPE_STRING, $curptr);
                    if ($m[1][$i] == "'")
                        $op->type = self::TYPE_STRING1;
                    elseif ($m[1][$i] == "`")
                        $op->type = self::TYPE_STRING2;
                } else {
                    for ($x = count($types) - 1; $x > 2; $x--) {
                        if (isset($m[$x][$i]) && $m[$x][$i] != "") {
                            $op = $this->oper(strtolower($m[$x][$i]), $types[$x], $curptr);
                            break;
                        }
                    }
                    //TODO: если не захватили никого - это пробельные символы... мистика - wtf?
                    if ($x == 2) continue;
                }
                $this->lex[] = $op;
            }
            $this->lex[] = $this->oper("\x1b", self::TYPE_EOF, $curptr);
        }
    }

    /**
     * сгенерировать сообщение об ошибке и снабдить его координатами
     * @param $msgId
     * @param $lex
     */
    public function error($msgId, $lex = null)
    {
        $mess = pps($this->error_msg[$msgId], $msgId);
        if (is_null($lex)) {
            $lex = $this->op;
        }
        if (!is_null($lex)) {
            $mess .= sprintf(' pos:%s lex:"%s"', pps($lex->pos, -1), pps($lex->val, -1));
        }
        throw new CompilationException($mess);
    }

    /**
     * назад, повторить ввод лексемы
     */
    protected function back()
    {
        if ($this->curlex > 0) $this->curlex--;
        $this->op =& $this->lex[$this->curlex];
    }

    /**
     * Дай следующую лексему
     */
    function getNext()
    {
        if ($this->curlex >= count($this->lex))
            $this->error('toofar');
        $this->op =& $this->lex[$this->curlex++];
        return $this->op->type;
    }

    /**
     *  поддержка трансляции вычисляемых выражений с операциями и скобками
     *  Expr === {Operand} [{Operation} {Expr}]*
     */
    function getExpression()
    {
        $this->newop('(');
        $place = 1;
        do {
            if ($place == 1) {
                switch ($this->getNext()) {
                    case self::TYPE_DIGIT:
                    case self::TYPE_OPERAND:
                        $this->pushOp($this->op);
                        $place = 2;
                        break;
                    case self::TYPE_STRING:
                    case self::TYPE_STRING1:
                        $this->pushOp($this->op->val, self::TYPE_STRING);
                        $place = 2;
                        break;
                    case self::TYPE_STRING2:
                    case self::TYPE_ID:
                        if (isset($this->ids[$this->op->val])) {
                            $x = $this->ids[$this->op->val];
                            if (is_string($x->val)) {
                                $op = $this->pushOp($x->val, $x->type);
                            } else {
                                $par = array(&$this->op, &$this);
                                if ($op = call_user_func_array($x->val, $par)) {
                                    $this->pushOp($op);
                                }
                            }
                        } elseif (method_exists($this, 'resolve_id')) {
                            $op = $this->resolve_id($this->op);
                        } else {
                            $this->error('unknown id');
                        }
                        $place = 2;
                        break;
                    case self::TYPE_OPERATION: // функция с параметрами в скобках
                        $op = $this->op;
                        $this->getNext();
                        if ($this->op->val == '(') {
                            //$this->get_Comma_separated_list();
                            $xop = $this->pushOp('[]', self::TYPE_LIST);
                            $xop->value = $this->get_Parameters_list();
                            $this->getNext();
                            if ($this->op->val != ')')
                                $this->error('closed brackets missed'); // гарантированно - ОПЕРАНД
                            $this->execute($op);

                        } else { // унарная операция
                            $this->back();
                            $this->calc($op, true);
                            continue 2;
                        }
                        $place = 2;
                        break;
                    case self::TYPE_COMMA:
                        if ($this->op->val == '(') { // это - начало скобок
                            $this->getExpression();
                            $this->getNext();
                            if ($this->op->val != ')') $this->error('closed brackets missed');
                        } elseif ($this->op->val == '[') { // это - начало скобок
                            // выбираем список
                            $xop = $this->pushOp('[]', self::TYPE_LIST);
                            $xop->value = $this->get_Parameters_list();
                            //$num=$this->get_Comma_separated_list();
                            $this->getNext();
                            if ($this->op->val != ']')
                                $this->error('missed ]');
                        } elseif ($this->op->val == '{') { // это - начало скобок
                            // выбираем список
                            $xop = $this->pushOp('[]', self::TYPE_LIST);
                            $xop->value = $this->get_Parameters_list();
                            //$num=$this->get_Comma_separated_list();
                            $this->getNext();
                            if ($this->op->val != '}')
                                $this->error('missed }');
                        } else {
                            $this->back();
                            break 2;
                        }
                        $place = 2;
                        break;
                    default:
                        $this->error('wtf');
                }

            } else if ($place == 2) {
                switch ($this->getNext()) {
                    case self::TYPE_OPERATION:
                        if (isset($this->suffop[$this->op->val])) {
                            $this->calc($this->op, 'suff'); // значение уже на стеке - работаем!
                            continue;
                        } else {
                            $this->calc($this->op);
                            $place = 1;
                            break;
                        }
                    case self::TYPE_COMMA:
                        if ($this->op->val == '(') {
                            $this->newop('_stack_');
                            $arr = $this->get_Parameters_list('=');
                            $this->getNext();
                            if ($this->op->val != ')')
                                $this->error('missed )');
                            $op = $this->pushOp('[]', self::TYPE_LIST);
                            $op->value = $arr;
                            break;
                        } elseif ($this->op->val == '[') { // получение атрибута
                            $xop = $this->pushOp('[]', self::TYPE_LIST);
                            $xop->value = $this->get_Parameters_list();
                            //	$num=$this->get_Comma_separated_list();
                            $this->getNext();
                            if ($this->op->val != ']')
                                $this->error('missed ]');
                            $this->newop('_scratch_');
                            continue 2;
                        } elseif ($this->op->val == '?') { // short if form  . Condition already catched
                            $this->getExpression(); //then
                            $this->getNext();
                            if ($this->op->val != ':') {
                                $this->error('Short condition without ":" sign');
                            }
                            $this->getExpression(); //else
                            $op = $this->operat('_iif_');
                            $op->type = self::TYPE_OBJECT;
                            $op->handler = array($this, 'function_iif');
                            $this->execute($op);
                            continue 2;
                        }
                        ;
                    default:
                        $this->back();
                        break 2;
                }
            }
        } while (true);
        $this->newop(')');
    }

    protected function newop($f)
    {
        if ($f == '(')
            array_push($this->operation, $this->operat($f, $this->prio['(']));
        else {
            $this->calc($this->operat($f, isset($this->prio[$f]) ? $this->prio[$f] : 10));
        }
    }

    /*	protected function get_Comma_separated_list(){
         $depth=count($this->operand);
         do {
             $this->getExpression();
             $this->getNext();
             if($this->op->val==',') {
                 //$this->newop(',');
             } else {
                 $this->back();
                 break;
             }
         } while(true);
         return count($this->operand)-$depth;
     }*/
    /**
     * список параметров через запятую, с именами, разделенных = или :
     * Возвращает асссоциативный массив
     */
    protected function get_Parameters_list($sign = ':')
    {
        $depth = count($this->operand);
        $arr = array();
        $keys = array();
        if (!empty($this->storeparams)) {
            $keys[] = $this->popOp();
            $arr[] = null;
            $this->storeparams = false;
        }
        do {
            //$type = $this->getNext();
            $opdepth = count($this->operand);
            $type = $this->getNext();
            if ($type == self::TYPE_ID) {
                $id = $this->op;
                $subtype = $this->getNext();
                $this->back();
                if ($this->op->val == $sign) {
                    $this->pushOp($id); //($this->oper($id->val,self::TYPE_STRING));
                } else if ($this->op->val == ',' && $sign == '=') { // wft?
                    $this->pushOp($id); //($this->oper($id->val,self::TYPE_STRING));
                } else {
                    $this->back();
                    $this->getExpression();
                }
            } else {
                $this->back();
                $this->getExpression();
            }


            if ($this->op->val == $sign) {
                //слопали ключ!
                $this->getNext();
                $this->getExpression();
                if ($opdepth + 2 != count($this->operand))
                    $this->error(' range operator not allowed with key:value pair');
                $keys[] = $this->popOp();
                $arr[] = $this->popOp();
                ;
            } else if ($opdepth < count($this->operand)) {
                while ($opdepth < count($this->operand)) {
                    $keys[] = $this->popOp();
                    $arr[] = null;
                }
            }
            if ($this->op->val != ',')
                break;
            $this->getNext();

        } while (true);
        return array('value' => $arr, 'keys' => $keys);
    }

    /**
     * второй проход компилятора - сборка на стеке операндов готовой конструкции
     * + лексический анализ
     */
    function mathcalc()
    {
        $this->getExpression();
        if (count($this->operation) > 1) {
            $op = array_pop($this->operation);
            $this->error('error happen', $op);
        }
        $res = $this->popOp();
        if (!empty($this->operand)) {
            $this->error('something wrong');
        }
        return $res->val;
    }

    /**
     * свертка(выполнение) приоритетных операций, выкладывание на стек новой операции
     * @param $op
     * @param $unop
     */
    protected function execute($last)
    {
        //echo 'exec "'.$last[0].'" ';
        if (empty($last->unop))
            $last->unop = !isset($this->binop[$last->val]);
        if ($last->val == '(') {
            $this->error('open brackes found', $last);
        } else if ($last->type == self::TYPE_OBJECT) {
            $op = call_user_func($last->handler, $last, $last->param, 'call');
            if ($op)
                $this->pushOp($op);
        } else if (!$last->unop && isset($this->binop[$last->val])) {
            if (is_string($this->binop[$last->val]->val)) {
                $op1 = $this->popOp();
                $op2 = $this->popOp();
                $opr = $this->binop[$last->val];
                $this->pushOp(sprintf($this->binop[$last->val]->val
                    , $this->to(array($opr->types{2}, 'value'), $op2)
                    , $this->to(array($opr->types{1}, 'value'), $op1)
                ), $this->t_conv[$opr->types{0}]);
            } elseif (is_callable($this->binop[$last->val]->val)) {
                $op2 = $this->popOp();
                $op1 = $this->popOp();
                $op = call_user_func($this->binop[$last->val]->val, $op1, $op2);
                if ($op)
                    $this->pushOp($op);
            } else $this->error('x3');
        } else if ($last->unop === 'suff' && isset($this->suffop[$last->val])) {
            if (is_string($this->suffop[$last->val]->val)) {
                $op1 = $this->popOp();
                $opr = $this->suffop[$last->val];
                $op1 = $this->to($opr->types{1}, $op1);
                $this->pushOp(sprintf($this->suffop[$last->val]->val
                    , $op1->val), $this->t_conv[$opr->types{0}]);
            } else {
                $op = call_user_func($this->suffop[$last->val]->val, $this->popOp());
                if ($op)
                    $this->pushOp($op);
            }
        } else if ($last->unop && isset($this->unop[$last->val])) {
            if (is_string($this->unop[$last->val]->val)) {
                $op = $this->popOp();
                $opr = $this->unop[$last->val];
                $this->pushOp(sprintf($this->unop[$last->val]->val
                    , $this->to(array($opr->types{1}, 'value'), $op)), $opr->types{0});
            } else {
                $op = call_user_func($this->unop[$last->val]->val, $this->popOp());
                if ($op)
                    $this->pushOp($op);
            }
        } else {
            $this->error('undefined operation', $last);
        }
    }

    /**
     * заглушка конвертера в "изображение type", для безопасной вставки в строку кода
     * @param $type
     * @param $res
     *
     * to(self::TYPE_ID - отконвертировать в "изображение переменной" для приписывания
     *         суффиксного оператора, в частности
     */
    function to($type, &$res)
    {
        if (!is_array($type)) $type = array($type);
        foreach ($type as $t) {
            switch ($t) {
                case 'value':
                    return $res->val;
                case '*':
                case 'S':
                case 'D':
                case self::TYPE_OPERAND:
                case self::TYPE_STRING:

                case 'L':
                case self::TYPE_XLIST:
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
                        $res->type = self::TYPE_XLIST;
                    }
                    break;
            }
        }
        return $res;
    }

    protected function calc($op, $unop = false)
    {
        $prio = $unop ? 10 : 0;
        $prio = max($prio, isset($this->prio[$op->val]) ? $this->prio[$op->val] : 10);
        while (count($this->operation) > 0) {
            $last =& $this->operation[count($this->operation) - 1];
            if ($last->prio > $prio || ($last->prio == $prio && !$last->unop)) {
                $this->execute(array_pop($this->operation));
            } elseif (!empty($this->storeparams) && $op->val != '_stack_') {
                // забыли вызвать одноместный фильтр
                $this->storeparams = false;
                $this->execute($this->operat('_stack_'));
            } else {
                break;
            }
        }
        ;
        if ($op->val != ')') {
            $op->prio = $prio;
            $op->unop = $unop;
            array_push($this->operation, $op);
        } else {
            $op = array_pop($this->operation);
            //TODO: проверить, что операнд - скобка
        }
    }
}