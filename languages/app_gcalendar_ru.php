<?php
/**
* Russian language file for NUT module
*
*/

$dictionary=array(

/* general */
'GC_IMPORT_TITLE'=>'Импортировать календари из Google',
'GC_DELETE_TITLE'=>'Удалить календари из базы',
'GC_GOTO_GOOGLE_URL'=>'Перейдите по ссылке и получите код',
'GC_HELP'=>'Для того чтобы добавить готовые календари, вам нужно зайти в Google Calendar \ Настройки \ Календари'

/* end module names */

);

foreach ($dictionary as $k=>$v) {
 if (!defined('LANG_'.$k)) {
  define('LANG_'.$k, $v);
 }
}

?>