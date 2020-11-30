<?php

namespace Ksnk\scaner;

/**
 * простой сканер разнобольших текстовых файлов, можно в гнузипе
 * Используется как родитель для транспортных классов - spider/mailer
 * так и для сольного использования - анализ логов.
 *
 * Читает файл в буфер. Анализ файла идет регулярками. когда "курсор" чтения
 * доходит до граница прочитанного буфера - файл дочитывается, буфер смещается.
 * Базовый сервис сканирования и парсинга с помощью регулярок и рудиментарно-простой синтаксический анализ
 *
 * Class scaner
 */
class scaner
{
    use traitHandledClass;
    /**
     * Читаем и дочитываем из файла вот такими кусками
     */
    const BUFSIZE = 40000; // максимальный размер буфера чтения

    /**
     * Гарантируем такое пространство от курсора чтения до конца буфера.
     * Фактически - ограничение сверху на длину строки
     */
    const GUARD_STRLEN = 20000;

    /**
     */
    const NL = "\n"; // символ новой сроки


    /**
     * Буфер чтения
     * @var string
     */
    var $buf;

    /** @var int - позиция начала совпадения регулярки для функции scan */
    public $reg_begin;

    /** @var string - служебная, хвост от предыдущей операции */
    private $tail = '';

    /** @var boolean - признак успешности только что вызванной функции scan */
    var $found = false,

        /** @var int - позиция начала буфера чтения в файле */
        $filestart = 0,

        /** @var int - позиция курсора чтения в буфере */
        $start;

    /** @var integer */
    private
        $result,

        $till = -1;

    var $finish = 0,

        /** @var array - массив нумерации строк */
        $lines = array(),
        /** @var int $lastline - дочитали от начала файла до такой строки */
        $lastline;

    /**
     * Выдать результат работы функций сканирования.
     * При этом чистится сохраненный результат
     * @return array|int
     */
    function getresult()
    {
        if (empty($this->result)) {
            $x = array();
        } else {
            $x = $this->result;
        }
        $this->result = array();
        return $x;
    }

    /**
     * Поместить строку в буфер анализа. Нефайловый сканер
     * @param $buf - Строка для анализа
     * @return $this
     */
    function newbuf($buf)
    {
        if (is_array($buf)) { // source from `file` function
            $buf = implode(self::NL, $buf);
        }
        $this->buf = $buf; // run the new scan
        $this->finish = mb_strlen($buf, '8bit');
        $this->reset();
        return $this;
    }

    /**
     * Сброс сканера для обработки следующего буфера
     */
    function reset()
    {
        $this->start = 0;
        $this->lastline = 0;
        $this->till = -1;
        $this->result = array();
        $this->filestart = 0;
        return $this;
    }

    function close()
    {
        if (!empty($this->handle)) {
            fclose($this->handle);
            $this->handle = false;
        }
    }

    /**
     * чистим хвосты
     */
    function __destruct()
    {
        $this->close();
    }

    /**
     * Файл для анализа. Можно gz.
     * @param $handle
     * @return $this
     */
    function newhandle($handle)
    {

        $this->close();
        if (is_string($handle)) {
            if (preg_match('/\.gz$/', $handle)) {
                $_handle = fopen($handle, "rb");
                fseek($_handle, filesize($handle) - 4);
                $x = unpack("L", fread($_handle, 4));
                $this->finish = $x[1];
                fclose($_handle);
                $handle = gzopen(
                    $handle, 'r'
                );
            } else {
                $this->finish = filesize($handle);
                $handle = fopen($handle, 'r+');
            }
        } else {
            //$this->finish = filesize($handle);
        }

        $this->handle = $handle; // sign a new scan
        $this->buf = '';
        $this->lines = [];
        $this->reset();
        return $this;
    }

