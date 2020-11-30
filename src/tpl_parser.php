<?php
/**
 * Jinja language parcer
 * <%=point('hat','jscomment');




%>
 */

namespace Ksnk\templater;

use Ksnk\scaner\scaner;
use Ksnk\templates\base as tpl_base;

class tpl_parser
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

    // дополнительные константы типов
    const
        TYPE_SENTENSE = 101,
        TYPE_LITERAL = 102;
    protected

        $locals = array(), // стек идентификаторов с областью видимости
        $ids_low = 0, // нижняя граница области видимости
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

    public
        $currentFunction = '', // имя текущей функции block или macro
        //для корректной работы parent()
        $opensentence = array(), // комплект открытых тегов, для портирования
        /** @var string - скрипт для выполнения */
        $script,
        /** @var boolean - сохранять-несохранять */
        $storeparams,

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
        $op = null,

        $namespace = '',
        $basenamespace = '',
        /**
         * @var scaner
         */
        $scaner = null;

    function reset()
    {
        $this->locals = array(); // стек идентификаторов с областью видимости
        $this->ids_low = 0; // нижняя граница области видимости
        $this->curlex = 0;
        $this->opensentence = [];
        $this->operand = [];
        $this->op = [];
        $this->lex = [];
    }

    function __construct()
    {
        $this->options = array_merge($this->options, array(
            // Enviroment setting
            'BLOCK_START' => '{%',
            'BLOCK_END' => '%}',
            'VARIABLE_START' => '{{',
            'VARIABLE_END' => '}}',
            'COMMENT_START' => '{#',
            'COMMENT_END' => '#}',
            'COMMENT_LINE' => '##',
            'trim' => true,
//            'COMPRESS_START_BLOCK' => true
        ));
        $this
            ->newOp2('_scratch_', 11, array($this, 'function_scratch'))
            ->newOp2('_stack_', 10, array($this, 'function_callstack'));
        $this->t_conv['E'] = self::TYPE_SENTENSE;
        $this->error_msg['unexpected construction'] = 'something strange catched.';

    }

    /**
     * Вызов функции op1 с параметрами op2
     * @param operand $op1
     * @param operand $op2
     * @return operand
     * @throws CompilationException
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
                if ($op1->type == self::TYPE_SLICE) {
                    // вызов объектного макроса
                    $this->to('I', $op1->list[0]);
                    //$op1->val =$op1->list[0]->val;
                    $op1->val = $this->template('callmacroex', array('par1' => $op1->list[0]->val, 'mm' => $op1->list[1]->val, 'param' => $arr));
                    $op1->type = self::TYPE_OPERAND;
                } else {
                    $op1->val = $this->template('callmacro', array('name' => $op1, 'param' => $arr, 'parkeys' => $arrkeys));
                    $op1->type = self::TYPE_SENTENSE;
                }
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
            if (!empty($tag_waitingfor) && $this->op->type != self::TYPE_STRING2 && in_array($this->op->val, $tag_waitingfor)) {
                $this->back();
                break;
            }
            if ($this->op->type == self::TYPE_COMMA && empty($this->op->val)) {

            } elseif ($this->op->type != self::TYPE_STRING2 && $this->exec_tag($this->op->val)) {
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
                if (!($op = $this->popOp())) break;
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
        $this->scaner = new scaner();
        if (strlen($script) < 60 && is_readable($script)) {
            $this->scaner->newhandle($script);
        } else {
            $this->scaner->newbuf($script);
        }
        // предварительное сканирование - режем исходник на началы тегов, до конца тегов
        $types = array();
        /**
         * на прошлом закрывающем теге был -
         */
        $triml = false;
        /**
         * массив индексов всех лексем, с начала bstart и до следующего.
         */
        $marklex = [-1];

        // забираем лексемную регулярку
        $reg = $this->get_reg($types);
        // добираемся уже внутри колбяка
        $this->scaner->syntax([
            'cline' => preg_quote($this->options['COMMENT_LINE']),
            'xstart' => preg_quote($this->options['VARIABLE_START']),
            'cstart' => preg_quote($this->options['COMMENT_START']),
            'bstart' => preg_quote($this->options['BLOCK_START']),
            'start_0' => ':cline:|:xstart:|:cstart:',
            'start_1' => ':start_0:|:bstart:',
            'trim' => preg_quote('-'),
        ], '~:start_1::trim:?~sm',
            function ($line) use ($reg, $types, &$marklex, &$triml) {
                // print_r($line);
                if (!empty($marklex) && preg_match('/\r?\n/s', $line['_skiped'])) {
                    // удаляем символ перевода строки из текущего скипа
                    $line['_skiped'] = preg_replace('/^[ \t]*\r?\n/', '', $line['_skiped']);
                    // удаляем все пробелы в лексемах
                    foreach ($marklex as $lid) {
                        if (isset($this->lex[$lid])) {
                            $this->lex[$lid]->val = preg_replace('/[ \t]*$/', '', $this->lex[$lid]->val);
                        }
                    }
                    $marklex = [];
                }
                if ($triml && !empty($line['_skiped'])) {
                    $line['_skiped'] = preg_replace('/^\s*/s', '', $line['_skiped']);
                    if ($line['_skiped'] != '') $triml = false;
                }
                if (!empty($line['trim'])) {
                    $line['_skiped'] = preg_replace('/(\s*\r?\n?|^)\s*$/', '', $line['_skiped']);
                    $line['trim'] = false;
                }

                if (!empty($line['_skiped'])) {
                    $pos = $this->scaner->getpos();
                    $this->lex[] = $this->oper('_echo_', self::TYPE_OPERATION, $pos);
                    if (!empty($line['bstart'])) {
                        $marklex[] = count($this->lex);
                    }
                    $this->lex[] = $this->oper($line['_skiped'], self::TYPE_STRING, $pos);
                    $this->lex[] = $this->oper('', self::TYPE_COMMA, $pos);
                    $line['_skiped'] = '';
                }
                if (!empty($line['cline'])) {
                    $this->scaner->scan('~(.*?)\r?\n~i');
                    return;
                } else if (!empty($line['cstart'])) {
                    $this->scaner->scan('~' . preg_quote($this->options['COMMENT_END'] . '~sm'));
                    return;
                }

                $triml = false;

// отрезаем следующую лексему шаблонизатора
                if (!empty($line['xstart']) || !empty($line['bstart'])) {
                    if (!empty($line['bstart'])) {
                        $marklex[] = -1;
                    } else {
                        $marklex = [];
                    }
                    $first = true;
                    while ($m = $this->scaner->regit($reg)) {
                        $pos = $this->scaner->filestart + $m[0][1];
                        if (!empty($m[1][0])) {
                            $op = $this->oper(stripslashes($m[2][0]), self::TYPE_STRING, $pos);
                            if ($m[1][0] == "'")
                                $op->type = self::TYPE_STRING1;
                            elseif ($m[1][0] == "`")
                                $op->type = self::TYPE_STRING2;

                            $this->lex[] = $op;
                        } else {
                            for ($x = count($types) - 1; $x > 2; $x--) {
                                if (isset($m[$x]) && $m[$x][0] != "") {
                                    if ($types[$x] == self::TYPE_COMMA && strlen($m[$x][0]) > 1) {
                                        if ($m[$x][0]{0} == '-') {
                                            $triml = true;
                                            $m[$x] = substr($m[$x][0], 1);
                                        }
                                        $this->lex[] = $this->oper('', self::TYPE_COMMA, $pos);
                                        break 2;
                                    }
                                    $op = $this->oper(strtolower($m[$x][0]), $types[$x], $pos);
                                    $op->orig = $m[$x][0];
                                    $this->lex[] = $op;

                                    // разбираемся с тегом RAW
                                    if ($first && $m[$x][0] == 'raw') {
                                        if (!$x = $this->scaner->regit('~.*?'
                                            . preg_quote($this->options['BLOCK_END'], '#~')
                                            . '(.*?)'
                                            . preg_quote($this->options['BLOCK_START'], '#~')
                                            . '\s*endraw\s*'
                                            . preg_quote($this->options['BLOCK_END'], '#~')
                                            . '~si'
                                        ))
                                            $this->error('endraw missed');

                                        array_pop($this->lex);
                                        array_pop($this->lex);
                                        $this->lex[] = $this->oper($x[1][0], self::TYPE_STRING, $pos);
                                        break 2;
                                    } else
                                        break;
                                }
                            }
                        }
                        $first = false;
                    }
                }
            }
        );
        if (!empty($marklex)) {
            foreach ($marklex as $lid) {
                if (isset($this->lex[$lid])) {
                    $this->lex[$lid]->val = preg_replace('/[ \t]*$/', '', $this->lex[$lid]->val);
                }
            }
        }

        $this->lex[] = $this->oper("\x1b", self::TYPE_EOF, $this->scaner->getpos());
    }

    function newId($op)
    {
        // установить ID как новый идентификатор
        if (is_string($op)) {
            $this->locals[] = $op;
        } else {
            $this->locals[] = $op->val;
        }
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
        return $this->function_scratch($op1, $op2);
    }

    /**
     * разрешить неизвестный ID.
     * @param operand $op
     * @return mixed|operand
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
        $this->currentFunction = $tag['name'];
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
        $this->currentFunction = $tag['name'];
        $this->opensentence[] = &$tag;
        $this->getNext();
        $this->block_internal(array('endblock'), $tag);
        $this->getNext();
        array_pop($this->opensentence);
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
        $t['import'][] = basename($op->val, '.' . template_compiler::options('TEMPLATE_EXTENSION', 'jtpl'));
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
    function tplcalc($class = 'compiler', $tpl_class = 'compiler')
    {
        $tag = array('tag' => 'class', 'import' => array(), 'macro' => array(), 'name' => $class, 'data' => array());
        if ($this->namespace) {
            $tag['namespace'] = $this->namespace;
            if ($this->basenamespace) {
                $tag['basenamespace'] = $this->basenamespace;
            }
        }

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
        return $this->template('class', $tag, $tpl_class);
    }

    /**
     * Встроенные в класс рендерер ...
     * @param string $idx - имя подшаблона "" - корневой подшаблон
     * @param array $par - данные для рендеринга
     * @param string $tpl_class - имя базового шаблона
     * @return mixed|string
     */
    function template($idx = null, $par = null, $tpl_class = '')
    {
        static $tpl_compiler;
        if (!empty($tpl_class) || empty($tpl_compiler)) {
            if ($this->basenamespace)
                $tpl_compiler = '\\' . $this->basenamespace . '\\' . \Ksnk\templates\base::pps($tpl_class, 'compiler');
            elseif ($this->namespace)
                $tpl_compiler = '\\' . $this->namespace . '\\' . \Ksnk\templates\base::pps($tpl_class, 'compiler');
            else
                $tpl_compiler = 'tpl_' . tpl_base::pps($tpl_class, 'compiler');
            if (!class_exists($tpl_compiler)) {
                // попытка включить файл
                include_once(template_compiler::options('templates_dir') . $tpl_compiler . '.php');
            }
            $tpl_compiler = new $tpl_compiler();
        }

        if (!is_null($par)) {
            $x =& $par;
            if (method_exists($tpl_compiler, '_' . $idx))
                return @call_user_func(array($tpl_compiler, '_' . $idx), $x);
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
        } elseif (class_exists($cl = __NAMESPACE__ . '\\' . $name, true)) {
            $tag = new $cl();
            $tag->execute($this);
        } else if (method_exists($this, $name)) {
            call_user_func(array($this, $name));
        } else
            return false;

        return true;
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
    /*
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
*/

    /**
     * вырезка из массива
     * @param operand $op1
     * @param operand $op2
     * @return operand
     */
    function function_scratch($op1, $op2)
    {
        //вырезка из массива
        /*       $this->to('I', $op1);
               $this->to('L', $op2);
               $op1->val .= '[' . $op2->val . ']';
               $op1->type = self::TYPE_XID; */
        if ($op1->type == self::TYPE_SLICE) {
            $op1->list[] = $op2;
            return $op1;
        }
        if ($op2->type == self::TYPE_SLICE) {
            array_unshift($op2->list, $op1);
            return $op2;
        }
        $op = new operand('', self::TYPE_SLICE);
        $op->list = array($op1, $op2);
        return $op;
    }

    /**
     *
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
     * @param $value
     * @param int $type
     * @param int $pos
     * @return operand
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
     * @return operand
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
            $array[$op] = $this->oper(str_replace('~~~', str_replace('%', '%%', $op), $phpeq));
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
     * @return tpl_parser
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
     * @return tpl_parser
     */
    function &newOp1($op, $phpeq = null, $types = '*')
    {
        return $this->new_Op($op, 10, $this->unop, tpl_base::pps($phpeq, '(~~~(%s))'), $types);
    }

    /**
     * определить унарную операциию - суффикс
     * @param $op - имя операции
     * @param $phpeq - PHP эквивалент - паттерн для spritf'а с одним параметром
     * @param $prio - приоритет
     * @param $types - конверсия типов для операции
     * @return tpl_parser
     */
    function &newOpS($op, $phpeq = null, $prio = 10, $types = '*')
    {
        return $this->new_Op($op, $prio, $this->suffop, tpl_base::pps($phpeq, '(%s~~~)'), $types);
    }

    /**
     * определить операнд
     * @param $op - имя операнда
     * @param $phpeq - PHP эквивалент
     * @return tpl_parser
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
     * @return $this
     */
    function &newOp2($op, $prio = 10, $phpeq = null, $types = '*')
    {
        return $this->new_Op($op, $prio, $this->binop, tpl_base::pps($phpeq, '((%s)~~~(%s))'), $types);
    }

    /**
     * сгенерировать сообщение об ошибке и снабдить его координатами
     * @param $msgId
     * @param $lex
     * @throws CompilationException
     */
    public function error($msgId, $lex = null)
    {
        $mess = tpl_base::pps($this->error_msg[$msgId], $msgId);
        if (is_null($lex)) {
            $lex = $this->op;
        }
        if (!is_null($lex)) {
            $mess .= sprintf(' pos:%s lex:"%s"', tpl_base::pps($lex->pos, -1), tpl_base::pps($lex->val, -1));
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
                            /* } elseif(isset($this->binop[$op->val]))  { // бинарная операция
                                // $this->back();
                                 $this->pushOp($op);
                                 $place = 2;
                                 break;*/
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
                        } else {
                            $this->calc($this->op);
                            $place = 1;
                        }
                        break;
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
                        };
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

    /**
     * список параметров через запятую, с именами, разделенных = или :
     * Возвращает асссоциативный массив
     */
    protected function get_Parameters_list($sign = ':')
    {
        //$depth = count($this->operand); //todo - проверить таки глубину
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
                $arr[] = $this->popOp();;
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
     * @param $last
     * @throws CompilationException
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
                $opr = $this->unop[$last->val];
                $op = $this->popOp();
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
     * @return mixed
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
        };
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


