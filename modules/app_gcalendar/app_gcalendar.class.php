<?
/**
* GCalendar Import
*
* @package project
* @author Ivan Z. <ivan@jad.ru>
* @version 0.1 (wizard, 16:21 [29 01, 2015])
*/
//
//
class app_gcalendar extends module {
	
	private $client;
	
/**
* Module class constructor
*
* @access private
*/
function app_gcalendar() {
  $this->name="app_gcalendar";
  $this->title="Google Calendar";
  $this->module_category="<#LANG_SECTION_APPLICATIONS#>";
  $this->checkInstalled();
}
/**
* saveParams
*
* Saving module parameters
*
* @access public
*/
function saveParams() {
 $p=array();
 if (IsSet($this->id)) {
  $p["id"]=$this->id;
 }
 if (IsSet($this->view_mode)) {
  $p["view_mode"]=$this->view_mode;
 }
 if (IsSet($this->edit_mode)) {
  $p["edit_mode"]=$this->edit_mode;
 }
 if (IsSet($this->tab)) {
  $p["tab"]=$this->tab;
 }
 return parent::saveParams($p);
}
/**
* getParams
*
* Getting module parameters from query string
*
* @access public
*/
function getParams() {
  global $id;
  global $mode;
  global $view_mode;
  global $edit_mode;
  global $tab;
  if (isset($id)) {
   $this->id=$id;
  }
  if (isset($mode)) {
   $this->mode=$mode;
  }
  if (isset($view_mode)) {
   $this->view_mode=$view_mode;
  }
  if (isset($edit_mode)) {
   $this->edit_mode=$edit_mode;
  }
  if (isset($tab)) {
   $this->tab=$tab;
  }
}
/**
* Run
*
* Description
*
* @access public
*/
function run() {
  global $session;
  $out=array();
  if ($this->action=='admin') {
   $this->admin($out);
  } else {
   $this->usual($out);
  }
  if (IsSet($this->owner->action)) {
   $out['PARENT_ACTION']=$this->owner->action;
  }
  if (IsSet($this->owner->name)) {
   $out['PARENT_NAME']=$this->owner->name;
  }
  $out['VIEW_MODE']=$this->view_mode;
  $out['EDIT_MODE']=$this->edit_mode;
  $out['MODE']=$this->mode;
  $out['ACTION']=$this->action;
  if ($this->single_rec) {
   $out['SINGLE_REC']=1;
  }
  $this->data=$out;
  $p=new parser(DIR_TEMPLATES.$this->name."/".$this->name.".html", $this->data, $this);
  $this->result=$p->result;
}
/**
* BackEnd
*
* Module backend
*
* @access public
*/
function admin(&$out) {	
	
	if ($this->view_mode == 'logout'){
	  unset($_SESSION['access_token']);
	  $this->view_mode = '';		
	} else if ($this->view_mode == "seldelete"){
	  $this->selectDelete($out);
	} else if ($this->view_mode == "delete"){  
	   $this->delete($out);
	   $this->view_mode = '';
	} else if ($this->view_mode != ""){
		require_once './lib/Google/autoload.php';
		
		session_start();		
		
		$this->client = new Google_Client();
		$this->client->setClientId('328872985588-7rmn68kb878j4pomshnltglcm86usgfu.apps.googleusercontent.com');
		$this->client->setClientSecret('JsOXXVLtiRDqVre5CXYJow0I');
		$this->client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
		$this->client->addScope("https://www.googleapis.com/auth/calendar.readonly");
		
		global $code; // $_GET['code']
		if (isset($code)) {			
			$this->client->authenticate($code);
			$_SESSION['access_token'] = $this->client->getAccessToken();		
		}

		if (isset($_SESSION['access_token']) && $_SESSION['access_token']) { 		    
			$this->client->setAccessToken($_SESSION['access_token']);
		} else {		    
			$out['AUTOURL'] = $this->client->createAuthUrl();
		}
		
		if ($this->client->getAccessToken()) {
		  $_SESSION['access_token'] = $this->client->getAccessToken();
		  
		  
		  if ($this->view_mode == 'import'){
			$this->selectCalendar($out);
		  } else if ($this->view_mode == 'import_data'){		  
			$this->import_data($out);
		  }
		} else {
		  // Nide token	  
		  $this->view_mode = 'nide_token';
		}		
	} else {
		unset($_SESSION['access_token']);
	}
}
/**
* FrontEnd
*
* Module frontend
*
* @access public
*/
function usual(&$out) {
 $this->admin($out);
}

/**
* Select delete
*
* @access public
*/
function selectDelete(&$out) {
  $CATEGORIES=SQLSelect("SELECT ID, TITLE FROM calendar_categories;"); 
  
  $out['CATEGORIES'] = $CATEGORIES;
}

/**
* Delete
*
* @access public
*/
function delete(&$out) {
  global $calendars_selected;
  
  foreach ($calendars_selected as $itm){
	SQLExec("DELETE FROM calendar_categories WHERE ID='".$itm."'"); 
    SQLExec("DELETE FROM calendar_events WHERE CALENDAR_CATEGORY_ID='".$itm."'"); 
  }
}

/**
* Select calendars
*
* @access public
*/
function selectCalendar(&$out) {
  $service = new Google_Service_Calendar($this->client);

  $calendarlist = $service->calendarList->listCalendarList();

  $CALENDARS = array();
  while(true) {
	foreach ($calendarlist->getitems() as $calendarlistentry) {
	  $CALENDAR['ID'] = $calendarlistentry->getId();
	  $CALENDAR['NAME'] = $calendarlistentry->getsummary();
	  $CALENDAR['VALUE'] = $calendarlistentry->getDescription();
	  
	  $CALENDARS[] = $CALENDAR;
	}
	
	$pagetoken = $calendarlist->getnextpagetoken();
	if ($pagetoken) {
		$optparams = array('pagetoken' => $pagetoken);
		$calendarlist = $service->calendarlist->listcalendarlist($optparams);
	} else {
		break;
	}
  }
  
  $out['CALENDARS']=$CALENDARS; 
}

/**
* import_data
*
* @access public
*/
function import_data(&$out) {
  global $calendars_selected;
  
  $service = new Google_Service_Calendar($this->client);	
  $calendarlist = $service->calendarList->listCalendarList();
  
  $RESULT_LOG = NULL;
  
  while(true) {
	  	
	foreach ($calendarlist->getitems() as $calendarlistentry) {
	  $cid = $calendarlistentry->getId();
	  
	  if (in_array($cid, $calendars_selected)) {
		  $ctitle = $calendarlistentry->getSummary();
		  
		  // Append calendar category
		  $rec=SQLSelectOne("SELECT * FROM calendar_categories WHERE TITLE='$ctitle'");
		  if (!$rec['ID']) {
			$rec['TITLE']=$ctitle;
			$rec['ACTIVE']=1;			
			$rec['ID']=SQLInsert('calendar_categories', $rec);
		  } else {
			// Clear old
			SQLExec("DELETE FROM calendar_events WHERE CALENDAR_CATEGORY_ID='".$rec['ID']."'"); 
		  }
		  $cCat = $rec['ID'];
		  
		  $events = $service->events->listEvents($cid);
		  
		  while(true) {
			foreach ($events->getItems() as $event) {
			  $node = NULL;
			  
			  // Title			  
			  $node['TITLE'] = $event->getSummary();
			  
			  // Notes
			  $notes = $event->getDescription();
			  if ($notes){
			    $node['NOTES'] = $event->getDescription();
			  }
			  
			  // Added
			  $node['ADDED'] = $event->getCreated();
			  
			  // Due
			  $start = $event->getStart();			  
			  if ($start) {
				if ($start['dateTime']){
			      $node['DUE']  = $start['dateTime'];				
				} else if ($start['date']){
				  $node['DUE']  = $start['date'];				
			    } else {
				  $RESULT_LOG[] = "Invalid start date :".print_r($start, true);
			    }
			  }			  			  
			  
			  // End
			  $end = $event->getEnd();
			  if ($end) {
				if ($end['dateTime']){
			      $node['DONE_WHEN']  = $end['dateTime'];
				} else if ($end['date']){
			      $node['DONE_WHEN']  = $end['date'];
				} else {
				  $RESULT_LOG[] = "Invalid end date :".print_r($start, true);
				}
			  }
			  
			  // Repeat
		      $recurrence = $event->getRecurrence();
			  if ($recurrence) {
			    foreach ($recurrence as $itm) {
				  // YEARLY
				  if (strpos($itm, 'RRULE:FREQ=YEARLY') !== false){
					$node['IS_REPEATING'] = 1;
				    $node['REPEAT_TYPE'] = 1;
				    $node['REPEAT_IN'] = 3;						
				  } 
				  // WEEKLY
				  else if (strpos($itm, 'RRULE:FREQ=WEEKLY') !== false){
					$node['IS_REPEATING'] = 1;
				    $node['REPEAT_TYPE'] = 3;
				    $node['REPEAT_IN'] = 3;					  
				  } 
				  // Unknow
				  else {
					$RESULT_LOG[] = "Unknow recurrence: ".$itm;				    
				  }  
			    }
			  }			 
			  
			  // Category
			  $node['CALENDAR_CATEGORY_ID'] = $cCat;			  
			  			  
			  SQLInsert('calendar_events', $node);
			}
			$pageToken = $events->getNextPageToken();
			if ($pageToken) {
				$optParams = array('pageToken' => $pageToken);
				$events = $service->events->listEvents('primary', $optParams);
			} else {
				break;
			}
		}
	  }	  
	}
	
	$pagetoken = $calendarlist->getnextpagetoken();
	if ($pagetoken) {
		$optparams = array('pagetoken' => $pagetoken);
		$calendarlist = $service->calendarlist->listcalendarlist($optparams);
	} else {
		break;
	}
  }  
  
  if (empty($RESULT_LOG)){
    $RESULT_LOG[] = "Import OK";
  }
  
  $out['RESULT_LOG'] = implode("<BR/>\n", $RESULT_LOG);
}

/**
* Install
*
* Module installation routine
*
* @access private
*/
 function install() {
  parent::install();
 }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgTWFyIDA0LCAyMDEwIHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
?>