    /**
     * заполняем массив начал строк. Заполняем по мере дочтения файла.
     */
    function refillNL()
    {
        preg_match_all('/$/m', $this->buf, $m, PREG_OFFSET_CAPTURE);
        if (isset($this->lines[$this->filestart + $m[0][0][1]])) {
            $this->lastline = $this->lines[$this->filestart + $m[0][0][1]];
            if (count($this->lines) > 10000) {
                foreach ($this->lines as $k => $v) {
                    if ($v < $this->lastline) unset($this->lines[$k]);
                }
            }
        }
        foreach ($m[0] as $k => $v) {
            $this->lines[$this->filestart + $v[1]] = ++$this->lastline;
        }
    }

    /**
     * дочитываем буфер, если надо
     * @param bool $force - проверять граничный размер
     * @return bool===false - файл кончился
     */
    protected function prepare($force = true)
    {
        // если в буфере остается ДОСТАТОЧНОЕ количество символов - ничего не делаем
        if (!$force && mb_strlen($this->buf, '8bit') - self::GUARD_STRLEN >= $this->start)
            return 0;

        if (!empty($this->handle)) {
            if (!feof($this->handle)) {
                if ($this->start >= mb_strlen($this->buf, '8bit')) {
                    $this->buf = $this->tail;
                } else
                    $this->buf = mb_substr($this->buf, $this->start, null, '8bit') . $this->tail;
                $this->buf .= fread($this->handle, self::BUFSIZE);
                $this->tail = '';
                if (!feof($this->handle)) {
                    // откусываем последнюю, возможно незавершенную строку буфера, если строка не очень большая
                    $x = mb_strrpos($this->buf, self::NL, 0, '8bit');
                    if (false !== $x && self::GUARD_STRLEN > (mb_strlen($this->buf, '8bit') - $x)) {
                        $this->tail = mb_substr($this->buf, $x + 1, null, '8bit');
                        $this->buf = mb_substr($this->buf, 0, $x + 1, '8bit');
                    }
                } else {
                    $this->finish = $this->filestart + $this->start + mb_strlen($this->buf, '8bit');
                }

                $this->filestart += $this->start;
                $this->refillNL();
                $this->start = 0;
                return true;
            }
        }
        return false;
    }

    /**
     * Построчное чтение файла
     */
    function line()
    {
        $this->found = true;
        $move = false;
        $this->prepare(false);
        if (mb_strlen($this->buf, '8bit') <= $this->start && !$move) {
            $this->found = false;
            return $this;
        }

        $x = mb_strpos($this->buf, self::NL, $this->start, '8bit');

        if (false === $x) {
            $this->result[] = mb_substr($this->buf, $this->start, null, '8bit');
            $this->start = mb_strlen($this->buf, '8bit');
        } else {
            if ($this->till <= 0 || $this->finish < $this->till)
                $till = $this->finish;
            else
                $till = $this->till;
            if ($this->filestart + $x + 1 > $till) {
                $this->found = false;
            } else {
                $this->result[] = mb_substr($this->buf, $this->start, $x - $this->start, '8bit');
                $this->start = $x + 1;
            }
        }
        return $this;
    }

    /**
     * Позиция курсора в файле
     * @return int
     */
    function getpos()
    {
        return $this->filestart + $this->start;
    }

    /**
     * установить курсор чтения в позицию $pos
     * @param $pos
     * @return $this
     */
    function position($pos)
    {
        if($pos==='till'){
            if($this->till>=0) {
                return $this->position($this->till);
            }
        }
        if (!empty($this->handle)) {
            if ($this->filestart <= $pos && (mb_strlen($this->buf, '8bit') + $this->filestart) > $pos) {
                $this->start = $pos - $this->filestart;
            } else {
                fseek($this->handle, $pos);
                $this->filestart = $pos;
                $this->buf = '';
                $this->tail = '';
                $this->start = 0;
            }
        } else {
            $this->start = $pos;
        }
        return $this;
    }

