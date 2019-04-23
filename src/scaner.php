<?php
/**
 * простой сканер разнобольших текстовых файлов, можно в гнузипе
 * Используется как родитель для транспортных классов - spider/mailer
 * так и для сольного использования - анализ логов.
 *
 * Читает файл в буфер. Анализ файла идет регулярками. когда "курсор" чтения
 * доходит до граница прочитанного буфера - файл дочитывается, буфер смещается.
 * Базовый сервис сканироввания с помощью регулярок и рудиментарно-простой синтаксиеский анализ
 *
 * Class scaner
 */

namespace Ksnk\templater ;

class scaner
{

    /**
     * Читаем и дочитываем из файла вот такими кусками
     */
    const BUFSIZE = 40000; // максимальный размер буфера чтения

    /**
     * Гарантируем такое пространство от курсора чтения до конца буфера.
     * Фактически - ограничение сверху на длину строки
     */
    const GUARD_STRLEN = 12000;

    /**
     * Читаем и дочитываем из файла вот такими кусками
     */
    const NL = "\n"; // символ новой сроки

    /** @var string */
    protected $buf;

    /** @var int - позиция начала совпадения регулярки для функции scan */
    public $reg_begin;

    /** @var string */
    private $tail = '';

    /** @var boolean - признак успешности только что вызванной функции scan */
    var $found = false,

        $filestart = 0;

    /** @var integer */
    private
        $result,

        $till = -1,

        $start;

    var $finish = 0,

    /** @var array - массив нумерации строк */
        $lines=array();

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
     * Строка для анализа
     * @param $buf
     * @return $this
     */
    function newbuf($buf)
    {
        if (is_array($buf)) { // source from `file` function
            $buf = implode(self::NL, $buf);
        }
        $this->buf = $buf; // run the new scan
        $this->start = 0;
        $this->finish = strlen($buf);
        $this->till = -1;
        $this->result = array();
        $this->filestart = 0;
        return $this;
    }

    function __destruct()
    {
        if (!empty($this->handle)) {
            fclose($this->handle);
        }
    }

    /**
     * Файл для анализа. Можно gz.
     * @param $handle
     * @return $this
     */
    function newhandle($handle)
    {

        if (!empty($this->handle)) {
            fclose($this->handle);
        }
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
        }

