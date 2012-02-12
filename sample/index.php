<?php
/**
 * sample
 * <%=point('hat','jscomment');
  
  
  
  
 %>
 */
// it's importatant a while
define('TEMPLATE_PATH',dirname(__FILE__).DIRECTORY_SEPARATOR.'templates');

// define path to include all useful files
ini_set('include_path',
  ini_get('include_path')
  .PATH_SEPARATOR.dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'lib'
);
  
require_once 'render.php';

// check if templates modified
require_once 'compiler.class.php';
template_compiler::checktpl();

// render root template with some data
templater('',array(
  'a_variable'=>'just a simple text. just a simple text. just a simple text. just a simple text. just a simple text. just a simple text. just a simple text. just a simple text. ',
  'navigation'=>array(
    array('href'=>'http://google.com', 'caption'=>'google it!'),
    array('href'=>'http://yandex.ru', 'caption'=>'yandex it!'),
  )
),'index');