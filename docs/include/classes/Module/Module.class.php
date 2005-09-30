<?php
/************************************************************************/
/* ATutor																*/
/************************************************************************/
/* Copyright (c) 2002-2005 by Greg Gay, Joel Kronenberg & Heidi Hazelton*/
/* Adaptive Technology Resource Centre / University of Toronto			*/
/* http://atutor.ca														*/
/*																		*/
/* This program is free software. You can redistribute it and/or		*/
/* modify it under the terms of the GNU General Public License			*/
/* as published by the Free Software Foundation.						*/
/************************************************************************/
// $Id$

// module statuses
// do not confuse with _MOD_ constants!

define('AT_MODULE_STATUS_DISABLED',    1);
define('AT_MODULE_STATUS_ENABLED',     2);
define('AT_MODULE_STATUS_UNINSTALLED', 4); // not in the db

define('AT_MODULE_HOME',	1);
define('AT_MODULE_MAIN',	2);
define('AT_MODULE_SIDE',	4);

define('AT_MODULE_TYPE_CORE',     1);
define('AT_MODULE_TYPE_STANDARD', 2);
define('AT_MODULE_TYPE_EXTRA',    4);

define('AT_MODULE_DIR_CORE',     '_core');
define('AT_MODULE_DIR_STANDARD', '_standard');

define('AT_MODULE_PATH', realpath(AT_INCLUDE_PATH.'../mods') . DIRECTORY_SEPARATOR);

/**
* ModuleFactory
* 
* @access	public
* @author	Joel Kronenberg
* @package	Module
*/
class ModuleFactory {
	// private
	var $_modules = NULL; // array of module refs

	function ModuleFactory($auto_load = FALSE) {
		global $db;

		$this->_modules = array();

		if ($auto_load == TRUE) {
			// initialise enabled modules
			$sql	= "SELECT dir_name, privilege, admin_privilege, status, display_defaults FROM ". TABLE_PREFIX . "modules WHERE status=".AT_MODULE_STATUS_ENABLED;
			$result = mysql_query($sql, $db);
			while($row = mysql_fetch_assoc($result)) {
				$module =& new ModuleProxy($row);
				$this->_modules[$row['dir_name']] =& $module;
				$module->load();
			}
		}
	}

	// public
	// state := enabled | disabled | uninstalled
	// type  := core | standard | extra
	// sort  := true | false
	// the results of this method are not cached. call sparingly.
	function & getModules($status, $type = 0, $sort = FALSE) {
		global $db;

		$modules     = array();
		$all_modules = array();

		if ($type == 0) {
			$type = AT_MODULE_TYPE_CORE | AT_MODULE_TYPE_STANDARD | AT_MODULE_TYPE_EXTRA;
		}

		$sql	= "SELECT dir_name, privilege, admin_privilege, status, display_defaults FROM ". TABLE_PREFIX . "modules";
		$result = mysql_query($sql, $db);
		while($row = mysql_fetch_assoc($result)) {
			if (!isset($this->_modules[$row['dir_name']])) {
				$module =& new ModuleProxy($row);
			} else {
				$module =& $this->_modules[$row['dir_name']];
			}
			$all_modules[$row['dir_name']] =& $module;
		}

		// small performance addition:
		if (query_bit($status, AT_MODULE_STATUS_UNINSTALLED)) {
			$dir = opendir(AT_MODULE_PATH);
			while (false !== ($dir_name = readdir($dir))) {
				if (($dir_name == '.') 
					|| ($dir_name == '..') 
					|| ($dir_name == '.svn') 
					|| ($dir_name == AT_MODULE_DIR_CORE) 
					|| ($dir_name == AT_MODULE_DIR_STANDARD)) {
					continue;
				}

				if (is_dir(AT_MODULE_PATH . $dir_name) && !isset($all_modules[$dir_name])) {
					$module =& new ModuleProxy($dir_name);
					$all_modules[$dir_name] =& $module;
				}
			}
			closedir($dir);
		}

		$keys = array_keys($all_modules);
		foreach ($keys as $dir_name) {
			$module =& $all_modules[$dir_name];
			if ($module->checkStatus($status) && $module->checkType($type)) {
				$modules[$dir_name] =& $module;
			}
		}

		if ($sort) {
			uasort($modules, array($this, 'compare'));
		}
		return $modules;
	}

	// public.
	function & getModule($module_dir) {
		if (!isset($this->_modules[$module_dir])) {
			$module =& new ModuleProxy($module_dir);
			$this->_modules[$module_dir] =& $module;
		}
		return $this->_modules[$module_dir];
	}