        $this->handle = $handle; // run the new scan
        $this->start = 0;
        $this->till = -1;
        $this->filestart = 0;
        $this->buf = '';
        $this->result = array();
        return $this;
    }

    /**
     * заполняем массив начал строк
     */
    function refillNL(){
        static $lastline; if(!isset($lastline)) $lastline=0;
        preg_match_all('/$/m', $this->buf, $m, PREG_OFFSET_CAPTURE);
        if(isset($this->lines[$this->filestart+$m[0][0][1]])) {
            $lastline = $this->lines[$this->filestart+$m[0][0][1]];
        }
        foreach ($m[0] as $k => $v) {
            $this->lines[$this->filestart+$v[1]] = ++$lastline;
        }
    }

    /**
     * дочитываем буфер, если надо
     * @param bool $force - проверять граничный размер
     * @return bool - последний ли это препаре или нет
     */
    protected function prepare($force = true)
    {
        // если в буфере остается ДОСТАТОЧНОЕ количество символов - ничего не делаем
        if (!$force && strlen($this->buf) - self::GUARD_STRLEN >= $this->start)
            return false;

        if (!empty($this->handle)) {
            if (!feof($this->handle)) {
                if ($this->start >= strlen($this->buf)) {
                    $this->buf = $this->tail;
                } else
                    $this->buf = substr($this->buf, $this->start + 1) . $this->tail;
                $this->buf .= fread($this->handle, self::BUFSIZE);
                $this->tail = '';
                if (!feof($this->handle)) {
                    // откусываем последнюю, возможно незавершенную строку буфера, если строка не очень большая
                    $x = strrpos($this->buf, self::NL);
                    if (false !== $x && self::GUARD_STRLEN>(strlen($this->buf)-$x)) {
                        $this->tail = substr($this->buf, $x + 1);
                        $this->buf = substr($this->buf, 0, $x);
                    }
                }

                $this->filestart += ($this->start+1);
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
        if (strlen($this->buf) <= $this->start && !$move) {
            $this->found = false;
            return $this;
        }

        $x = strpos($this->buf, self::NL, $this->start);

        if (false === $x) {
            $this->result[] = substr($this->buf, $this->start);
            $this->start = strlen($this->buf);
        } else {
            if ($this->till <= 0 || $this->finish < $this->till)
                $till = $this->finish;
            else
                $till = $this->till;
            if ($this->filestart + $x + 1 > $till) {
                $this->found = false;
            } else {
                $this->result[] = substr($this->buf, $this->start, $x - $this->start);
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
        if (!empty($this->handle)) {
            if ($this->filestart <= $pos && (strlen($this->buf) + $this->filestart) > $pos) {
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
                $x = strrpos($this->buf, self::NL);
                if (false === $x) {
                    $this->start = strlen($this->buf);
                } else {
                    $this->start = $x;
                }
                if ($this->prepare()) {
                    continue;
                } else {
                    break;
                }
            } else {
                $this->start=$m[0][1]+strlen($m[0][0]);
                break;
            }
        } while (true);
        if(!$found)
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

            if ($reg{0} == '/' || $reg{0} == '~' ) { // so it's a regular expresion
                $res = preg_match($reg, $this->buf, $m, PREG_OFFSET_CAPTURE, $this->start);
                if ($res) {
                    if ($this->till <= 0 || $this->finish < $this->till)
                        $till = $this->finish;
                    else
                        $till = $this->till;
                    $plen=isset($m['fin'])?$m['fin'][1]:$m[0][1] + strlen($m[0][0]);
                    if ($this->filestart +$plen > $till) {
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
                $y = stripos($this->buf, trim($reg), $this->start);
                if (false !== $y) {
                    if ($this->till > 0 && $this->filestart + $y + strlen($reg) > $this->till) {
                        $this->position($this->till);
                        break;
                    }
                    $this->found = true;
                    $x = strpos($this->buf, self::NL, $y + strlen($reg));
                    if (false === $x)
                        $this->start = strlen($this->buf);
                    else
                        $this->start = $x - 1;
                    $xx = strrpos($this->buf, self::NL, $y - strlen($this->buf));
                    // echo $xx,' ',$y,' ',$this->start,self::NL;
                    $this->result[] = substr($this->buf, $xx, $this->start - $xx);
                }
            }
            if (!$this->found && !empty($this->handle) && !feof($this->handle)) { //3940043
                // $this->start=strlen($this->buf);
                $x = strrpos($this->buf, self::NL);
                if (false === $x) {
                    $this->start = strlen($this->buf);
                } else {
                    $this->start = $x;
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
        else $x = strrpos($this->buf, self::NL, $this->start - strlen($this->buf));
        $y = strpos($this->buf, self::NL, $this->start);
        if (false === $x) $x = 0; else $x++;
        if (false === $y) return substr($this->buf, $x);
        return substr($this->buf, $x, $y - $x);
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
            $this->position($oldstart);
        }
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
     */
    function syntax($tokens, $pattern, $callback)
    {
        // so build a reg
        $idx = array(0,'_skiped');
        while (preg_match('/:(\w+):/', $pattern, $m, PREG_OFFSET_CAPTURE)) {
            if (!isset($tokens[$m[1][0]])) break;
            $idx[] = $m[1][0];
            if (is_string($tokens[$m[1][0]])) $tokens[$m[1][0]] = array($tokens[$m[1][0]]);
            $pattern =
                substr($pattern, 0, $m[0][1]) .
                '(' . implode('|', $tokens[$m[1][0]]) . ')' .
                substr($pattern, $m[0][1] + strlen($m[0][0]));
        }
        if ($this->till < 0) {
            $till = $this->finish;
        } else {
            $till = $this->till;
        }
        $this->prepare(false);
        while(true) {
            $skiped='';
            while ($found=preg_match($pattern, $this->buf, $m, PREG_OFFSET_CAPTURE, $this->start)) {
                $skiped = substr($this->buf, $this->start, $m[0][1] - $this->start);
                $this->start = isset($m['fin'])?$m['fin'][1]:($m[0][1]+strlen($m[0][0]));
                if ($this->filestart + $this->start > $till) {
                    $this->start=$m[0][1]; // не терять тег на границе буфера todo: oppa! строка то фиксированной длниы?
                    break;
                }
                $r = array('_skiped' => $skiped);
                $skiped='';
                foreach ($idx as $i => $v) {
                    if (isset($m[$i]) && !empty($i)) {
                        if($idx[$i]=='_skiped')
                            $r[$idx[$i]] = $m[$i][0];
                        else
                            $r[$idx[$i]] = trim($m[$i][0], "\n\r ");
                    }
                }
                if (false === $callback($r)) break 2;
            }
            if(''!=$skiped){
                if (false === $callback(array('_skiped' => $skiped))) break;
            } else if(!$found){
                $skiped = substr($this->buf, $this->start);
                $this->start=strlen($this->buf);
                if (false === $callback(array('_skiped' => $skiped))) break;
            }
            if(!$this->prepare(false)) break;
        }
        $this->position($till);
    }

    function error($msg){
        echo $msg.PHP_EOL;
        return false;
    }

}
