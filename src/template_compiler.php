<?php
/**
 * helper class to check template modification time
 * <%=point('hat','jscomment');
// эти пустые строки оставлены для того, чтобы номера строк совпадали



%>
 */

namespace Ksnk\templater ;

use \Ksnk\templater\php_compiler;

class template_compiler
{

    static $filename = '';

    static private $opt = array(
        'templates_dir' => 'templates/',
        'TEMPLATE_EXTENSION' => 'jtpl',
        'namespace'=>'Ksnk\templates'
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
    static function compile_tpl($tpl, $name = 'compiler', $tpl_class = '')
    {
        static $calc;
        if (empty($calc)) {
            $calc = new php_compiler();
        } else {
            $calc->reset();
        }
        //compile it;
        $result = '';
        try {
            $calc->makelex($tpl);
            $ns=self::options('namespace');
            if(!empty($ns))
                $calc->namespace=$ns;
            $bns=self::options('basenamespace');
            if(!empty($bns))
                $calc->basenamespace=$bns;
            $result = $calc->tplcalc($name, $tpl_class);
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

        //$time = microtime(true);
        $templates = glob(self::options('TEMPLATE_PATH') . DIRECTORY_SEPARATOR . '*.' . $ext);
        //print_r('xxx'.$templates);echo " !";
        $xtime = filemtime(__FILE__);

        if (!empty($templates)) {
            foreach ($templates as $v) {
                $name = str_replace('.','_',basename($v, "." . $ext));
                $phpn = self::options('PHP_PATH') . DIRECTORY_SEPARATOR . $name . '.php';
                $force=self::options('FORCE');
                $NLBR=php_sapi_name() == "cli"?"\n":"<br>\n";
                if (
                    !empty($force)
                    || !file_exists($phpn)
                    || (max($xtime, filemtime($v)) > filemtime($phpn))
                ) {
                    php_compiler::$filename = $v;
                    $x = self::compile_tpl(file_get_contents($v), $name);
                    if (!!$x) {
                        if(false===($size=file_put_contents($phpn, $x))){
                            if($force) echo $NLBR."error writing file " .$phpn;
                        } else {
                            if($force) printf($NLBR."success writing file %s(%s)",$phpn,$size);
                        };
                    } else if($force) {
                        echo $NLBR."fail with " .$phpn;
                    }
                } else {
                    if($force) {
                        echo $NLBR."skipped with " .$phpn;
                    }
                }
            }
        }
    }

}