    function regit($reg)
    {
        $this->prepare(false);

        do {
            $found = preg_match($reg, $this->buf, $m, PREG_OFFSET_CAPTURE, $this->start);
            if (!$found && !empty($this->handle) && !feof($this->handle)) {
                $x = mb_strrpos($this->buf, self::NL, 0, '8bit');
                if (false === $x) {
                    $this->start = mb_strlen($this->buf, '8bit');
                } else {
                    $this->start = $x;
                }
                if ($this->prepare()) {
                    continue;
                } else {
                    break;
                }
            } else {
                $this->start = $m[0][1] + mb_strlen($m[0][0], '8bit');
                break;
            }
        } while (true);
        if (!$found)
            return false;
        else
            return $m;
    }

    /**
     * scan buffer till pattern not found
     * @param $reg
     * @return $this
     */
    function scan($reg)
    {
        $this->prepare(false);

        do {
            $this->found = false;

            if ($reg{0} == '/' || $reg{0} == '~') { // so it's a regular expresion
                $res = preg_match($reg, $this->buf, $m, PREG_OFFSET_CAPTURE, $this->start);
                if ($res) {
                    if ($this->till <= 0 || $this->finish < $this->till)
                        $till = $this->finish;
                    else
                        $till = $this->till;
                    $plen = isset($m['fin']) ? $m['fin'][1] : $m[0][1] + mb_strlen($m[0][0], '8bit');
                    if ($this->filestart + $plen > $till) {
                        $this->found = false;
                        break;
                    } else {
                        $this->reg_begin = $this->filestart + $m[0][1];
                        $this->found = true;
                        $this->start = $plen;
                        $args = func_get_args();
                        array_shift($args);
                        while (count($args) > 0) {
                            $x = array_shift($args);
                            $name = '';
                            if (count($args) > 0) $name = array_shift($args);
                            if (isset($m[$x])) {
                                if (empty($name))
                                    $this->result[] = $m[$x][0];
                                else
                                    $this->result[$name] = $m[$x][0];
                            }
                        }
                    }
                }
            } else { // it's a plain text
                $y = mb_stripos($this->buf, trim($reg), $this->start, '8bit');
                if (false !== $y) {
                    if ($this->till > 0 && $this->filestart + $y + mb_strlen($reg, '8bit') > $this->till) {
                        $this->position($this->till);
                        break;
                    }
                    $this->found = true;
                    $x = mb_strpos($this->buf, self::NL, $y + mb_strlen($reg, '8bit'), '8bit');
                    if (false === $x)
                        $this->start = mb_strlen($this->buf, '8bit');
                    else
                        $this->start = $x - 1;
                    $xx = mb_strrpos($this->buf, self::NL, $y - mb_strlen($this->buf, '8bit'), '8bit');
                    // echo $xx,' ',$y,' ',$this->start,self::NL;
                    $this->result[] = mb_substr($this->buf, $xx, $this->start - $xx, '8bit');
                }
            }
            if (!$this->found && !empty($this->handle) && !feof($this->handle)) { //3940043
                // $this->start=mb_strlen($this->buf,'8bit');
                $x = mb_strrpos($this->buf, self::NL, 0, '8bit');
                if (false === $x) {
                    $this->start = mb_strlen($this->buf, '8bit');
                } else {
                    $this->start = $x+1;
                }
                if ($this->prepare()) {
                    continue;
                } else {
                    break;
                }
            } else
                break;
        } while (true);
        return $this;
    }

    /**
     * в случае неудачи возвращает указатель на начало
     * @return $this
     */
    function ifscan()
    {
        $pos = $this->getpos();
        $arg = func_get_args();
        call_user_func_array(array($this, 'scan'), $arg);
        if (!$this->found) {
            $this->position($pos);
        }
        return $this;
    }

    /**
     * Получить строку, вокруг позиции Start
     */
    function getline()
    {
        if ($this->start == 0) $x = 0;
        else $x = mb_strrpos($this->buf, self::NL, $this->start - mb_strlen($this->buf, '8bit'), '8bit');
        $y = mb_strpos($this->buf, self::NL, $this->start, '8bit');
        if (false === $x) $x = 0; else $x++;
        if (false === $y) return mb_substr($this->buf, $x, null, '8bit');
        return mb_substr($this->buf, $x, $y - $x, '8bit');
    }