	// private
	// used for sorting modules
	function compare($a, $b) {
		return strnatcmp($a->getName(), $b->getName());
	}
}

/**
* ModuleProxy
* 
* @access	public
* @author	Joel Kronenberg
* @package	Module
*/
class ModuleProxy {
	// private
	var $_moduleObj;
	var $_directoryName;
	var $_status; // core|enabled|disabled
	var $_privilege; // priv bit(s) | 0 (in dec form)
	var $_admin_privilege; // priv bit(s) | 0 (in dec form)
	var $_display_defaults; // bit(s)
	var $_pages;
	var $_type; // core, standard, extra

	// constructor
	function ModuleProxy($row) {
		if (is_array($row)) {
			$this->_directoryName   = $row['dir_name'];
			$this->_status          = $row['status'];
			$this->_privilege       = $row['privilege'];
			$this->_admin_privilege = $row['admin_privilege'];
			$this->_display_defaults= $row['display_defaults'];

			if (strpos($row['dir_name'], AT_MODULE_DIR_CORE) === 0) {
				$this->_type = AT_MODULE_TYPE_CORE;
			} else if (strpos($row['dir_name'], AT_MODULE_DIR_STANDARD) === 0) {
				$this->_type = AT_MODULE_TYPE_STANDARD;
			} else {
				$this->_type = AT_MODULE_TYPE_EXTRA;
			}
		} else {
			$this->_directoryName   = $row;
			$this->_status          = AT_MODULE_STATUS_UNINSTALLED;
			$this->_privilege       = 0;
			$this->_admin_privilege = 0;
			$this->_display_defaults= 0;
			$this->_type            = AT_MODULE_TYPE_EXTRA; // standard/core are installed by default
		}
	}

	// statuses
	function checkStatus($status) { return query_bit($status, $this->_status); }
	function isUninstalled()  { return ($this->_status == AT_MODULE_STATUS_UNINSTALLED)  ? true : false; }
	function isEnabled()      { return ($this->_status == AT_MODULE_STATUS_ENABLED)      ? true : false; }
	function isDisabled()     { return ($this->_status == AT_MODULE_STATUS_DISABLED)     ? true : false; }

	// types
	function checkType($type) { return query_bit($type, $this->_type); }
	function isCore()     { return ($this->_type == AT_MODULE_TYPE_CORE)     ? true : false; }
	function isStandard() { return ($this->_type == AT_MODULE_TYPE_STANDARD) ? true : false; }
	function isExtra()    { return ($this->_type == AT_MODULE_TYPE_EXTRA)    ? true : false; }

	// privileges
	function getPrivilege()      { return $this->_privilege;       }
	function getAdminPrivilege() { return $this->_admin_privilege; }

	// private!
	function initModuleObj() {
		if (!isset($this->_moduleObj)) {
			$this->_moduleObj =& new Module($this->_directoryName);
		}
	}

	function getProperties($properties_list) {
		// this requires a real module object
		$this->initModuleObj();
		return $this->_moduleObj->getProperties($properties_list);
	}

	function getProperty($property) {
		$this->initModuleObj();
		return $this->_moduleObj->getProperty($property);
	}

	function getName() {
		if ($this->isUninstalled()) {
			return current($this->getProperty('name'));
		}
		return _AT(basename($this->_directoryName));
	}

	function getDescription($lang) {
		$this->initModuleObj();
		return $this->_moduleObj->getDescription($lang);
	}

	function load() {
		if (is_file(AT_MODULE_PATH . $this->_directoryName.'/module.php')) {
			global $_modules, $_pages, $_stacks;

			require(AT_MODULE_PATH . $this->_directoryName.'/module.php');
			if (isset($_module_pages)) {
				$this->_pages =& $_module_pages;

				$_pages = array_merge_recursive($_pages, $this->_pages);
			}

			//side menu items
			if (isset($_module_stacks)) {
				$count = 0;
				foreach($_module_stacks as $mod_stack) {
					$_module_stacks[$count]['mod_name'] = $this->_directoryName;
					$count++;
				}
				$this->_stacks =& $_module_stacks;
				$_stacks = array_merge($_stacks, $this->_stacks);
			}

			//student tools
			if (isset($_student_tools)) {
				$this->_student_tools =& $_student_tools;
				$_modules[] = $this->_student_tools;
			}
		}					
	}

	function getChildPage($page) {
		if (!is_array($this->_pages)) {
			return;
		}
		foreach ($this->_pages as $tmp_page => $item) {
			if ($item['parent'] == $page) {
				return $tmp_page;
			}
		}
	}

