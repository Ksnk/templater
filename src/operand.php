<?php
/**
 * Jinja language parcer
 * <%=point('hat','jscomment');
 *
 *
 *
 *
 * %>
 */

namespace Ksnk\templater;


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

    function __construct($val, $type = tpl_parser::TYPE_NONE, $pos = 0)
    {
        $this->val = $val;
        $this->type = $type;
        $this->pos = $pos;
    }

    function __toString()
    {
        return $this->val;
    }

    function __get($name)
    {
        $this->$name = '';
        return '';
    }

    /*	// функция конвертирования операнда в новый тип
     function &_to($type){
         return $this;
     }*/
}

