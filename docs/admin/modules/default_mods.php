<?php
/****************************************************************/
/* ATutor														*/
/****************************************************************/
/* Copyright (c) 2002-2005 by Greg Gay & Joel Kronenberg        */
/* Adaptive Technology Resource Centre / University of Toronto  */
/* http://atutor.ca												*/
/*                                                              */
/* This program is free software. You can redistribute it and/or*/
/* modify it under the terms of the GNU General Public License  */
/* as published by the Free Software Foundation.				*/
/****************************************************************/
// $Id: modules.php 4922 2005-06-17 16:29:59Z joel $

define('AT_INCLUDE_PATH', '../../include/');
require (AT_INCLUDE_PATH.'vitals.inc.php');
admin_authenticate(AT_ADMIN_PRIV_ADMIN);

if (isset($_POST['up'])) {
	$up = key($_POST['up']);
	$_new_modules  = array();
	if (isset($_POST['main'])) {
		foreach ($_POST['main'] as $m) {
			if ($m == $up) {
				$last_m = array_pop($_new_modules);
				$_new_modules[] = $m;
				$_new_modules[] = $last_m;
			} else {
				$_new_modules[] = $m;
			}
		}

		$_POST['main'] = $_new_modules;
	}
	if (isset($_POST['home'])) {
		$_new_modules  = array();
		foreach ($_POST['home'] as $m) {
			if ($m == $up) {
				$last_m = array_pop($_new_modules);
				$_new_modules[] = $m;
				$_new_modules[] = $last_m;
			} else {
				$_new_modules[] = $m;
			}
		}
		$_POST['home'] = $_new_modules;
	}

	$_POST['submit'] = TRUE;
} else if (isset($_POST['down'])) {
	$_new_modules  = array();

	$down = key($_POST['down']);

	if (isset($_POST['main'])) {
		foreach ($_POST['main'] as $m) {
			if ($m == $down) {
				$found = TRUE;
				continue;
			}
			$_new_modules[] = $m;
			if ($found) {
				$_new_modules[] = $down;
				$found = FALSE;
			}
		}

		$_POST['main'] = $_new_modules;
	}

	if (isset($_POST['home'])) {
		$_new_modules  = array();
		foreach ($_POST['home'] as $m) {
			if ($m == $down) {
				$found = TRUE;
				continue;
			}
			$_new_modules[] = $m;
			if ($found) {
				$_new_modules[] = $down;
				$found = FALSE;
			}
		}

		$_POST['home'] = $_new_modules;
	}

	$_POST['submit'] = TRUE;
}

if (isset($_POST['submit'])) {
	if (isset($_POST['main'])) {
		$_POST['main'] = array_unique($_POST['main']);
		$main_defaults = implode('|', $_POST['main']);
	} else {
		$main_defaults = '';
	}

	if (isset($_POST['home'])) {
		$_POST['home'] = array_unique($_POST['home']);
		$home_defaults = implode('|', $_POST['home']);
	} else {
		$home_defaults = '';
	}

	if ((strlen($main_defaults) < 256) && (strlen($home_defaults) < 256)) {
		$sql    = "UPDATE ".TABLE_PREFIX."config SET value='$main_defaults' WHERE name='main_defaults'";
		$result = mysql_query($sql, $db);
		$sql    = "UPDATE ".TABLE_PREFIX."config SET value='$home_defaults' WHERE name='home_defaults'";
		$result = mysql_query($sql, $db);
	}
	$msg->addFeedback('SECTIONS_SAVED');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

require(AT_INCLUDE_PATH.'header.inc.php');

$home_defaults = explode('|', $_config['home_defaults']);
$main_defaults = explode('|', $_config['main_defaults']);

$module_list =& $moduleFactory->getModules(AT_MODULE_STATUS_ENABLED);
$keys = array_keys($module_list);

?>
<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
<table class="data static" rules="rows" summary="" style="width:60%;">
<thead>
<tr>
	<th scope="cols"><?php echo _AT('section'); ?></th>
	<th><?php echo _AT('location'); ?></th>
	<th style="width:15px;"><?php echo _AT('order'); ?></th>
</tr>
</thead>
<tfoot>
<tr>
	<td colspan="3"><input type="submit" name="submit" value="<?php echo _AT('save'); ?>" accesskey="s" /></td>
</tr>
</tfoot>
<tbody>
<?php 
foreach ($keys as $dir_name) {
	$module =& $module_list[$dir_name]; 
	$tool = $module->getStudentTools();
	if (!empty($tool)) {
		$student_tools[] = $tool;
	}
}
$count = 0;

//main mods
$_current_modules = $main_defaults; 
$num_main    = count($_current_modules);
//main and home merged
$_current_modules = array_merge($_current_modules, array_diff($home_defaults,$main_defaults) );
$num_modules = count($_current_modules);
//all other mods
$_current_modules = array_merge($_current_modules, array_diff($_modules, $_current_modules));

foreach ($_current_modules as $tool) :
		$count++; 
?>
	<tr>
		<td><?php 
		if (isset($_pages[$tool]['title'])) {
			echo $_pages[$tool]['title'];
		} else {
			echo _AT($_pages[$tool]['title_var']);
		} ?></td>
		<td align="center">
			<?php if (in_array($tool, $main_defaults)): ?>
				<input type="checkbox" name="main[]" value="<?php echo $tool; ?>" id="m<?php echo $tool; ?>" checked="checked" /><label for="m<?php echo $tool; ?>"><?php echo _AT('main_navigation'); ?></label>
			<?php else: ?>
				<input type="checkbox" name="main[]" value="<?php echo $tool; ?>" id="m<?php echo $tool; ?>" /><label for="m<?php echo $tool; ?>"><?php echo _AT('main_navigation'); ?></label>
			<?php endif; ?>

			<?php if (in_array($tool, $home_defaults)): ?>
				<input type="checkbox" name="home[]" value="<?php echo $tool; ?>" id="h<?php echo $tool; ?>" checked="checked" /><label for="h<?php echo $tool; ?>"><?php echo _AT('home'); ?></label>
			<?php else: ?>
				<input type="checkbox" name="home[]" value="<?php echo $tool; ?>" id="h<?php echo $tool; ?>" /><label for="h<?php echo $tool; ?>"><?php echo _AT('home'); ?></label>
			<?php endif; ?>
		</td>
		<td align="right">
			<?php if (($count != $num_modules+1) && ($count > 1)): ?>
				<input type="submit" name="up[<?php echo $tool; ?>]" value="" title="<?php echo _AT('move_up'); ?>" style="color: white; background-color: white; width: 10px; background-image: url('images/move-up.gif'); background-repeat: no-repeat; background-position: 0px 6px; border: 0;" />
			<?php else: ?>
				<img src="images/clr.gif" alt="" width="12" />
			<?php endif; ?>
			<?php if (($count != $num_modules) && ($count < $num_modules)): ?>
				<input type="submit" name="down[<?php echo $tool; ?>]" value="" title="<?php echo _AT('move_down'); ?>" style="background-color: white; width: 10px; background-image: url('images/move-down.gif'); background-repeat: no-repeat; background-position: -3px 6px; border: 0;"/>
			<?php else: ?>
				<img src="images/clr.gif" alt="" width="12" />
			<?php endif; ?>
		</td>
	</tr>
<?php 
endforeach; ?>
</tbody>
</table>
</form>

<?php require(AT_INCLUDE_PATH.'footer.inc.php'); ?>