    /**
     * Установить нижнюю границу для выполнения doscan
     * @param $reg
     * @return $this
     */
    function until($reg = '')
    {
        if (empty($reg)) {
            $this->till = -1;
            return $this;
        }
        $oldstart = $this->filestart + $this->start;
        $res = $this->result;
        $f = $this->found;

        $this->scan($reg);

        if ($this->found) {
            $this->till = $this->reg_begin;//'$this->filestart+$this->start;
        } else {
            $this->till = -1;
        }
        $this->position($oldstart);
        $this->found = $f;
        $this->result = $res;
        return $this;
    }

    /**
     * Циклический поиск в буфере
     * @param $reg
     * @return $this
     */
    function doscan($reg)
    {
        $arg = func_get_args();
        $old = $this->getResult();
        $r = array();
        do {
            call_user_func_array(array($this, 'scan'), $arg);
            if ($this->found)
                $r[] = $this->getResult();
        } while ($this->found);
        $this->result = $old;
        $this->result['doscan'] = $r;
        $this->till = -1;
        return $this;
    }

    function getbuf()
    {
        return $this->buf;
    }

  /**
   * syntax parsing
   * @param $tokens
   * @param $pattern
   * @param $callback
   * @param bool $movepostotill - переместить указатель на финальную границу области
   * @return scaner
   */
    function syntax($tokens, $pattern, $callback, $movepostotill=true)
    {
        // so build a reg
        $idx = array('_skiped');
        while (preg_match('/:(\w+):/', $pattern, $m, PREG_OFFSET_CAPTURE)) {
            if (!isset($tokens[$m[1][0]])) break;
            $idx[] = $m[1][0];
            if (is_string($tokens[$m[1][0]])) $tokens[$m[1][0]] = array($tokens[$m[1][0]]);
            $pattern =
                mb_substr($pattern, 0, $m[0][1], '8bit') .
                '(' . implode('|', $tokens[$m[1][0]]) . ')' .
                mb_substr($pattern, $m[0][1] + mb_strlen($m[0][0], '8bit'), null, '8bit');
        }
        if ($this->till < 0) {
            $till = $this->finish;
        } else {
            $till = $this->till;
        }
        $this->prepare(false);
        while (true) {
            $skiped = '';
            while ($found = preg_match($pattern, $this->buf, $m, PREG_OFFSET_CAPTURE, $this->start)) {
                $skiped = mb_substr($this->buf, $this->start, $m[0][1] - $this->start, '8bit');
                if (isset($m['fin'])) {
                    $this->start = $m['fin'][1];
                } else {
                    $this->start = $m[0][1] + mb_strlen($m[0][0], '8bit');
                }
                if ($this->filestart + $this->start > $till) {
                    $this->start = $m[0][1]; // не терять тег на границе буфера todo: oppa! строка то фиксированной длины?
                    if($this->filestart + $this->start >= $till)
                        $skiped='';
                    else
                        $skiped = mb_substr($this->buf, $this->start,
                        $till-$this->filestart,
                        '8bit');
                    break;
                }
                $r = array('_skiped' => $skiped);
                $skiped = '';
                //array_shift($m);
                foreach ($idx as $i => $v) {
                    if (isset($m[$i]) && !empty($i)) {
                        $r[$idx[$i]] = trim($m[$i][0], "\n\r ");
                    }
                }
                if (false === $callback($r)) break 2;
            }
            if ('' != $skiped) {
                if (false === $callback(array('_skiped' => $skiped))) break;
            } else if (!$found) {
                if($this->filestart + $this->start >= $till)
                    $skiped='';
                else
                    $skiped = mb_substr($this->buf, $this->start,
                        $till-$this->filestart-$this->start,
                        '8bit');
               //$skiped = mb_substr($this->buf, $this->start, null, '8bit');
                $this->start = mb_strlen($this->buf, '8bit');
                if (''===$skiped || false === $callback(array('_skiped' => $skiped))) break;
            }
            if (!$this->prepare(false)) break;
        }
        if($movepostotill)
          $this->position($till);
        return $this;
    }

}
