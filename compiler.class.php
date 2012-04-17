<?php
/**
 * helper class to check template modification time
 * <%=point('hat','jscomment');
// эти пустые строки оставлены для того, чтобы номера строк совпадали   



%>
 */

/**
 * helper function to check if value is empty
 */
if (!function_exists('pps')) {
    function pps(&$x, $default = '')
    {
        if (empty($x)) return $default; else return $x;
    }
}
if (!function_exists('ps')) {
    function ps($x, $default = '')
    {
        if (empty($x)) return $default; else return $x;
    }
}

class template_compiler
{

    static $filename = '';

    static private $opt = array(
        'templates_dir' => 'templates/',
        'TEMPLATE_EXTENSION' => 'jtpl'
    );

    static public function options($options = '', $val = null)
    {
        if (is_array($options))
            self::$opt = array_merge(self::$opt, $options);
        else if (!is_null($val))
            self::$opt[$options] = $val;
        else if (isset(self::$opt[$options]))
            return self::$opt[$options];
    }

    static function do_prepare()
    {
        static $done;
        if (!empty($done)) return;
        require_once 'nat2php.class.php';
        require_once 'template_parser.class.php';
        require_once 'compiler.php.php';
        $done = true;
    }

    /**
     * функция компиляции текста. Результатом будет
     * текст функции, для вставки в шаблон
     * @param string $tpl
     */
    static function compile_tpl($tpl, $name = 'compiler')
    {
        static $calc;
        if (empty($calc)) {
            $calc = new php_compiler();
        }
        //compile it;
        $result = '';
        try {
            $calc->makelex($tpl);
            $result = $calc->tplcalc($name);
        } catch (Exception $e) {
            echo $e->getMessage();
            //echo '<pre> filename:'.self::$filename.'<br>';print_r($calc);echo'</pre>';
            return null;
        }
        //execute it
        return $result;

    }

    /**
     * проверка даты изменения шаблона-образца
     */
    static function checktpl($options = '')
    {
        static $include_done;
        if (defined('TEMPLATE_PATH')) {
            self::options('TEMPLATE_PATH', TEMPLATE_PATH);
            self::options('PHP_PATH', TEMPLATE_PATH);
        }
        self::options('TEMPLATE_EXTENSION', 'jtpl');
        if (!empty($options))
            self::options($options);
        $time = microtime(true);
        $templates = glob(self::options('TEMPLATE_PATH') . DIRECTORY_SEPARATOR . '*.' . self::options('TEMPLATE_EXTENSION'));
        //print_r('xxx'.$templates);echo " !";
        $xtime = filemtime(__FILE__);
        if (!empty($templates)) {
            foreach ($templates as $v) {
                $name = basename($v, "." . self::options('TEMPLATE_EXTENSION'));
                $phpn = self::options('PHP_PATH') . DIRECTORY_SEPARATOR . 'tpl_' . $name . '.php';
                //echo($phpn.' '.$v);
                if (!file_exists($phpn)
                    ||
                    (max($xtime, filemtime($v)) > filemtime($phpn))
                ) {
                    if (empty($include_done)) {
                        $include_done = true;
                        require_once 'nat2php.class.php';
                        require_once 'template_parser.class.php';
                        require_once 'compiler.php.php';
                    }
                    self::$filename = $v;
                    $x = self::compile_tpl(file_get_contents($v), $name);
                    if (!!$x)
                        file_put_contents($phpn, $x);
                }
            }
        }
        $time = microtime(true) - $time;
        //echo $time.' sec spent';
    }

}

