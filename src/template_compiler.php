<?php
/**
 * helper class to check template modification time
 * <%=point('hat','jscomment');
// эти пустые строки оставлены для того, чтобы номера строк совпадали



%>
 */

namespace Ksnk\templater ;

class template_compiler
{

    static $filename = '';

    static private $opt = array(
        'templates_dir' => 'templates/',
        'TEMPLATE_EXTENSION' => 'jtpl'
    );

    static public function options($options = '', $val = null,$default='')
    {
        if (is_array($options))
            self::$opt = array_merge(self::$opt, $options);
        else if (!is_null($val))
            self::$opt[$options] = $val;
        else if (isset(self::$opt[$options]))
            return self::$opt[$options];

        return $default;
    }

    /**
     * функция компиляции текста. Результатом будет
     * текст функции, для вставки в шаблон
     * @param string $tpl
     * @return mixed|null|string
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
        } catch (CompilationException $e) {
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
        if (defined('TEMPLATE_PATH')) {
            self::options('TEMPLATE_PATH', TEMPLATE_PATH);
            self::options('PHP_PATH', TEMPLATE_PATH);
        }
        if (!empty($options))
            self::options($options);

        $ext = self::options('TEMPLATE_EXTENSION', null, 'jtpl');

        if (!class_exists('tpl_base'))
            include_once (template_compiler::options('templates_dir') . 'tpl_base.php');
        //$time = microtime(true);
        $templates = glob(self::options('TEMPLATE_PATH') . DIRECTORY_SEPARATOR . '*.' . $ext);
        //print_r('xxx'.$templates);echo " !";
        $xtime = filemtime(__FILE__);

        if (!empty($templates)) {
            foreach ($templates as $v) {
                $name = basename($v, "." . $ext);
                $phpn = self::options('PHP_PATH') . DIRECTORY_SEPARATOR . 'tpl_' . $name . '.php';
                if (
                    ''!=empty(self::options('FORCE'))
                    || !file_exists($phpn)
                    || (max($xtime, filemtime($v)) > filemtime($phpn))
                ) {
                    php_compiler::$filename = $v;
                    $x = self::compile_tpl(file_get_contents($v), $name);
                    if (!!$x)
                        file_put_contents($phpn, $x);
                }
            }
        }
    }

}

