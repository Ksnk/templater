<?php
/**
 * simple template renderer
 * <%=point('hat','jscomment');




%>
 */

/**
 * Simple template renderer. points to one template.
 * but it's still possible to define another one.
 *
 * @param string $idx - template name, defined by block tag
 * @param array $par - data to render
 * @param string $tpl_class - define another base template
 *
 * @example
 * template() -- just repnder default template
 * templater(null,null,'template1') - define template1 class as main template
 * templater('one',array('name'=>'User') - render block one with data.
 */
function templater($idx=null,$par=null,$tpl_class='compiler'){
    static $tpl_compiler;
    if(!is_null($tpl_class) || empty($tpl_compiler)) {
        $tpl_compiler='tpl_'.pps($tpl_class,'compiler');
        if(!class_exists($tpl_compiler)){
            // попытка включить файл
            include_once TEMPLATE_PATH.DIRECTORY_SEPARATOR.$tpl_compiler.'.php';
        };
        $tpl_compiler=new $tpl_compiler();
    }

    if (!is_null($par)){
        if (method_exists($tpl_compiler,'_'.$idx))
            echo call_user_func (array($tpl_compiler,'_'.$idx),$par);
        else
            printf('have no template "%s:%s"',$tpl_compiler,'_'.$idx);
    }
    return '';
}
