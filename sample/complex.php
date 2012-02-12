<?php
/**
 * complex templater with possible to find and compile absent templates
 * <%=point('hat','jscomment');
  
  
  
  
 %>
 */

/**
 * Simple template renderer. points to one template.
 *  but it's possible to use another one.
 * 
 * @param string $idx - template name, defined by block tag
 * @param array $par - data to render
 * @param string $tpl_class - define another base template
 */