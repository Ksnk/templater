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
 * класс для вывода ошибок компиляции
 */
class CompilationException extends \Exception
{
}