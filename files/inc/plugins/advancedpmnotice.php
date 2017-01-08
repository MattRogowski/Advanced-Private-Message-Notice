<?php
/**
 * Advanced Private Message Notice 1.0.2

 * Copyright 2017 Matthew Rogowski

 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at

 ** http://www.apache.org/licenses/LICENSE-2.0

 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
**/

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook('global_end', 'advancedpmnotice');

global $templatelist;

if($templatelist)
{
	$templatelist .= ',';
}
$templatelist .= 'advancedpmnotice,advancedpmnotice_pm,advancedpmnotice_footer';

function advancedpmnotice_info()
{
	return array(
		"name" => "Advanced Private Message Notice",
		"description" => "An advanced unread private message notice in the header",
		"website" => "https://github.com/MattRogowski/Advanced-Private-Message-Notice",
		"author" => "Matt Rogowski",
		"authorsite" => "https://matt.rogow.ski",
		"version" => "1.0.2",
		"compatibility" => "18*",
		"codename" => "advancedpmnotice"
	);
}

function advancedpmnotice_activate()
{
	global $db;

	advancedpmnotice_deactivate();

	$settings_group = array(
		"name" => "advancedpmnotice",
		"title" => "Advanced Private Message Notice Settings",
		"description" => "Settings for the Advanced Private Message Notice plugin.",
		"disporder" => "28",
		"isdefault" => 0
	);
	$db->insert_query("settinggroups", $settings_group);
	$gid = $db->insert_id();

	$settings = array();
	$settings[] = array(
		"name" => "advancedpmnotice_count",
		"title" => "Number of PMs to show",
		"description" => "If there are more unread PMs than this setting, a link to view all will be shown",
		"optionscode" => "text",
		"value" => "3"
	);
	$i = 1;
	foreach($settings as $setting)
	{
		$insert = array(
			"name" => $db->escape_string($setting['name']),
			"title" => $db->escape_string($setting['title']),
			"description" => $db->escape_string($setting['description']),
			"optionscode" => $db->escape_string($setting['optionscode']),
			"value" => $db->escape_string($setting['value']),
			"disporder" => intval($i),
			"gid" => intval($gid),
		);
		$db->insert_query("settings", $insert);
		$i++;
	}

	rebuild_settings();

	$templates = array();
	$templates[] = array(
		"title" => "advancedpmnotice",
		"template" => "<table border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\">
	<tr>
		<td class=\"thead\" colspan=\"5\"><a href=\"{\$mybb->settings['bburl']}/private.php\"><strong>{\$lang->advancedpmnotice_header}</strong></a></td>
	</tr>
	<tr>
		<td class=\"tcat\" width=\"20%\" align=\"left\">{\$lang->advancedpmnotice_subject}</td>
		<td class=\"tcat\" width=\"50%\" align=\"left\">{\$lang->advancedpmnotice_message}</td>
		<td class=\"tcat\" width=\"10%\" align=\"center\">{\$lang->advancedpmnotice_from}</td>
		<td class=\"tcat\" width=\"15%\" align=\"center\">{\$lang->advancedpmnotice_date}</td>
		<td class=\"tcat\" width=\"5%\" align=\"center\">{\$lang->advancedpmnotice_controls}</td>
	</tr>
	{\$advancedpmnotice_unread_pms}
	{\$advancedpmnotice_footer}
</table>
<br />"
	);
	$templates[] = array(
		"title" => "advancedpmnotice_pm",
		"template" => "<tr>
	<td class=\"trow1\" width=\"20%\" align=\"left\"><a href=\"{\$mybb->settings['bburl']}/private.php?action=read&amp;pmid={\$pmid}\"><strong>{\$subject}</strong></a></td>
	<td class=\"trow1\" width=\"50%\" align=\"left\">{\$message}</td>
	<td class=\"trow2\" width=\"10%\" align=\"center\">{\$from}</td>
	<td class=\"trow1\" width=\"15%\" align=\"center\">{\$date}</td>
	<td class=\"trow1 postbit_buttons\" width=\"5%\" align=\"center\"><a href=\"{\$mybb->settings['bburl']}/private.php?action=send&amp;pmid={\$pmid}&amp;do=reply\" title=\"{\$lang->reply_title}\" class=\"postbit_reply_pm\"><span>{\$lang->advancedpmnotice_reply}</span></a></td>
</tr>"
	);
	$templates[] = array(
		"title" => "advancedpmnotice_footer",
		"template" => "<tr>
	<td class=\"tfoot\" colspan=\"5\" align=\"right\"><a href=\"{\$mybb->settings['bburl']}/private.php\">{\$lang->advancedpmnotice_view_all}</a></td>
</tr>"
	);

	foreach($templates as $template)
	{
		$insert = array(
			"title" => $db->escape_string($template['title']),
			"template" => $db->escape_string($template['template']),
			"sid" => "-1",
			"version" => "1800",
			"status" => "",
			"dateline" => TIME_NOW
		);

		$db->insert_query("templates", $insert);
	}
}

