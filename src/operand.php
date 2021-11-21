<?php
/**
 * Jinja language parcer
 * ----------------------------------------------------------------------------
 * $Id: Templater engine v 2.0 (C) by Ksnk (sergekoriakin@gmail.com).
 *      based on Twig sintax,
 * ver: v2.0, Last build: 2012012257
 * GIT: origin	https://github.com/Ksnk/templater (push)$
 * ----------------------------------------------------------------------------
 * License MIT - Serge Koriakin - 2020
 * ----------------------------------------------------------------------------
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