	function isBackupable() {
		return is_file(AT_MODULE_PATH . $this->_directoryName.'/module_backup.php');
	}

	function backup($course_id, &$zipfile) {
		$this->initModuleObj();
		$this->_moduleObj->backup($course_id, $zipfile);
	}

	function restore($course_id, $version, $import_dir) {
		$this->initModuleObj();
		$this->_moduleObj->restore($course_id, $version, $import_dir);
	}

	function delete($course_id) {
		$this->initModuleObj();
		$this->_moduleObj->delete($course_id);
	}

	function enable() {
		$this->initModuleObj();
		$this->_moduleObj->enable();
		$this->_status = AT_MODULE_STATUS_ENABLED;
	}

	function disable() {
		$this->initModuleObj();
		$this->_moduleObj->disable();
		$this->_status = AT_MODULE_STATUS_DISABLED;
	}

	function install() {
		$this->initModuleObj();
		$this->_moduleObj->install();
	}

	function getStudentTools() {
		if (!isset($this->_student_tools)) {
			return;
		} 

		return $this->_student_tools;
	}

/*	function getDisplayDefaults() {
		global $db;

		if (empty($this->_student_tools)) {
			return;
		}

		$defaults = array();

		$defaults['student_tool'] = $this->_student_tools;
		$defaults['total'] = $this->_display_defaults;

		if (query_bit($this->_display_defaults, AT_MODULE_HOME)) {
			$defaults['home'] = TRUE;
		} else {
			$defaults['home'] = FALSE;
		}
		if (query_bit($this->_display_defaults, AT_MODULE_MAIN)) {
			$defaults['main'] = TRUE;
		} else {
			$defaults['main'] = FALSE;
		}

		return $defaults;
	}
*/
}


// ----------------- in a diff file. only required when .. required.


/**
* Module
* 
* @access	protected
* @author	Joel Kronenberg
* @package	Module
*/
class Module {
	// all private
	var $_directoryName;
	var $_properties; // array from xml

	function Module($dir_name) {
		require_once(dirname(__FILE__) . '/ModuleParser.class.php');
		$moduleParser   =& new ModuleParser();
		$this->_directoryName = $dir_name;
		$moduleParser->parse(@file_get_contents(AT_MODULE_PATH . $dir_name.'/module.xml'));
		if ($moduleParser->rows[0]) {
			$this->_properties = $moduleParser->rows[0];
		} else {
			$this->_properties = array();
		}
	}


	function getDescription($lang = 'en') {
		if (!$this->_properties) {
			return;
		}

		return (isset($this->_properties['description'][$lang]) ? $this->_properties['description'][$lang] : current($this->_properties['description']));
	}

	/**
	* Get the properties of this module as found in the module.xml file
	* @access  public
	* @param   array $properties_list	list of property names
	* @return  array associative array of property/value pairs
	* @author  Joel Kronenberg
	*/
	function getProperties($properties_list) {
		if (!$this->_properties) {
			return;
		}
		$properties_list = array_flip($properties_list);
		foreach ($properties_list as $property => $garbage) {
			$properties_list[$property] = $this->_properties[$property];
		}
		return $properties_list;
	}

	/**
	* Get a single property as found in the module.xml file
	* @access  public
	* @param   string $property	name of the property to return
	* @return  string the value of the property 
	* @author  Joel Kronenberg
	*/
	function getProperty($property) {
		if (!$this->_properties) {
			return;
		}

		return $this->_properties[$property];
	}

	/**
	* Checks whether or not this module can be backed-up
	* @access  public
	* @return  boolean true if this module can be backed-up, false otherwise
	* @author  Joel Kronenberg
	*/
	function isBackupable() {
		return is_file(AT_MODULE_PATH . $this->_directoryName . '/module_backup.php');
	}

	/**
	* Backup this module for a given course
	* @access  public
	* @param   int		$course_id	ID of the course to backup
	* @param   object	$zipfile	a reference to a zipfile object
	* @author  Joel Kronenberg
	*/
	function backup($course_id, &$zipfile) {
		static $CSVExport;

		if (!isset($CSVExport)) {
			require_once(AT_INCLUDE_PATH . 'classes/CSVExport.class.php');
			$CSVExport = new CSVExport();
		}
		$now = time();

		if ($this->isBackupable()) {
			require(AT_MODULE_PATH . $this->_directoryName . '/module_backup.php');
			if (isset($sql)) {
				foreach ($sql as $file_name => $table_sql) {
					$content = $CSVExport->export($table_sql, $course_id);
					$zipfile->add_file($content, $file_name . '.csv', $now);
				}
			}

			if (isset($dirs)) {
				foreach ($dirs as $dir => $path) {
					$path = str_replace('?', $course_id, $path);

					$zipfile->add_dir($path , $dir);
				}
			}
		}
	}
	