function advancedpmnotice_deactivate()
{
	global $db;

	$db->delete_query("settinggroups", "name = 'advancedpmnotice'");

	$settings = array(
		"advancedpmnotice_count"
	);
	$settings = "'" . implode("','", $settings) . "'";
	$db->delete_query("settings", "name IN ({$settings})");

	rebuild_settings();

	$db->delete_query("templates", "title IN ('advancedpmnotice','advancedpmnotice_pm','advancedpmnotice_footer')");
}

function advancedpmnotice()
{
	global $mybb, $db, $lang, $templates, $theme, $parser, $header, $pm_notice;

	if($pm_notice == '')
	{
		return;
	}
	elseif($pm_notice && THIS_SCRIPT == 'private.php')
	{
		$header = str_replace($pm_notice, '', $header);
		return;
	}

	$lang->load('advancedpmnotice');

	if(!$parser)
	{
		require_once MYBB_ROOT.'inc/class_parser.php';
		$parser = new postParser;
	}

	$limit = $mybb->settings['advancedpmnotice_count'];
	if(!is_numeric($limit) || $limit <= 0)
	{
		$limit = 3;
	}

	$query = $db->query("
		SELECT pm.subject, pm.message, pm.pmid,pm.dateline, fu.username AS fromusername, fu.uid AS fromuid, fu.usergroup as fromusergroup, fu.displaygroup as fromdisplaygroup
		FROM ".TABLE_PREFIX."privatemessages pm
		LEFT JOIN ".TABLE_PREFIX."users fu on (fu.uid=pm.fromid)
		WHERE pm.folder = '1' AND pm.uid = '{$mybb->user['uid']}' AND pm.status = '0'
		ORDER BY pm.dateline DESC
	");

	$pms = array();
	while($pm = $db->fetch_array($query))
	{
		$pms[] = $pm;
	}

	$advancedpmnotice_unread_pms = '';
	for($i = 0; $i < $limit; $i++)
	{
		$pm = $pms[$i];

		if(!$pm)
		{
			continue;
		}

		$pmid = $pm['pmid'];

		$subject = htmlspecialchars_uni($parser->parse_badwords($pm['subject']));
		$message = $parser->text_parse_message($pm['message'], array('filter_badwords' => true));
		if(str_word_count($message, 0) > 20)
		{
			$words = str_word_count($message, 2);
			$pos = array_keys($words);
			$message = trim(substr($message, 0, $pos[20])).'...';
		}

		if($pm['fromuid'] == 0)
		{
			$from = $lang->mybb_engine;
		}
		else
		{
			$from = build_profile_link(format_name($pm['fromusername'], $pm['fromusergroup'], $pm['fromdisplaygroup']), $pm['fromuid']);
		}

		$date = my_date('relative', $pm['dateline']);

		eval("\$advancedpmnotice_unread_pms .= \"".$templates->get('advancedpmnotice_pm')."\";");
	}

	if(count($pms) > $limit)
	{
		$lang->advancedpmnotice_view_all = $lang->sprintf($lang->advancedpmnotice_view_all, count($pms));
		eval("\$advancedpmnotice_footer = \"".$templates->get('advancedpmnotice_footer')."\";");
	}

	eval("\$advancedpmnotice = \"".$templates->get('advancedpmnotice')."\";");

	$header = str_replace($pm_notice, $advancedpmnotice, $header);
}
?>
