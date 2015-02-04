﻿<?php
/**
* Russian language file for NUT module
*
*/

$dictionary=array(

/* general */
'GC_IMPORT_TITLE'=>'Import calendars from Google',
'GC_DELETE_TITLE'=>'Delete Calendar from base',
'GC_GOTO_GOOGLE_URL'=>'Goto URL and get access code'

/* end module names */

);

foreach ($dictionary as $k=>$v) {
 if (!defined('LANG_'.$k)) {
  define('LANG_'.$k, $v);
 }
}

?>