	/**
	* Restores this module into the given course
	* @access  public
	* @param   int		$course_id	ID of the course to restore into
	* @param   string	$version	version number of the ATutor installation used to make this backup
	* @param   string	$import_dir	the path to the import directory
	* @author  Joel Kronenberg
	*/
	function restore($course_id, $version, $import_dir) {
		static $CSVImport;
		if (!file_exists(AT_MODULE_PATH . $this->_directoryName.'/module_backup.php')) {
			return;
		}

		if (!isset($CSVImport)) {
			require_once(AT_INCLUDE_PATH . 'classes/CSVImport.class.php');
			$CSVImport = new CSVImport();
		}

		require(AT_MODULE_PATH . $this->_directoryName.'/module_backup.php');
		if (isset($sql)) {
			foreach ($sql as $table_name => $table_sql) {
				$CSVImport->import($table_name, $import_dir, $course_id);
			}
		}
		if (isset($dirs)) {
			foreach ($dirs as $src => $dest) {
				$dest = str_replace('?', $course_id, $dest);
				copys($import_dir.$src, $dest);
			}
		}
	}

	/**
	* Delete this module's course content
	* @access  public
	* @param   int $course_id	ID of the course to delete
	* @author  Joel Kronenberg
	*/
	function delete($course_id) {
		if (is_file(AT_MODULE_PATH . $this->_directoryName.'/module_delete.php')) {
			require(AT_MODULE_PATH . $this->_directoryName.'/module_delete.php');
			if (function_exists($this->_directoryName.'_delete')) {
				$fnctn = $this->_directoryName.'_delete';
				$fnctn($course_id);
			}
		}
	}

	/**
	* Enables the installed module
	* @access  public
	* @author  Joel Kronenberg
	*/
	function enable() {
		global $db;

		$sql = 'UPDATE '. TABLE_PREFIX . 'modules SET status='.AT_MODULE_STATUS_ENABLED.' WHERE dir_name="'.$this->_directoryName.'"';
		$result = mysql_query($sql, $db);
	}

	/**
	* Disables the installed module
	* @access  public
	* @author  Joel Kronenberg
	*/
	function disable() {
		global $db;

		$sql = 'UPDATE '. TABLE_PREFIX . 'modules SET status='.AT_MODULE_STATUS_DISABLED.' WHERE dir_name="'.$this->_directoryName.'"';
		$result = mysql_query($sql, $db);
	}

	/**
	* Installs the module
	* @access  public
	* @author  Joel Kronenberg
	*/
	function install() {
		global $db;

		$sql = "SELECT MAX(`privilege`) AS `privilege`, MAX(admin_privilege) AS admin_privilege FROM ".TABLE_PREFIX."modules";
		$result = mysql_query($sql, $db);
		$row = mysql_fetch_assoc($result);

		if (strcasecmp($this->_properties['instructor_privilege'], 'create') == 0) {
			$priv = $row['privilege'] * 2;
		} else if (strcasecmp($this->_properties['instructor_privilege'], 'existing') == 0) {
			$priv = AT_PRIV_ADMIN;
		} else {
			$priv = 0;
		}

		if (strcasecmp($this->_properties['admin_privilege'], 'create') == 0) {
			$admin_priv = $row['admin_privilege'] * 2;
		} else if (strcasecmp($this->_properties['admin_privilege'], 'existing') == 0) {
			$admin_priv = AT_ADMIN_PRIV_ADMIN;
		} else {
			$admin_priv = 0;
		}

		// check if the directory is writeable
		if ($this->_properties['directory']) {
			$dir = AT_MODULE_PATH . $this->_directoryName . DIRECTORY_SEPARATOR . $this->_properties['directory'];
			if (!is_dir($dir) && !@mkdir($dir)) {
				global $msg;
				$msg->addError(array('DIR_NOT_EXIST', $dir));
				return;
			} else if (!is_writable($dir) && @chmod($dir, 0666)) {
				global $msg;
				$msg->addError(array('DIR_NOT_WRITEABLE', $dir));
				return;
			}
		}

		$sql = 'INSERT INTO '. TABLE_PREFIX . 'modules VALUES ("'.$this->_directoryName.'", '.AT_MODULE_STATUS_DISABLED.', '.$priv.', '.$admin_priv.')';
		$result = mysql_query($sql, $db);

		if (mysql_affected_rows($db) == 1) {
			// check for a .sql file that has to be run

		}
	}

}

?>