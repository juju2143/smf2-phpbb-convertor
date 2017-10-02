<?php
/** 
*
* @package install
* @version $Id: functions_smf20RCx.php,v 1.0.2 2010/03/06 Dicky
* @copyright (c) 2006 phpBB Group
* @copyright (c) 2007 Andy Miller
* @copyright Some Changes (c) 2007 A_Jelly_Doughnut
* @copyright Some Changes (c) 2008 Dicky
* @copyright Updated to work with phpBB 3.2 (c) 2017 Juju
* @license http://opensource.org/licenses/gpl-license.php GNU Public License 
*
*/

/**
* Helper functions for smf 2.0.RC2 to phpBB 3.0.x conversion
*/

/**
* NOTES: Dicky
# Uncommented 'continue' in line 473
# May need to comment 'continue' in line 473
*/

/**
* Set forum flags - only prune old polls by default
*/
function forum_flags()
{
	// Set forum flags
	$forum_flags = 0;

	// FORUM_FLAG_LINK_TRACK
	$forum_flags += 0;

	// FORUM_FLAG_PRUNE_POLL
	$forum_flags += FORUM_FLAG_PRUNE_POLL;

	// FORUM_FLAG_PRUNE_ANNOUNCE
	$forum_flags += 0;

	// FORUM_FLAG_PRUNE_STICKY
	$forum_flags += 0;

	// FORUM_FLAG_ACTIVE_TOPICS
	$forum_flags += 0;

	// FORUM_FLAG_POST_REVIEW
	$forum_flags += FORUM_FLAG_POST_REVIEW;

	return $forum_flags;
}

/**
* Function for recoding text with the default language
* SMF doesn't store the user's preferred language in the database, so we use the
* board's default language in all cases
*
* @param string $text text to recode to utf8
*/
function smf_set_encoding($text)
{
	global $convert, $phpEx, $phpbb_root_path, $src_db;

	// use static variables so these lookups only happen once per 
	// page load, rather than every time smf_set_encoding() is called
	static $db_character_set = '';
	static $get_lang = '';
	static $encoding = '';

	if ($db_character_set === '')
	{
		// grab default language and UTF-8 status from SMF
		$smf_settings = extract_variables_from_file($convert->options['forum_path'] . '/Settings.'.$phpEx);
		$db_character_set = isset($smf_settings['db_character_set']) ? $smf_settings['db_character_set'] : false;
		$get_lang = trim($smf_settings['language']);
		unset($smf_settings);

		if ($db_character_set == 'utf8')
		{
			$db_collation = '';
			// since the post text is the most important text on the board, we check it.
			// We assume all columns have the same charset & collation
			$sql = "SHOW FULL COLUMNS FROM {$convert->src_table_prefix}messages";
			$result = $src_db->sql_query($sql);

			while ($row = $src_db->sql_fetchrow($result))
			{
				if ($row['Field'] == 'body')
				{
					$db_collation = $row['Collation'];
				}
			}

			// now do a lookup to see what charset this collation belongs to
			$sql = "SHOW COLLATION LIKE '$db_collation'";
			$result = $src_db->sql_query($sql);

			$db_charset = $src_db->sql_fetchfield('Charset');

			if ($db_charset != 'utf8')
			{
				// the characters do need to be recoded.  This variable could
				// be set to anything as long as it is not UTF8.
				$db_character_set = false;
			}
		}
	}

	if (!class_exists('utf_normalizer'))
	{
		include($phpbb_root_path . 'includes/utf/utf_normalizer.' . $phpEx);
	}

	if ($db_character_set == 'utf8')
	{
		// we are using UTF-8 in SMF.
		// normalize everything (to be sure)
		$text = utf8_decode_ncr($text);
		utf8_normalize_nfc($text);

		return $text;
	}

	if ($encoding === '')
	{
		$filename = $convert->options['forum_path'] . '/Themes/default/languages/index.' . $get_lang . '.' . $phpEx;

		if (!file_exists($filename))
		{
			// we have nothing better to go on than the DB Character set
			$encoding = $db_character_set;
		}

		if (!isset($lang_enc_array[$get_lang]))
		{
			include($convert->options['forum_path'] . '/Themes/default/languages/index.' . $get_lang . '.' . $phpEx);
			$encoding = $txt['lang_character_set'];
			unset($txt);
		}
	}

	// make sure everything is normalized and entities are turned into UTF-8 characters
	$text = utf8_decode_ncr(utf8_recode($text, $encoding));
	utf8_normalize_nfc($text);

	return $text;
}

/**
* Return correct user id value
* If there is a Member ID 1 in SMF, it will become MAX(id_member) + 1 in phpBB3
*/
function smf_user_id($user_id)
{
	global $config, $convert, $src_db, $db;

	// If the old user id is 0, it is the anonymous user...
	if ($user_id == 0)
	{
		return ANONYMOUS;
	}

	if (!isset($config['increment_user_id']))
	{
		// Now let us set a temporary config variable for user id incrementing
		$sql = "SELECT id_member
			FROM {$convert->src_table_prefix}members
			WHERE id_member = 1";
		$result = $src_db->sql_query($sql);
		$id = (int) $src_db->sql_fetchfield('id_member');
		$src_db->sql_freeresult($result);

		// If there is a user id 1, we need to increment user ids. :/
		if ($id === 1)
		{
			// Try to get the maximum user id possible...
			//$sql = "SELECT MAX(id_member) AS max_id_member
			//	FROM {$convert->src_table_prefix}members";
			// Or maybe the next available id?
			$sql = "SELECT MIN(t1.id_member+1) AS max_id_member
				FROM {$convert->src_table_prefix}members t1
				LEFT JOIN {$convert->src_table_prefix}members t2
				ON t1.id_member+1=t2.id_member
				WHERE t2.id_member IS NULL";
			$result = $src_db->sql_query($sql);
			$max_user_id = (int) $src_db->sql_fetchfield('max_id_member');
			$src_db->sql_freeresult($result);

			set_config('increment_user_id', ($max_user_id), true);
			$config['increment_user_id'] = $max_user_id;
		}
		else
		{
			set_config('increment_user_id', 0, true);
			$config['increment_user_id'] = 0;
		}
	}

	if (!empty($config['increment_user_id']) && $user_id == 1)
	{
		return $config['increment_user_id'];
	}
	return (int) $user_id;
}

/**
* Convert authentication
* user, group and forum table has to be filled in order to work
*/
function smf_convert_authentication($mode)
{
	global $db, $src_db, $same_db, $convert, $user, $config, $cache;

	if ($mode == 'start')
	{
		$db->sql_query($convert->truncate_statement . ACL_USERS_TABLE);
		$db->sql_query($convert->truncate_statement . ACL_GROUPS_TABLE);

		// All members of the SMF Admins group become founders in phpBB3.

		// Grab user ids of users who are in the Admin group (id 1)
		$sql = "SELECT id_member, id_group, additional_groups FROM {$convert->src_table_prefix}members
			WHERE (FIND_IN_SET(1, additional_groups)
				OR id_group = 1)
			ORDER BY date_registered ASC";
		$result = $src_db->sql_query($sql);

		$smf_admin_ary = array();
		while ($row = $src_db->sql_fetchrow($result))
		{
			if (preg_match('#(^1,|,1,|,1$)#', $row['additional_groups']) || $row['id_group'] == 1 || $row['additional_groups'] == 1)
			{
				$smf_admin_ary[] = smf_user_id($row['id_member']);
			}
		}
		$src_db->sql_freeresult($result);

		// actually give the founder status
		$sql = 'UPDATE ' . USERS_TABLE . ' SET user_type = ' . USER_FOUNDER . '
			WHERE ' . $db->sql_in_set('user_id', $smf_admin_ary);
		$db->sql_query($sql);
		unset($smf_admin_ary);
	}

	// algorithm for converting permissions
	// SMF's AddDeny = 1 => ACL_YES, AddDeny = 0 => ACL_NEVER

	// groups who are listed in smf_boards.member_groups have f_view permission & f_read

	if ($convert->mysql_convert && $same_db)
	{
		$src_db->sql_query("SET NAMES 'binary'");
	}

	$sql = "SELECT * FROM {$convert->src_table_prefix}board_permissions";
	$result = $src_db->sql_query($sql);

	// this could be optimized for memory
	$board_permissions = array();
	while ($row = $src_db->sql_fetchrow($result))
	{
		$board_permissions[$row['id_group']][$row['id_profile']][] = $row;
	}
	$src_db->sql_freeresult($result);

	if ($convert->mysql_convert && $same_db)
	{
		$src_db->sql_query("SET NAMES 'utf8'");
	}

	// Add Forum Access List
	// Two arrays in this form
	// 'SMF_perm'	=> 'phpBB3 perm(s)'
	$auth_map_local = array(
		'post_new'			=> array('f_post', 'f_bbcode', 'f_smilies', 'f_img', 'f_sigs', 'f_postcount', 'f_print'),
		'post_reply_any'	=> array('f_reply', 'f_bump'),
		'delete_own'		=> 'f_delete',
		'poll_add'			=> 'f_poll',
		'poll_vote'			=> 'f_vote',
		'announce_topic'	=> 'f_announce',
		'send_topic'		=> 'f_email',
		'make_sticky'		=> 'f_sticky',
		'lock_own'			=> 'f_user_lock',
		'post_attachment'	=> 'f_attach',
		'view_attachments'	=> 'f_download',
		'search_posts'		=> 'f_search',
		'report_any'		=> 'f_report',
		'modify_own'		=> 'f_edit',
		'mark_any_notify'	=> 'f_subscribe',
	);

	$auth_map_global = array(
		'pm_read'		=> array('u_readpm', 'u_pmdownload', 'u_pmdelete'),
		'pm_send'		=> array('u_sendpm', 'u_pmattach', 'u_pmbbcode', 'u_pmedit'),
		// there are several SMF permissions that would equate to moderator or administrator permissions
		// in phpBB.  these are not converted to avoid any possible permissions escalations.
		'profile_view'	=> 'u_viewprofile',
		'view_attachments'	=> 'u_download',
		'profile_server_avatar'	=> 'u_avatar',
		'profile_upload_avatar'	=> 'u_avatar',
		'profile_remote_avatar'	=> 'u_avatar',
	);


	if ($mode == 'start')
	{
		// add the anonymous user to the GUESTS group, and everyone else to the REGISTERED group
		user_group_auth('guests', 'SELECT user_id, {GUESTS} FROM ' . USERS_TABLE . ' WHERE user_id = ' . ANONYMOUS, false);
		user_group_auth('registered', 'SELECT user_id, {REGISTERED} FROM ' . USERS_TABLE . ' WHERE user_id <> ' . ANONYMOUS, false);

		/** 
		* Selecting from old table
		* Since SMF uses comma-separated lists, we use FIND_IN_SET().  It is MySQL
		* only syntax, but that is acceptable because SMF only supports MySQL.
		*
		* If the increment user ID is used, we ensure we get the proper user ID of
		* the user who formerly had user_id 1.
		**/
		if (!empty($config['increment_user_id']))
		{
			// admin permissions here
			$auth_sql = 'SELECT id_member as user_id, {ADMINISTRATORS} FROM ' . $convert->src_table_prefix . 'members 
				WHERE (id_group = 1 OR FIND_IN_SET(1, additional_groups)) AND id_member <> 1';
			user_group_auth('administrators', $auth_sql, true);

			$auth_sql = 'SELECT ' . $config['increment_user_id'] . ' as user_id, {ADMINISTRATORS} FROM ' . $convert->src_table_prefix . 'members 
				WHERE (id_group = 1 OR FIND_IN_SET(1, additional_groups)) 
				AND id_member = 1';
			user_group_auth('administrators', $auth_sql, true);

			// we give administrators global moderator permissions too
			$auth_sql = 'SELECT id_member as user_id, {GLOBAL_MODERATORS} FROM ' . $convert->src_table_prefix . 'members 
				WHERE ((id_group = 1 OR FIND_IN_SET(1, additional_groups)) 
					OR (id_group = 2 OR FIND_IN_SET(2, additional_groups))) 
				AND id_member <> 1';
			user_group_auth('global_moderators', $auth_sql, true);

			$auth_sql = 'SELECT ' . $config['increment_user_id'] . ' as user_id, {GLOBAL_MODERATORS} FROM ' . $convert->src_table_prefix . 'members 
				WHERE ((id_group = 1 OR FIND_IN_SET(1, additional_groups)) 
					OR (id_group = 2 OR FIND_IN_SET(2, additional_groups))) 
				AND id_member = 1';
			user_group_auth('global_moderators', $auth_sql, true);
		}
		else
		{
			// administrators
			$auth_sql = 'SELECT id_member as user_id, {ADMINISTRATORS} FROM ' . $convert->src_table_prefix . 'members 
				WHERE (id_group = 1 OR FIND_IN_SET(1, additional_groups))
					AND id_member <> 1';
			user_group_auth('administrators', $auth_sql, true);

			// global moderators
			$auth_sql = 'SELECT id_member as user_id, {GLOBAL_MODERATORS} FROM ' . $convert->src_table_prefix . 'members 
				WHERE ((id_group = 1 OR FIND_IN_SET(1, additional_groups)) 
					OR (id_group = 2 OR FIND_IN_SET(2, additional_groups)))
				AND id_member <> 1';
			user_group_auth('global_moderators', $auth_sql, true);
		}
	}
	// this section handles local forum permissions.
	else if ($mode == 'first')
	{
		// Grab forum auth information
		$sql = "SELECT member_groups, id_board, id_profile
			FROM {$convert->src_table_prefix}boards";
		$result = $src_db->sql_query($sql);

		$board_data = array();
		while ($row = $src_db->sql_fetchrow($result))
		{
			$board_data[$row['id_board']] = $row;
		}
		$src_db->sql_freeresult($result);

		$forum_parents = array();
		// we need a bit of data from the phpBB forums table.
		// note this may be redundant...step 3 seems to grant permissions to categories
		$sql = 'SELECT forum_id, parent_id FROM ' . FORUMS_TABLE;
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$forum_parents[$row['forum_id']] = $row['parent_id'];
		}

		// Grab the permissions defined for the FORUM_READONLY role so we can use them as a base later on.
		// We can't use the role itself because phpBB doesn't allow roles and individual permissions to be intermixed.
		$sql = 'SELECT rd.auth_option_id, rd.auth_setting
			FROM ' . ACL_ROLES_DATA_TABLE . ' rd, ' . ACL_ROLES_TABLE . ' r
			WHERE rd.role_id = r.role_id
				AND role_name = "ROLE_FORUM_READONLY"';
		$result = $db->sql_query($sql);
		$readonly_acl = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);

		// Now we assign forum permissions in this loop
		foreach ($board_data as $board_id => $forum)
		{
			$new_forum_id = (int) $board_id;

			// Administrators & global mods have full access to all forums whatever happens
			mass_auth('group_role', $new_forum_id, 'administrators', 'FORUM_FULL');
			mass_auth('group_role', $new_forum_id, 'global_moderators', 'FORUM_FULL');

			$forum_viewable_groups = explode(',', $forum['member_groups']);

			foreach ($forum_viewable_groups as $group_id)
			{
				// SMF has only a virtual guests group.  Make it use phpBB's.
				$use_group_id = (int) $group_id;
				if ($group_id == '-1')
				{
					$use_group_id = get_group_id('guests');
				}
				// ditto for registered users
				if ($group_id == '0')
				{
					$use_group_id = get_group_id('registered');
				}

				// If the group doesn't have any permissions defined for the profile, then we just give them the FORUM_READONLY role and we're done.
				if (empty($board_permissions[$group_id][$forum['id_profile']]))
				{
					mass_auth('group_role', $new_forum_id, $use_group_id, 'FORUM_READONLY');
					// We can read this forum's parent as well...
					mass_auth('group_role', $forum_parents[$new_forum_id], $use_group_id, 'FORUM_READONLY');

					continue;
				}

				// Set up the permissions defined for the FORUM_READONLY role.
				// As previously stated, we can't simply use the role because that will simply get undone once we add permissions.
				foreach ($readonly_acl as $index => $acl)
				{
					$readonly_acl[$index] = array_merge($acl, array(
						'forum_id'	=> $new_forum_id,
						'group_id'	=> $use_group_id
					));
				}
				$sql = $db->sql_multi_insert(ACL_GROUPS_TABLE, $readonly_acl);

				$group_permissions = &$board_permissions[$group_id][$forum['id_profile']];

				foreach ($group_permissions as $permission_entry)
				{
					if ($permission_entry['permission'] == 'moderate_board' && $permission_entry['add_deny'])
					{
						// User is a board moderator.  Assign the phpBB standard board mod role
						// and ensure the user can actually read the forum he or she is assigned
						mass_auth('group_role', $new_forum_id, $use_group_id, 'MOD_STANDARD');
						mass_auth('group_role', $new_forum_id, $use_group_id, 'FORUM_STANDARD');						
					}

					// if we don't map this permission to phpBB, stop this iteration.
					// we do not map things that require moderator permission, for instance
					if (!isset($auth_map_local[$permission_entry['permission']]))
					{
						continue;
					}

					// All SMF permissions are group-based.
					mass_auth('group', $new_forum_id, $use_group_id, $auth_map_local[$permission_entry['permission']], $permission_entry['add_deny']);
				}
			}
		}
	}
	else if ($mode == 'second')
	{
		// Assign permission roles and other default permissions

		// guests having u_download and u_search ability
		$db->sql_query('INSERT INTO ' . ACL_GROUPS_TABLE . ' (group_id, forum_id, auth_option_id, auth_role_id, auth_setting) SELECT ' . get_group_id('guests') . ', 0, auth_option_id, 0, 1 FROM ' . ACL_OPTIONS_TABLE . " WHERE auth_option IN ('u_', 'u_download', 'u_search')");

		// administrators/global mods having full user features
		mass_auth('group_role', 0, 'administrators', 'USER_FULL');
		mass_auth('group_role', 0, 'global_moderators', 'USER_FULL');

		// By default all converted administrators are given full access
		mass_auth('group_role', 0, 'administrators', 'ADMIN_FULL');

		// All registered users are assigned the standard user role
		mass_auth('group_role', 0, 'registered', 'USER_STANDARD');
		mass_auth('group_role', 0, 'registered_coppa', 'USER_STANDARD');

		// Instead of administrators being global moderators we give the MOD_FULL role to global mods (admins already assigned to this group)
		mass_auth('group_role', 0, 'global_moderators', 'MOD_FULL');

		// now that a basic set of permissions has been established, we can yank from certain users
		// There are only a couple of user permissions that we bring over.
		// Specifically, avatars and private messaging
		$sql = "SELECT * FROM {$convert->src_table_prefix}permissions
			WHERE add_deny = 0
				AND (permission LIKE 'profile_%'
					OR permission LIKE 'pm_%')";
		$result = $src_db->sql_query($sql);

		while ($row = $src_db->sql_fetchrow($result))
		{
			if (isset($auth_map_global[$row['permission']]))
			{
				// if the group is denied this permission in SMF, note it.
				mass_auth('group', 0, $row['id_group'], $auth_map_global[$row['permission']], ACL_NO);
			}
		}
	}
	else if ($mode == 'third')
	{
		// We grant everyone readonly access to the categories to ensure that the forums are visible
		$sql = 'SELECT forum_id, forum_name, parent_id, left_id, right_id
			FROM ' . FORUMS_TABLE . '
			ORDER BY left_id ASC';
		$result = $db->sql_query($sql);

		$parent_forums = $forums = array();
		while ($row = $db->sql_fetchrow($result))
		{
			if ($row['parent_id'] == 0)
			{
				mass_auth('group_role', $row['forum_id'], 'administrators', 'FORUM_FULL');
				mass_auth('group_role', $row['forum_id'], 'global_moderators', 'FORUM_FULL');
				$parent_forums[] = $row;
			}
			else
			{
				$forums[] = $row;
			}
		}
		$db->sql_freeresult($result);

		global $auth;

		// Let us see which groups have access to these forums...
		foreach ($parent_forums as $row)
		{
			// Get the children
			$branch = $forum_ids = array();

			foreach ($forums as $key => $_row)
			{
				if ($_row['left_id'] > $row['left_id'] && $_row['left_id'] < $row['right_id'])
				{
					$branch[] = $_row;
					$forum_ids[] = $_row['forum_id'];
					continue;
				}
			}

			if (sizeof($forum_ids))
			{
				// Now make sure the user is able to read these forums
				$hold_ary = $auth->acl_group_raw_data(false, 'f_list', $forum_ids);

				if (empty($hold_ary))
				{
					continue;
				}

				foreach ($hold_ary as $g_id => $f_id_ary)
				{
					$set_group = false;

					foreach ($f_id_ary as $f_id => $auth_ary)
					{
						foreach ($auth_ary as $auth_option => $setting)
						{
							if ($setting == ACL_YES)
							{
								$set_group = true;
								break 2;
							}
						}
					}

					if ($set_group)
					{
						mass_auth('group', $row['forum_id'], $g_id, 'f_list', ACL_YES);
					}
				}
			}
		}
	}
}

function create_userconv_table()
{
	global $db, $src_db, $convert, $table_prefix, $user, $lang;

	$map_dbms = '';
	switch ($db->sql_layer)
	{
		case 'mysql':
			$map_dbms = 'mysql_40';
		break;

		case 'mysql4':
			if (version_compare($db->sql_server_info(true), '4.1.3', '>='))
			{
				$map_dbms = 'mysql_41';
			}
			else
			{
				$map_dbms = 'mysql_40';
			}
		break;

		case 'mysqli':
			$map_dbms = 'mysql_41';
		break;

		case 'mssql':
		case 'mssql_odbc':
			$map_dbms = 'mssql';
		break;

		default:
			$map_dbms = $db->sql_layer;
		break;
	}

	// create a temporary table in which we store the clean usernames
	$drop_sql = 'DROP TABLE ' . USERCONV_TABLE;
	switch ($map_dbms)
	{
		case 'firebird':
			$create_sql = 'CREATE TABLE ' . USERCONV_TABLE . ' (
				user_id INTEGER NOT NULL,
				username_clean VARCHAR(255) CHARACTER SET UTF8 DEFAULT \'\' NOT NULL COLLATE UNICODE
			)';
		break;

		case 'mssql':
			$create_sql = 'CREATE TABLE [' . USERCONV_TABLE . '] (
				[user_id] [int] NOT NULL ,
				[username_clean] [varchar] (255) DEFAULT (\'\') NOT NULL
			)';
		break;

		case 'mysql_40':
			$create_sql = 'CREATE TABLE ' . USERCONV_TABLE . ' (
				user_id mediumint(8) NOT NULL,
				username_clean blob NOT NULL
			)';
		break;

		case 'mysql_41':
			$create_sql = 'CREATE TABLE ' . USERCONV_TABLE . ' (
				user_id mediumint(8) NOT NULL,
				username_clean varchar(255) DEFAULT \'\' NOT NULL
			) CHARACTER SET `utf8` COLLATE `utf8_bin`';
		break;

		case 'oracle':
			$create_sql = 'CREATE TABLE ' . USERCONV_TABLE . ' (
				user_id number(8) NOT NULL,
				username_clean varchar2(255) DEFAULT \'\'
			)';
		break;

		case 'postgres':
			$create_sql = 'CREATE TABLE ' . USERCONV_TABLE . ' (
				user_id INT4 DEFAULT \'0\',
				username_clean varchar_ci DEFAULT \'\' NOT NULL
			)';
		break;

		case 'sqlite':
			$create_sql = 'CREATE TABLE ' . USERCONV_TABLE . ' (
				user_id INTEGER NOT NULL DEFAULT \'0\',
				username_clean varchar(255) NOT NULL DEFAULT \'\'
			)';
		break;
	}

	$db->sql_return_on_error(true);
	$db->sql_query($drop_sql);
	$db->sql_return_on_error(false);
	$db->sql_query($create_sql);
}

/**
* Checks whether there are any usernames on the old board that would map to the same
* username_clean on phpBB3. Prints out a list if any exist and exits.
*/
function smf_check_username_collisions()
{
	global $db, $src_db, $convert, $table_prefix, $user, $lang;

	// now find the clean version of the usernames that collide
	$sql = 'SELECT username_clean
		FROM ' . USERCONV_TABLE .'
		GROUP BY username_clean
		HAVING COUNT(user_id) > 1';
	$result = $db->sql_query($sql);

	$colliding_names = array();
	while ($row = $db->sql_fetchrow($result))
	{
		$colliding_names[] = $row['username_clean'];
	}
	$db->sql_freeresult($result);

	// there was at least one collision, the admin will have to solve it before conversion can continue
	if (sizeof($colliding_names))
	{
		$sql = 'SELECT user_id, username_clean
			FROM ' . USERCONV_TABLE . '
			WHERE ' . $db->sql_in_set('username_clean', $colliding_names);
		$result = $db->sql_query($sql);
		unset($colliding_names);

		$colliding_user_ids = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$colliding_user_ids[(int) $row['user_id']] = $row['username_clean'];
		}
		$db->sql_freeresult($result);

		$sql = 'SELECT member_name, id_member, posts
			FROM ' . $convert->src_table_prefix . 'members
			WHERE ' . $src_db->sql_in_set('id_member', array_keys($colliding_user_ids));
		$result = $src_db->sql_query($sql);

		$colliding_users = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$row['user_id'] = (int) $row['id_member'];
			if (isset($colliding_user_ids[$row['user_id']]))
			{
				$colliding_users[$colliding_user_ids[$row['user_id']]][] = $row;
			}
		}
		$db->sql_freeresult($result);
		unset($colliding_user_ids);

		$list = '';
		foreach ($colliding_users as $username_clean => $users)
		{
			$list .= sprintf($user->lang['COLLIDING_CLEAN_USERNAME'], $username_clean) . "<br />\n";
			foreach ($users as $i => $row)
			{
				$list .= sprintf($user->lang['COLLIDING_USER'], $row['user_id'], smf_set_encoding($row['username']), $row['user_posts']) . "<br />\n";
			}
		}

		$lang['INST_ERR_FATAL'] = $user->lang['CONV_ERR_FATAL'];
		$convert->p_master->error('<span style="color:red">' . $user->lang['COLLIDING_USERNAMES_FOUND'] . '</span></b><br /><br />' . $list . '<b>', __LINE__, __FILE__);
	}

	$drop_sql = 'DROP TABLE ' . USERCONV_TABLE;
	$db->sql_query($drop_sql);
}

// smf specific functions

/**
* Get default SMF theme
*/
function smf_get_default_theme()
{
	global $db, $src_db, $convert, $config;

	$sql = 'SELECT value  
		FROM ' . $convert->src_table_prefix . 'themes 
		WHERE ID_THEME = ' . $convert->convertor['theme_default'] . ' AND variable = \'theme_dir\'';

	$result = $src_db->sql_query($sql);
	$theme_name = $src_db->sql_fetchfield('value');

	return $theme_name;
}

/**
* Get path to the default theme's images directory
*/
function smf_default_theme_images_path($include_forum_path = true)
{
	global $db, $convert, $src_db;
	static $abs_path;

	if (!$abs_path)
	{
		$theme_path = smf_get_default_theme();
		$theme_path = 'Themes/' . smf_absolute_to_path($theme_path);

		// Get path
		$sql = 'SELECT value  
			FROM ' . $convert->src_table_prefix . 'themes 
			WHERE id_theme = ' . $db->sql_escape($convert->convertor['theme_default']) . ' AND variable = "images_url"';
		$result = $src_db->sql_query($sql);
		$images_path = $src_db->sql_fetchfield('value');
		$abs_path = $theme_path . smf_absolute_to_path($images_path);
	}
	return (($include_forum_path) ? $convert->options['forum_path'] . '/' : '') . $abs_path;
}

// smf specific functions

/**
* Insert/Convert forums
*/
function smf_insert_forums()
{
	global $db, $src_db, $same_db, $convert, $user, $config;

	$db->sql_query($convert->truncate_statement . FORUMS_TABLE);

	// Determine the highest id used within the old forums table (we add the categories after the forum ids)
	$sql = 'SELECT MAX(id_board) AS max_forum_id
		FROM ' . $convert->src_table_prefix . 'boards';
	$result = $src_db->sql_query($sql);
	$max_forum_id = (int) $src_db->sql_fetchfield('max_forum_id');
	$src_db->sql_freeresult($result);

	$max_forum_id += 1;

	// Insert categories
	$sql = 'SELECT id_cat, name
		FROM ' . $convert->src_table_prefix . 'categories
		ORDER BY cat_order';

	if ($convert->mysql_convert && $same_db)
	{
		$src_db->sql_query("SET NAMES 'binary'");
	}

	$result = $src_db->sql_query($sql);

	if ($convert->mysql_convert && $same_db)
	{
		$src_db->sql_query("SET NAMES 'utf8'");
	}

	switch ($db->sql_layer)
	{
		case 'mssql':
		case 'mssql_odbc':
			$db->sql_query('SET IDENTITY_INSERT ' . FORUMS_TABLE . ' ON');
		break;
	}

	$cats_added = array();
	while ($row = $src_db->sql_fetchrow($result))
	{
		$sql_ary = array(
			'forum_id'		=> $max_forum_id,
			'forum_name'	=> ($row['name']) ? utf8_htmlspecialchars(smf_set_encoding($row['name'])) : $user->lang['CATEGORY'],
			'parent_id'		=> 0,
			'forum_parents'	=> '',
			'forum_desc'	=> '',
			'forum_type'	=> FORUM_CAT,
			'forum_status'	=> ITEM_UNLOCKED,
			'forum_rules'	=> '',
		);

		$sql = 'SELECT MAX(right_id) AS right_id
			FROM ' . FORUMS_TABLE;
		$_result = $db->sql_query($sql);
		$cat_row = $db->sql_fetchrow($_result);
		$db->sql_freeresult($_result);

		$sql_ary['left_id'] = $cat_row['right_id'] + 1;
		$sql_ary['right_id'] = $cat_row['right_id'] + 2;

		$sql = 'INSERT INTO ' . FORUMS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary);
		$db->sql_query($sql);

		$cats_added[$row['id_cat']] = $max_forum_id;
		$max_forum_id++;
	}
	$src_db->sql_freeresult($result);

	// There may be installations having forums with non-existant category ids.
	// We try to catch them and add them to an "unknown" category instead of leaving them out.
	$sql = 'SELECT id_cat
		FROM ' . $convert->src_table_prefix . 'boards
		GROUP BY id_cat';
	$result = $src_db->sql_query($sql);

	$unknown_cat_id = false;
	while ($row = $src_db->sql_fetchrow($result))
	{
		// Catch those categories not been added before
		if (!isset($cats_added[$row['id_cat']]))
		{
			$unknown_cat_id = true;
		}
	}
	$src_db->sql_freeresult($result);

	// Is there at least one category not known?
	if ($unknown_cat_id === true)
	{
		$unknown_cat_id = 'ghost';

		$sql_ary = array(
			'forum_id'		=> $max_forum_id,
			'forum_name'	=> $user->lang['CATEGORY'],
			'parent_id'		=> 0,
			'forum_parents'	=> '',
			'forum_desc'	=> '',
			'forum_type'	=> FORUM_CAT,
			'forum_status'	=> ITEM_UNLOCKED,
			'forum_rules'	=> '',
		);

		$sql = 'SELECT MAX(right_id) AS right_id
			FROM ' . FORUMS_TABLE;
		$_result = $db->sql_query($sql);
		$cat_row = $db->sql_fetchrow($_result);
		$db->sql_freeresult($_result);

		$sql_ary['left_id'] = $cat_row['right_id'] + 1;
		$sql_ary['right_id'] = $cat_row['right_id'] + 2;

		$sql = 'INSERT INTO ' . FORUMS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary);
		$db->sql_query($sql);

		$cats_added[$unknown_cat_id] = $max_forum_id;
		$max_forum_id++;
	}

	// Now insert the forums
	$sql = 'SELECT f.id_board, f.id_parent, f.name, f.id_cat, f.description, f.board_order, f.child_level,f.redirect FROM ' . $convert->src_table_prefix . 'boards f
		GROUP BY f.board_order, f.id_parent, f.id_cat, f.child_level, f.id_board, f.description
      	ORDER BY f.board_order';

	if ($convert->mysql_convert && $same_db)
	{
		$src_db->sql_query("SET NAMES 'binary'");
	}

	$result = $src_db->sql_query($sql);

	if ($convert->mysql_convert && $same_db)
	{
		$src_db->sql_query("SET NAMES 'utf8'");
	}

	while ($row = $src_db->sql_fetchrow($result))
	{
		// Some might have forums here with an id not being "possible"...
		// To be somewhat friendly we "change" the category id for those to a previously created ghost category
		if (!isset($cats_added[$row['id_cat']]) && $unknown_cat_id !== false)
		{
			$row['id_cat'] = $unknown_cat_id;
		}

		if (!isset($cats_added[$row['id_cat']]))
		{
			continue;
		}

		// Define the new forums sql ary
		$sql_ary = array(
			'forum_id'			=> (int) $row['id_board'],
			'forum_name'		=> utf8_htmlspecialchars(smf_set_encoding($row['name'])),
			'parent_id'			=> ($row['child_level']) ? (int) $row['id_parent'] : $cats_added[$row['id_cat']],
			'forum_parents'		=> '',
			'forum_desc'		=> utf8_htmlspecialchars(smf_set_encoding($row['description'])),
			'forum_type'		=> ($row['redirect']) ? FORUM_LINK : FORUM_POST,
//HUH??			'forum_status'		=> is_item_locked($row['forum_status']),
/*			'enable_prune'		=> $row['prune_enable'],
			'prune_next'		=> null_to_zero($row['prune_next']),
			'prune_days'		=> null_to_zero($row['prune_days']),
			'prune_viewed'		=> 0,
			'prune_freq'		=> null_to_zero($row['prune_freq']), */

			'forum_flags'		=> forum_flags(),

			// Default values
			'forum_desc_bitfield'		=> '',
			'forum_desc_options'		=> 7,
			'forum_desc_uid'			=> '',
			'forum_link'				=> utf8_htmlspecialchars($row['redirect']),
			'forum_password'			=> '',
			'forum_style'				=> 0,
			'forum_image'				=> '',
			'forum_rules'				=> '',
			'forum_rules_link'			=> '',
			'forum_rules_bitfield'		=> '',
			'forum_rules_options'		=> 7,
			'forum_rules_uid'			=> '',
			'forum_topics_per_page'		=> 0,
			'forum_posts_approved'				=> 0,
			'forum_posts_unapproved'				=> 0,
			'forum_posts_softdeleted'				=> 0,
			'forum_topics_approved'				=> 0,
			'forum_topics_unapproved'			=> 0,
			'forum_topics_softdeleted'			=> 0,
			'forum_last_post_id'		=> 0,
			'forum_last_poster_id'		=> 0,
			'forum_last_post_subject'	=> '',
			'forum_last_post_time'		=> 0,
			'forum_last_poster_name'	=> '',
			'forum_last_poster_colour'	=> '',
			'display_on_index'			=> 1,
			'enable_indexing'			=> 1,
			'enable_icons'				=> 1,
		);

		// Now add the forums with proper left/right ids
		$sql = 'SELECT left_id, right_id
			FROM ' . FORUMS_TABLE . '
			WHERE forum_id = ' . $sql_ary['parent_id'];
		$_result = $db->sql_query($sql);
		$cat_row = $db->sql_fetchrow($_result);
		$db->sql_freeresult($_result);

		$sql = 'UPDATE ' . FORUMS_TABLE . '
			SET left_id = left_id + 2, right_id = right_id + 2
			WHERE left_id > ' . $cat_row['right_id'];
		$db->sql_query($sql);

		$sql = 'UPDATE ' . FORUMS_TABLE . '
			SET right_id = right_id + 2
			WHERE ' . $cat_row['left_id'] . ' BETWEEN left_id AND right_id';
		$db->sql_query($sql);

		$sql_ary['left_id'] = $cat_row['right_id'];
		$sql_ary['right_id'] = $cat_row['right_id'] + 1;

		$sql = 'INSERT INTO ' . FORUMS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary);
		$db->sql_query($sql);
	}
	$src_db->sql_freeresult($result);

	switch ($db->sql_layer)
	{
		case 'mssql':
		case 'mssql_odbc':
			$db->sql_query('SET IDENTITY_INSERT ' . FORUMS_TABLE . ' OFF');
		break;
	}
	smf_build_forum_parents();
}

/**
*  Try for the attachment extension from the filename
*/
function smf_get_attachment_ext($filename)
{
	// keep $extensions around to minimize queries
	static $extensions;

	// Get the extension from the filename
	$Ex = substr(strrchr($filename, '.'), 1);

	// Get Extensions (once per run)
	if (!isset($extensions))
	{
		global $db;
		$sql = 'SELECT extension
			FROM ' . EXTENSIONS_TABLE;
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			$extensions[] = $row['extension'];
		}
		$db->sql_freeresult($result);
	}

	// Have a poke about for our new friend
	foreach ($extensions as $extension)
	{
		// Eureka! We have it!
		if (strtolower($extension) == strtolower($Ex))
		{
			return strtolower($Ex);
		}
	}

	// what should be returned on no find?
	return '';
}

function smf_time_format($timeformat)
{
	$formatreplace = array("%a" => "D", "%A" => "l", "%b" => "M", "%B" => "F", "%c" => "D d M Y h:i:s A", "%C" => "", "%d" => "d", "%D" => "m/d/y", "%e" => "j", "%g" => "y", "%G" => "Y", "%h" => "M", "%H" => "H", "%I" => "h", "%j" => "z", "%m" => "m", "%M" => "i", "%n" => "", "%p" => "A", "%P" => "A", "%r" => "h:i:s A", "%R" => "H:i", "%S" => "s", "%t" => "", "%T" => "H:i:s", "%u" => "", "%U" => "", "%V" => "W", "%w" => "w", "%W" => "", "%x" => "m/d/Y", "%X" => "h:i:s A", "%y" => "y", "%Y" => "Y", "%z" => "O", "%Z" => "T", "%%" => "%");
	return strtr($timeformat, $formatreplace);
}

/**
* Convert censored words
*/
function convert_words()
{
	global $db, $convert;

	$db->sql_query($convert->truncate_statement . WORDS_TABLE);

	$wildcard = (bool) !get_config_value('censorWholeWord');
	
	$words = smf_set_encoding(get_config_value('censor_vulgar'));
	$replacements = smf_set_encoding(get_config_value('censor_proper'));

	if (empty($words) || empty($replacements))
	{
		return;
	}

	$words = explode("\n", $words);
	$words = array_map('utf8_htmlspecialchars', $words);
	$replacements = explode("\n", $replacements);
	$replacements = array_map('utf8_htmlspecialchars', $replacements);

	foreach ($words as $index => $word)
	{
		if (!isset($replacements[$index]))
		{
			continue;
		}

		if ($wildcard)
		{
			$word = "*$word*";
		}
		$sql = 'INSERT INTO ' . WORDS_TABLE . '
			(word, replacement) 
			VALUES ("' . $db->sql_escape($word). '", "' . $db->sql_escape($replacements[$index]) . '")';
		$db->sql_query($sql);
	}
}

/**
* Convert buddy/ignore user lists for users
*/
function convert_zebra()
{
	global $src_db, $db, $convert;

	$db->sql_query($convert->truncate_statement . ZEBRA_TABLE);

	// select buddies and ignored users from the SMF members table
	$sql = 'SELECT id_member, buddy_list, pm_ignore_list
		FROM ' . $convert->src_table_prefix . 'members
		WHERE buddy_list <> "" OR pm_ignore_list <> ""
		ORDER BY id_member ASC';
	$result = $src_db->sql_query($sql);

	$zebra_ary = array();
	while ($row = $src_db->sql_fetchrow($result))
	{
		$zebra_ary[$row['id_member']]['friends'] = array_unique(explode(',', $row['buddy_list']));
		$zebra_ary[$row['id_member']]['foes'] = array_unique(explode(',', $row['pm_ignore_list']));
	}
	$src_db->sql_freeresult($result);

	$db->sql_return_on_error(true);

	$insert_ary = array();
	$i = 0;

	// and build some SQL queries for phpBB's zebra table
	foreach ($zebra_ary as $user_id => $zebra_groups)
	{
		$user_id = smf_user_id($user_id);

		foreach ($zebra_groups as $zebra_group => $zebra_members)
		{
			foreach ($zebra_members as $zebra_id)
			{
				$insert_ary[] = array(
					'user_id'	=> (int) $user_id,
					'zebra_id'	=> (int) $zebra_id,
					'friend'	=> (int) ($zebra_group == 'friends'),
					'foe'		=> (int) ($zebra_group == 'foes'),
				);

				$i++;

				if ($i >= 999)
				{
					$db->sql_multi_insert(ZEBRA_TABLE, $insert_ary);
					$insert_ary = array();
					$i = 0;
				}
			}
		}
	}

	// Insert any left over rows that do not meet the batch minimum
	if ($i > 0)
	{
		$db->sql_multi_insert(ZEBRA_TABLE, $insert_ary);	
	}

	$db->sql_return_on_error(false);
}

/**
* Calculate the date a user became inactive
*/
function smf_inactive_time()
{
	global $convert_row;

	if ($convert_row['is_activated'])
	{
		return 0;
	}

	if ($convert_row['last_login'])
	{
		return $convert_row['last_login'];
	}

	return $convert_row['date_registered'];
}

/**
* Calculate the reason a user became inactive
* We can't actually tell the difference between a manual deactivation and one for profile changes
* from the data available to assume the latter
*/
function smf_inactive_reason()
{
	global $convert_row;

	if ($convert_row['is_activated'])
	{
		return 0;
	}

	if ($convert_row['last_login'])
	{
		return INACTIVE_PROFILE;
	}

	return INACTIVE_REGISTER;
}

function smf_get_birthday($birthday = '')
{
	$birthday = (string) $birthday;
	
	// stored as year, month, day
	if (!$birthday)
	{
		return '';
	}

	// Expected format from SMF is YYYY-MM-DD
	$birthday_parts = explode('-',$birthday);

	if ( $birthday_parts[0] == 0001 )
	{
		return '';
	}

	$year = $birthday_parts[0];
	$month = $birthday_parts[1];
	$day =  $birthday_parts[2];

	return sprintf('%2d-%2d-%4d', $day, $month, $year);
}

function smf_rank_min($min_posts)
{
	if ($min_posts == -1)
	{
		return 0;
	}
	return (int) $min_posts;
}

function smf_group_colour($colour)
{
	if (strpos($colour, '#') === 0)
	{
		return substr($colour, 1);
	}
	return $colour;
}
// takes SMF rank image, and removes everything before and including the sharp.
function smf_rank_image($rank_image)
{
	global $config;

	$smf_rank_image = substr($rank_image, (strpos($rank_image, '#') + 1));
	$src = smf_default_theme_images_path() . $smf_rank_image;

	$trg = $config['ranks_path'] . '/' . $smf_rank_image;
	copy_file($src, $trg, false, false, false);

	return substr($rank_image, (strpos($rank_image, '#') + 1));
}

/**
* Copy message icon to phpBB
*/
function smf_message_icon($filename)
{
	global $config;

	$filename = $filename . '.gif';
	$src = smf_default_theme_images_path() . 'post/' . $filename;
	$to = $config['icons_path'] . '/smf/' . $filename;

	copy_file($src, $to, true, false, false);

	return 'smf/' . $filename;
}

/**
* Get message icon dimensions
*/
function smf_icon_dim($filename, $axis)
{
	global $config, $phpbb_root_path;

	$src = smf_default_theme_images_path(false) . 'post/' . $filename . '.gif';
	$axis = ($axis == 'x') ? 0 : 1;
	$dimensions = get_image_dim($src);

	return (int) $dimensions[$axis];
}

/**
* Get message icon height
*/
function smf_icon_height($filename)
{
	return smf_icon_dim($filename, 'y');
}

/**
* Get message icon width
*/
function smf_icon_width($filename)
{
	return smf_icon_dim($filename, 'x');
}

/**
* Get message icon id using file name
*/
function smf_icon_id($icon_name)
{
	global $src_db, $convert;
	static $icons;

	// We don't want to add the default icon to topics so skip it
	if ($icon_name == 'xx')
	{
		return 0;
	}

	if ($icons === null)
	{
		$sql = 'SELECT id_icon, filename
			FROM ' . $convert->src_table_prefix . 'message_icons';
		$result = $src_db->sql_query($sql);

		while ($row = $src_db->sql_fetchrow($result))
		{
			$icons[$row['filename']] = (int) $row['id_icon'];
		}
		$src_db->sql_freeresult($result);
	}

	if (isset($icons[$icon_name]))
	{
		return $icons[$icon_name]; 
	}
	return 0;
}

function smf_prepare_message($message)
{
	global $phpbb_root_path, $phpEx, $db, $smf_board_url, $boardurl, $smf_config_schema, $convert, $user, $config, $cache, $convert_row, $message_parser;

	if (!$message)
	{
		$convert->row['mp_bbcode_bitfield'] = $convert_row['mp_bbcode_bitfield'] = 0;
		$convert->row['mp_bbcode_uid'] = $convert_row['mp_bbcode_uid'] = '';
		return '';
	}

	// Scrap any bbcodes we don't handle and convert newlines
	// @todo: Insert custom BBCodes so we don't have to scrap these
	$message = preg_replace('#<(br|br/|br /|br\s/)>#i', "\n", $message);
//	$bbcodes = array('[hr]', '[s]', '[/s]', '[sup]', '[/sup]', '[sub]', '[/sub]', '[tt]', '[/tt]', '[left]', '[/left]', '[center]', '[/center]', '[table]', '[/table]', '[tr]', '[/tr]', '[td]', '[/td]', '[pre]', '[/pre]', '[right]', '[/right]', '[move]', '[/move]', '[/shadow]', '[/glow]', '[/font]', '[/li]');
	$bbcodes = array('[table]', '[/table]', '[tr]', '[/tr]', '[td]', '[/td]');
	$message = str_replace($bbcodes, '', $message);

	$smf2phpbb_bbcodes = array(
		'/\[hr\]/is' => '[hr][/hr]',
		'/\[pre\](.*?)\[\/pre\]/is' => '[code]$1[/code]',
		'/\[center\](.*?)\[\/center\]/is' => '[align=center]$1[/align]',
		'/\[left\](.*?)\[\/left\]/is' => '[align=left]$1[/align]',
		'/\[right\](.*?)\[\/right\]/is' => '[align=right]$1[/align]',
		'/\[li\](.*?)\[\/li\]/is' => '[*]$1',
		'/\[IMG/' => '[img',
		'/\[\/IMG/' => '[/img',
		'/\[list type=decimal/' => '[list=1',
	);
	$message = preg_replace(array_keys($smf2phpbb_bbcodes), array_values($smf2phpbb_bbcodes), $message);

	$message = str_replace('[li]', '[*]', $message);

   	$message = preg_replace('#\[size=([0-9]*)pt\]#i', '[size=${1}0]', $message);

	// attempt to convert board relative URLs.  This may not be worth the performance hit
	$message = preg_replace('#' . phpbb_preg_quote(path($smf_board_url . '/'), '#') . 'index\.php\?board=([0-9])*#i', $config['server_protocol'] . $config['server_name'] . $config['script_path'] . '/viewforum.' . $phpEx . '?f=\\1', $message);
	$message = preg_replace ('#(\[iurl\])' . phpbb_preg_quote(path($smf_board_url . '/'), '#') . 'index\.php\?topic=([0-9])*(.0\[/iurl\])#i', '[url]' . $config['server_protocol'] . $config['server_name'] . $config['script_path'] . '/viewtopic.' . $phpEx . '?t=\\2[/url]', $message);
	$message = preg_replace ('#' . phpbb_preg_quote(path($smf_board_url . '/'), '#') . 'index\.php\?topic=([0-9])*(.0)#i', $config['server_protocol'] . $config['server_name'] . $config['script_path'] . '/viewtopic.' . $phpEx . '?t=\\1', $message);
	$message = preg_replace('/' . phpbb_preg_quote(path($smf_board_url . '/'), '/') . 'index\.php\?topic=([0-9]*).msg([0-9]*)#msg([0-9]*)/i', $config['server_protocol'] . $config['server_name'] . $config['script_path'] . '/viewtopic.' . $phpEx . '?p=\\2#p\\2', $message);


	// Set bbcode_uid, and set message in parser
	if (isset($convert->row['poster_time']))
	{
		$message_parser->bbcode_uid = make_uid($convert->row['poster_time']); 
	}
	else if (isset($convert->row['message_time']))
	{
		$message_parser->bbcode_uid = make_uid($convert->row['message_time']);
	}
	else
	{
		// this is probably a signature ... randomly make a UID
		$message_parser->bbcode_uid = $convert->row['mp_bbcode_uid'] = make_uid(mt_rand(10000000, 99999999));
	}

	// turn SMF's extended quotes into quotes that can be parsed by phpBB
	if (strpos($message, '[quote') !== false)
	{
		$message = preg_replace('/\[quote=(.*?)\]/s', '[quote=&amp;quot;\1&amp;quot;]', $message);
		$message = preg_replace('/\[quote=&amp;quot;&quot;(.*?)&quot;&amp;quot;\]/s', '[quote=&amp;quot;\1&amp;quot;]', $message); // Removes double quotes inserted by previous preg_replace
		$message = preg_replace("#\[quote author=(.+?) link=.+?\]#i", '[quote=&amp;quot;\1&amp;quot;]', $message);
		$message = preg_replace("#\[quote author=&quot;(.+?)&quot;\]#i", '[quote=&amp;quot;\1&amp;quot;]', $message);
		$message = preg_replace("#\[quote author=(.+?)\]#i", '[quote=&amp;quot;\1&amp;quot;]', $message);

		// let's hope that this solves more problems than it causes. Deal with escaped quotes.
		$message = str_replace('\"', '&quot;', $message);
		$message = str_replace('\&quot;', '&quot;', $message);
	}

	$message_parser->message = $message;

	// Already the new user id ;)
	$user_id = $convert->row['id_member'];

	$message = str_replace('<br />', "\n", $message);

	// make the post UTF-8
	$message = smf_set_encoding($message);

	$message_parser->warn_msg = array(); // Reset the errors from the previous message
	$message_parser->message = $message;
	unset($message);

	// Make sure options are set.
//	$enable_html = (!isset($row['enable_html'])) ? false : $row['enable_html'];
	$enable_bbcode = ($config['allow_bbcode']) ? true : false;
	$enable_smilies = (!isset($convert->row['user_sig'])) ? true : $config['allow_sig_smilies'];
	$enable_magic_url = (!isset($convert->row['enable_magic_url'])) ? true : $convert->row['enable_magic_url'];

	// parse($allow_bbcode, $allow_magic_url, $allow_smilies, $allow_img_bbcode = true, $allow_flash_bbcode = true, $allow_quote_bbcode = true, $allow_url_bbcode = true, $update_this_message = true, $mode = 'post')
	$message_parser->parse($enable_bbcode, $enable_magic_url, $enable_smilies, true, true, true, true);

	if (sizeof($message_parser->warn_msg))
	{
		$msg_id = isset($convert->row['post_id']) ? $convert->row['post_id'] : $convert->row['privmsgs_id'];
		//$convert->p_master->error('<span style="color:red">' . $user->lang['POST_ID'] . ': ' . $msg_id . ' ' . $user->lang['CONV_ERROR_MESSAGE_PARSER'] . ': <br /><br />' . implode('<br />', $message_parser->warn_msg), __LINE__, __FILE__, true);
	}

	$convert->row['mp_bbcode_bitfield'] = $convert_row['mp_bbcode_bitfield'] = $message_parser->bbcode_bitfield;
	$convert->row['mp_bbcode_uid'] = $convert_row['mp_bbcode_uid'] = $message_parser->bbcode_uid;

	$message = $message_parser->message;
	unset($message_parser->message);

	return $message;
}

/**
* Return the bitfield calculated by the previous function
*/
function get_bbcode_bitfield()
{
	global $convert;

	return $convert->row['mp_bbcode_bitfield'];
}

// config table functions

function phpbb_preg_quote($str, $delimiter)
{
	$text = preg_quote($str);
	$text = str_replace($delimiter, '\\' . $delimiter, $text);
	
	return $text;
}

function smf_set_primary_group($group_id)
{
	global $convert_row;

	if ($group_id == 1)
	{
		return get_group_id('administrators');
	}
	else if ($group_id == 2)
	{
		return get_group_id('global_moderators');
	}
	else if ($group_id == 3)
	{
		return get_group_id('moderators');
	}
	else if ($convert_row['is_activated'])
	{
		return get_group_id('registered');
	}

	return 0;
}

function smf_avatar($filename)
{
	$filename = trim($filename);

	// require at least 4 characters.  Ex: a.png
	if (strlen($filename) > 4)
	{
		return ($filename == 'blank.gif') ? '' : $filename;
	}

	if (empty($filename))
	{
		// Do we have an uploaded avatar?
		global $src_db, $convert, $config;

/*		$sql = 'SELECT ID_ATTACH, filename, ID_MEMBER';

		if (defined('FILE_HASH'))
		{
			$sql .= ', file_hash';
		}

		$sql .= ' FROM ' . $convert->src_table_prefix . 'attachments 
			WHERE ID_MEMBER = ' . $convert->row['ID_MEMBER']; */

		if (defined('FILE_HASH'))
		{
			$sql = "SELECT id_attach, filename, id_member, file_hash
				FROM {$convert->src_table_prefix}attachments 
				WHERE id_member = " . $convert->row['id_member'];
		}
		else
		{
			$sql = "SELECT id_attach, filename, id_member
				FROM {$convert->src_table_prefix}attachments 
				WHERE id_member = " . $convert->row['id_member'];
		}

		if (!$result = $src_db->sql_query($sql))
		{
			$convert->p_master->error('Error in obtaining user_avatar_data for ' . $convert->row['id_member'], __LINE__, __FILE__);
		}
		$row = $src_db->sql_fetchrow($result);

		if ($row['filename'])
		{
			$smf_upload_path = $convert->options['forum_path'] . '/' . $convert->convertor['upload_path'];
			$user_avatar = $row['filename'];
			$member = smf_user_id($row['id_member']);
			$filename = substr(strrchr($user_avatar, '_'), 1);
//			$src = $smf_upload_path . $user_avatar;
			$src = empty($row['file_hash']) ? $smf_upload_path . $user_avatar : $smf_upload_path . $row['id_attach'] . '_' . $row['file_hash'];
			$use_target = $config['avatar_path'] . '/' . $config['avatar_salt'] . '_' . $member . '.' . substr(strrchr($filename, '.'), 1);
			$filename = substr(strrchr($use_target, '_'), 1);
			copy_file($src, $use_target, false, false, false);
			
			$src_db->sql_freeresult($result);
		}
	}
	return ($filename == 'blank.gif') ? '' : $filename;
}

function smf_avatar_type($path)
{
	$type = (preg_match('#/.*?/#', $path)) ? AVATAR_REMOTE : AVATAR_GALLERY;

	if ($path == 'blank.gif' || empty($path))
	{
		$type = 0;
		if (empty($path))
		{
			global $src_db, $convert;
			// Check for upload avatar
			$sql = "SELECT filename, id_member
				FROM {$convert->src_table_prefix}attachments 
				WHERE id_member = " . $convert->row['id_member'];

			if (!($result = $src_db->sql_query($sql)))
			{
				$convert->p_master->error('Error in obtaining user_avatar_data', __LINE__, __FILE__);
			}
			$row = $src_db->sql_fetchrow($result);

			if ($row['id_member'])
			{
				$type = 1;
			}
		}
	}
	return (int) $type;
}

function smf_convert_dateformat($format)
{
	static $locale, $replace;

	// No format? Go for default
	$format = (empty($format)) ? get_config_value('time_format') : $format;

	// setup locales to php date arrays
	if (empty($locale) || empty($replace))
	{
		$locale		= array('%a', '%A', '%b', '%B', '%d', '%D', '%e', '%g', '%G', '%h', '%H', '%I', '%j', '%m', '%M', '%n', '%p', '%r', '%R', '%S', '%t', '%T', '%u', '%U', '%V', '%w', '%W', '%y', '%Y', '%z', '%Z', '%%');
		$replace	= array('D', 'l', 'M', 'F', 'd', 'm/d/Y', 'j', 'y', 'y', 'M', 'H', 'g', 'z', 'm', 'i', "\n", 'a', 'g:ia', 'H:i', 's', "\t", 'H:i:s', 'w', 'W', 'W', 'w', 'W', 'Y', 'y', 'T', 'T', '%');
	}

	// run replacements
	$format = str_replace($locale, $replace, $format);

	return $format;
}

function smf_build_forum_parents()
{
	global $db;
	$sql = 'SELECT forum_id, forum_name, parent_id, forum_parents, left_id, right_id, forum_type
		FROM ' . FORUMS_TABLE . '
		ORDER BY left_id ASC';
	$result = $db->sql_query($sql);

	$parent_forums = $forums = array();
	while ($row = $db->sql_fetchrow($result))
	{
		if ($row['parent_id'] !== 0)
		{
			$forums[] = $row;
		}
	}
	$db->sql_freeresult($result);

	get_forum_parents($forums);
}

function get_forum_parents(&$forum_data)
{
	global $db;
	$forum_parents = array();

	for ($i = 0; $i < count($forum_data); $i++)
	{
		if (isset($forum_data[$i]) && $forum_data[$i]['parent_id'] > 0)
		{
			if ($forum_data[$i]['forum_parents'] == '')
			{
				$sql = 'SELECT forum_id, forum_name, forum_type
					FROM ' . FORUMS_TABLE . '
					WHERE left_id < ' . $forum_data[$i]['left_id'] . '
					AND right_id > ' . $forum_data[$i]['right_id'] . '
					ORDER BY left_id ASC';
				$result2 = $db->sql_query($sql);

				while ($row = $db->sql_fetchrow($result2))
				{
					$forum_parents[$row['forum_id']] = array($row['forum_name'], (int) $row['forum_type']);
				}
				$db->sql_freeresult($result2);

				$forum_data['forum_parents'] = serialize($forum_parents);

				$sql = 'UPDATE ' . FORUMS_TABLE . "
					SET forum_parents = '" . $db->sql_escape($forum_data['forum_parents']) . "'
					WHERE parent_id = " . $forum_data[$i]['parent_id'];
				$db->sql_query($sql);
				unset($forum_parents);
			}
		}
	}
}

function smf_import_attachment($filename)
{
	global $config, $convert, $phpbb_root_path, $physical, $db, $src_db;

	$attach_dir = $config['upload_path'];
	$attach_id = $convert->row['id_attach'];
	$thumb_id = $convert->row['id_thumb'];
	$filename = $convert->row['filename'];
	$file_hash = $convert->row['file_hash'];
	$smf_upload_path = $convert->options['forum_path'].'/'.$convert->convertor['upload_path'];
	$user_id = smf_user_id($convert->row['attach_poster']);
	$clean_filename = preg_replace(array('/\s/', '/[^\w_\.\-]/'), array('_', ''), $filename);
	$enc_filename = $attach_id . '_' . strtr($clean_filename, '.', '_') . md5($clean_filename);
	$plain_filename = preg_replace('~\.[\.]+~', '.', $clean_filename);
	$hash_filename = $attach_id . '_' . $file_hash;
	if ( $thumb_id )
	{
		$thumb_enc_filename = $thumb_id . '_' . strtr($clean_filename, '.', '_') . '_thumb' . md5($clean_filename . '_thumb');
		$thumb_plain_filename = $plain_filename . '_thumb';
	}

	$exist_encode_file = $exist_plain_file = $exist_hash_file = FALSE;

	if ( file_exists($smf_upload_path . $enc_filename ) )
	{
		$exist_encode_file = TRUE;
		$physical = $new_file = $user_id . '_' . md5(unique_id());
		$src = $convert->convertor['upload_path'] . $enc_filename;
		$trg = $attach_dir . '/' . $new_file;
		copy_file($src, $trg, false, false);
	}
	if ( file_exists($smf_upload_path . $clean_filename ) )
	{
		$exist_plain_file = TRUE;
		$physical = $new_file = $user_id . '_' . md5(unique_id());
		$src = $convert->convertor['upload_path'] . $clean_filename;
		$trg = $attach_dir . '/' . $new_file;
		copy_file($src, $trg, false, false);
	}
	if ( isset($file_hash ) && file_exists($smf_upload_path . $hash_filename ) )
	{
		$exist_hash_file = TRUE;
		$physical = $new_file = $user_id . '_' . md5(unique_id());
		$src = $convert->convertor['upload_path'] . $hash_filename;
		$trg = $attach_dir . '/' . $new_file;
		copy_file($src, $trg, false, false);
	}

	if ($convert->row['id_thumb'])
	{
		$thumb_filename = '';
		if ( $exist_encode_file )
		{
			$thumb_filename = $thumb_enc_filename;
		}
		if ( $exist_plain_file )
		{
			$thumb_filename = $thumb_plain_filename;
		}
		if ( $exist_hash_file )
		{
			$sql = 'SELECT id_attach, file_hash FROM ' . $convert->src_table_prefix . 'attachments
				WHERE id_attach = ' . (int) $thumb_id;
			if (!($result = $src_db->sql_query($sql)))
			{
				$convert->p_master->error('Could not retrieve attachment thumb information', __LINE__, __FILE__);
			}

			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			$thumb_filename = $row['id_attach'] . '_' . $row['file_hash'];
		}
		$src = $convert->convertor['upload_path'] . $thumb_filename;
		$trg = $attach_dir . '/' . 'thumb_' . $new_file;
		copy_file($src, $trg, false, false);
	}

	return $physical;
}

/**
*  Does the post have open reports?
*/
function smf_post_reported($reports_closed)
{
	if ($reports_closed == '')
	{
		return 0;
	}
	return not($reports_closed);
}

/**
*  Does the passed post have an attachment?
*/
function smf_post_has_attachment($post_id)
{
	global $db, $src_db, $same_db, $convert;

	$sql = 'SELECT id_attach FROM ' . $convert->src_table_prefix . 'attachments
		WHERE id_msg = ' . (int) $post_id;
	if (!($result = $src_db->sql_query($sql)))
	{
		$convert->p_master->error('Could not retrieve post information', __LINE__, __FILE__);
	}

	$row = $db->sql_fetchrowset($result);
	$db->sql_freeresult($result);

	return (sizeof($row)) > 0 ? 1 : 0;
}

/**
* Does the passed topic have an attachment?
*
* NOTE: This only deals with the first message,
* Really don't want to be checking each post in the topic
*/
function smf_topic_has_attachment($topic_id)
{
	global $src_db, $same_db, $convert;

	$sql = 'SELECT a.id_attach, t.id_first_msg, t.id_topic
		FROM ' . $convert->src_table_prefix . 'attachments a, ' . $convert->src_table_prefix . 'topics t
		WHERE a.id_msg = t.id_first_msg
			AND t.id_topic = ' . $topic_id;

	if (!($result = $src_db->sql_query($sql)))
	{
		$convert->p_master->error('Could not retrieve topic information', __LINE__, __FILE__);
	}

	$row = $src_db->sql_fetchrowset($result);
	$src_db->sql_freeresult($result);

	return sizeof($row);
}

/**
*  Determine if the report is closed.
*
*  When dealing with a report that is open, this will return false only if it's the latest report for the post.
*  phpBB can only have one report open at a time, so we must handle this right now otherwise users will be unable to close reports.
*/

function smf_report_closed($reports_closed)
{
	global $convert_row;

	if ($reports_closed || $convert_row['last_report'] != $convert_row['time_sent'])
	{
		return 1;
	}
	return 0;
}

/**
*  Wrapper for accessing current post bbcode_uid
*/
function smf_get_bbcode_uid()
{
	global $convert;

	return $convert->row['mp_bbcode_uid'];
}

/**
* Get ID from Username
*/
function smf_get_userid_from_username($username)
{
	if (empty($username))
	{
		return;
	}

	global $db, $src_db, $same_db, $convert;

	if ($convert->mysql_convert && $same_db)
	{
		$src_db->sql_query("SET NAMES 'binary'");
	}

	$username = $db->sql_escape($username);
	$sql = "SELECT id_member
		FROM {$convert->src_table_prefix}members
		WHERE real_name = '$username'";
	if (!($result = $src_db->sql_query($sql)) || !($row = $src_db->sql_fetchrow($result)))
	{
		//conv_error('Could not retrieve username', __LINE__, __FILE__);
		//return '';
	}

	if ($convert->mysql_convert && $same_db)
	{
		$src_db->sql_query("SET NAMES 'utf8'");
	}

	return (int) smf_user_id($row['id_member']);
}

function smf_topic_has_poll($topic_id)
{
	if ($topic_id == 0)
	{
		return 0;
	}

	global $db, $src_db, $same_db, $convert;

	$sql = "SELECT m.poster_time, t.id_first_msg
		FROM {$convert->src_table_prefix}messages m, {$convert->src_table_prefix}topics t
		WHERE t.id_first_msg = m.id_msg
			AND t.id_poll <> 0
			AND t.id_topic = $topic_id";
	if (!($result = $src_db->sql_query($sql)))
	{
		$convert->p_master->error('Could not retrieve poll start time', __LINE__, __FILE__);
	}

	if ($row = $src_db->sql_fetchrow($result))
	{
		return $row['poster_time'];
	}
	else
	{
		return 0;
	}
}

function smf_poll_expire_time($poll_id)
{
	if ($poll_id == 0)
	{
		return 0;
	}

	global $db, $src_db, $same_db, $convert;

	// need poll_expire_time, first_message_time,
	$sql = "SELECT expire_time
		FROM {$convert->src_table_prefix}polls
		WHERE id_poll = $poll_id";
	if (!($result = $src_db->sql_query($sql)))
	{
		$convert->p_master->error('Could not retrieve poll expire time', __LINE__, __FILE__);
	}

	if (!($row = $src_db->sql_fetchrow($result)))
	{
		$sql = "UPDATE {$convert->src_table_prefix}topics SET id_poll = 0 WHERE id_poll = $poll_id";
		$src_db->sql_query($sql);
	}
	$src_db->sql_freeresult($result);

	return (int) $row['expire_time'];
}

function smf_poll_topic_id($poll_id)
{
	global $src_db, $convert;

	$poll_id = (int) $poll_id;

	$sql = "SELECT id_topic FROM {$convert->src_table_prefix}topics
		WHERE id_poll = $poll_id";
	$result = $src_db->sql_query($sql);
	$topic_id = $src_db->sql_fetchfield('id_topic');

	return $topic_id;	
}

/**
*  For setting the hardcoded vars into the db from Settings.php
*/
function smf_conv_file_settings()
{
	global $smf_board_url;

	$smf_board_url = smf_setting('boardurl');
	$board_disable = (smf_setting('maintenance') == 0) ? 0 : 1;
	set_config('board_disable', $board_disable);

	set_config('sitename', utf8_htmlspecialchars(smf_set_encoding(smf_setting('mbname'))));

	set_config('board_contact', smf_set_encoding(smf_setting('webmaster_email')));
	set_config('board_disable_msg', utf8_htmlspecialchars(smf_set_encoding(smf_setting('mmessage'))));
	set_config('board_email', smf_set_encoding(smf_setting('webmaster_email')));
}

/**
* Access a hardcoded setting from the smf Settings.php
*/
function smf_setting($var)
{
	static $smf_settings;

	if (empty($smf_settings))
	{
		global $convert;
		$smf_settings = extract_variables_from_file($convert->options['forum_path'] . '/Settings.php');
	}
	return (isset($smf_settings[$var])) ? $smf_settings[$var]: false;
}

function smf_absolute_to_path($absolute)
{
	global $convert;

	$temp_path = substr(strrchr($absolute, "/"), 1);
	$path = $convert->options['forum_path'] . "/" . $absolute . '/';
	return $path;
}


function smf_get_avatar_height($user_avatar)
{
	$user_dim_avatar = $user_avatar;
	global $type;
	$type = smf_avatar_type($user_avatar);

	switch ($type)
	{
	// remote_avatar_dims
		case AVATAR_REMOTE:
//			return get_remote_avatar_dim($src, $axis);
			$remote_avatar_height = get_avatar_height($user_dim_avatar, 'phpbb_avatar_type', AVATAR_REMOTE);

			return get_avatar_height($user_dim_avatar, 'phpbb_avatar_type', AVATAR_REMOTE);
		break;
	
		case AVATAR_GALLERY:
			return get_avatar_height($user_dim_avatar, 'phpbb_avatar_type', AVATAR_GALLERY);
		break;

		case AVATAR_UPLOAD:
			global $src_db, $convert;
			$upload_avatar_height = 0;

			$sql = "SELECT filename, id_member
				FROM {$convert->src_table_prefix}attachments 
				WHERE id_member = " . $convert->row['id_member'];

			if ( !($result = $src_db->sql_query($sql)) )
			{
				$convert->p_master->error('Error in obtaining user_avatar_data', __LINE__, __FILE__);
			}
			$row = $src_db->sql_fetchrow($result);

			if ($row['filename'])
			{
				$temp_avatar_path = $convert->convertor['avatar_path'];
				$convert->convertor['avatar_path'] = $convert->convertor['upload_path'];
				$user_dim_avatar = $row['filename'];
			}

			$upload_avatar_height = get_avatar_height($user_dim_avatar, 'phpbb_avatar_type', AVATAR_UPLOAD);

			$convert->convertor['avatar_path'] = $temp_avatar_path;
			return $upload_avatar_height;
		break;
	}
	return 0;
}


/**
* Find out about the avatar's dimensions
*/
function smf_get_avatar_width($user_avatar)
{
	$user_dim_avatar = $user_avatar;
	global $type;
	$type = smf_avatar_type($user_avatar);

	switch ($type)
	{
		case AVATAR_REMOTE:
		// remote_avatar_dims
			return get_avatar_width($user_dim_avatar, 'phpbb_avatar_type', AVATAR_REMOTE);
		break;
	
		case AVATAR_GALLERY:
			return get_avatar_width($user_dim_avatar, 'phpbb_avatar_type', AVATAR_GALLERY);
		break;
		
		case AVATAR_UPLOAD:
			// We have an uploaded avatar!
			global $src_db, $convert;

			$upload_avatar_width = 0;

			$sql = "SELECT filename, id_member
				FROM {$convert->src_table_prefix}attachments 
				WHERE id_member = " . $convert->row['id_member'];

			if ( !($result = $src_db->sql_query($sql)) )
			{
				$convert->p_master->error('Error in obtaining user_avatar_data', __LINE__, __FILE__);
			}
			$row = $src_db->sql_fetchrow($result);

			if ($row['filename'])
			{
				$user_dim_avatar = $row['filename'];
				$temp_avatar_path = $convert->convertor['avatar_path'];
				$convert->convertor['avatar_path'] = $convert->convertor['upload_path'];
				$upload_avatar_width = get_avatar_width($user_dim_avatar, 'phpbb_avatar_type', AVATAR_UPLOAD);
				$convert->convertor['avatar_path'] = $temp_avatar_path;
			}
			return $upload_avatar_width;
		break;
	}
	return 0;
}


function smf_to_address($to_userid)
{
	global $config, $convert;

	if ($convert->row['bcc'] == 0)
	{
		return 'u_' . smf_user_id($to_userid);
	}
	else
	{
		return '';
	}
}

function smf_bcc_address()
{
	global $src_db, $same_db, $convert;

	$sql = 'SELECT id_member FROM ' .  $convert->src_table_prefix . 'pm_recipients 
		WHERE id_pm = ' . $convert->row['id_pm'] . 
		' AND bcc = 1';
	$result = $src_db->sql_query($sql);

	while ($row = $src_db->sql_fetchrow($result))
	{
		$user_id = 'u_' . (int) smf_user_id($row['id_member']);
		$bcc[] = $user_id;
	}

	if ($bcc)
	{
		$bcc_to = implode(':', $bcc);
	}

	$src_db->sql_freeresult($result);
	return $bcc_to;
}

function smf_pm_box()
{
	global $src_db, $same_db, $convert, $convert_row;

	$box = array();
	$sql = 'SELECT is_read FROM ' .  $convert->src_table_prefix . 'pm_recipients 
		WHERE id_pm = ' . $convert->row['id_pm'];
	$result = $src_db->sql_query($sql);

	$box_type = PRIVMSGS_OUTBOX;
	while ($row = $src_db->sql_fetchrow($result))
	{
		if ($row['is_read'] == 1)
		{
			$box_type = PRIVMSGS_SENTBOX;
			break;
		}
	}
	$src_db->sql_freeresult($result);

	return $box_type;
}

function smf_user_last_privmsg()
{
	global $src_db, $same_db, $convert;
		
	$sql = "SELECT MAX(pr.id_pm) AS max_id_pm, psm.msgtime FROM ({$convert->src_table_prefix}pm_recipients pr
		LEFT JOIN {$convert->src_table_prefix}personal_messages psm ON psm.id_pm = pr.id_pm)
		WHERE id_member = " . (int) $convert->row['id_member'] . "
		GROUP BY pr.id_pm, psm.id_pm";

	$result = $src_db->sql_query($sql);
	$msgtime = (int) $src_db->sql_fetchfield('msgtime');
	$src_db->sql_freeresult($result);

	return $msgtime;
}

function conv_error($msg, $err_line = '', $err_file = '', $sql = '')
{
	echo $msg . '<br />';
}

function smf_ban_user_id($user_id)
{
	if ($user_id === 0)
	{
		return (int) $user_id;
	}
	return (int) smf_user_id($user_id);
}

// Add login_name field to users table if NOT exists
function smf_add_login_field()
{
	global $db, $table_prefix;

	$drop_sql = 'ALTER TABLE ' . USERS_TABLE . ' DROP login_name, DROP user_passwd_salt';
	$create_login_name_sql = 'ALTER TABLE ' . USERS_TABLE . ' ADD login_name VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT \'\' NOT NULL';
	$create_salt_sql =  'ALTER TABLE ' . USERS_TABLE . ' ADD user_passwd_salt VARCHAR( 5 ) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT \'\' NOT NULL';
	$create_convert_sql =  'ALTER TABLE ' . USERS_TABLE . ' ADD IF NOT EXISTS user_pass_convert TINYINT(1) UNSIGNED COLLATE utf8_bin DEFAULT 0 NOT NULL';

	$db->sql_return_on_error(true);
	$db->sql_query($drop_sql);
	$db->sql_query($create_login_name_sql);
	$db->sql_query($create_salt_sql);
	$db->sql_query($create_convert_sql);
	$db->sql_return_on_error(false);
}

function smf_import_banlist()
{
	global $db, $src_db, $convert, $cache;

	$db->sql_query($convert->truncate_statement . BANLIST_TABLE);
	$cache->destroy('sql', BANLIST_TABLE);

	$banlist_ary = array();
	$sql_ary = array();

	$sql = 'SELECT bi.*, bg.* 
		FROM ' . $convert->src_table_prefix . 'ban_items bi, ' . $convert->src_table_prefix . 'ban_groups bg
		WHERE bi.id_ban_group = bg.id_ban_group';
	$result = $src_db->sql_query($sql);

	while ($row = $src_db->sql_fetchrow($result))
	{
		$low_ban_ip = $row['ip_low1'] . '.' . $row['ip_low2'] . '.' . $row['ip_low3'] . '.' . $row['ip_low4'];
		$high_ban_ip = $row['ip_high1'] . '.' . $row['ip_high2'] . '.' . $row['ip_high3'] . '.' . $row['ip_high4'];
		$encode_low_ip = encode_ip($low_ban_ip);
		$encode_high_ip = encode_ip($high_ban_ip);
		$range_ip = ( $encode_high_ip > $encode_low_ip ) ? $low_ban_ip . '-' . $high_ban_ip : $low_ban_ip;

		$banlist_ary = array();

		// get mode
		if ( $encode_low_ip !== '00000000' && $encode_high_ip !== '00000000' )
		{
			$mode = 'ip';
			$ban_item = $range_ip;
		}
		if ( $row['id_member'] )
		{
			$mode = 'user';
			$ban_item = smf_ban_user_id($row['id_member']);
		}
		if ( $row['email_address'] )
		{
			$mode = 'email';
			$ban_item = str_replace('%', '*', $row['email_address']);
		}
		if ( $row['hostname'] )
		{
			$mode = 'ip';
			$ban_item = str_replace('%', '*', $row['hostname']);
		}

		switch ($mode)
		{
			case 'ip':
				$type = 'ban_ip';

				if (preg_match('#^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})[ ]*\-[ ]*([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$#', trim($ban_item), $ip_range_explode))
				{
					// This is an IP range
					// Don't ask about all this, just don't ask ... !
					$ip_1_counter = $ip_range_explode[1];
					$ip_1_end = $ip_range_explode[5];

					while ($ip_1_counter <= $ip_1_end)
					{
						$ip_2_counter = ($ip_1_counter == $ip_range_explode[1]) ? $ip_range_explode[2] : 0;
						$ip_2_end = ($ip_1_counter < $ip_1_end) ? 254 : $ip_range_explode[6];

						if ($ip_2_counter == 0 && $ip_2_end == 254)
						{
							$ip_2_counter = 256;
							$ip_2_fragment = 256;

							$banlist_ary[] = "$ip_1_counter.*";
						}

						while ($ip_2_counter <= $ip_2_end)
						{
							$ip_3_counter = ($ip_2_counter == $ip_range_explode[2] && $ip_1_counter == $ip_range_explode[1]) ? $ip_range_explode[3] : 0;
							$ip_3_end = ($ip_2_counter < $ip_2_end || $ip_1_counter < $ip_1_end) ? 254 : $ip_range_explode[7];

							if ($ip_3_counter == 0 && $ip_3_end == 254)
							{
								$ip_3_counter = 256;
								$ip_3_fragment = 256;

								$banlist_ary[] = "$ip_1_counter.$ip_2_counter.*";
							}

							while ($ip_3_counter <= $ip_3_end)
							{
								$ip_4_counter = ($ip_3_counter == $ip_range_explode[3] && $ip_2_counter == $ip_range_explode[2] && $ip_1_counter == $ip_range_explode[1]) ? $ip_range_explode[4] : 0;
								$ip_4_end = ($ip_3_counter < $ip_3_end || $ip_2_counter < $ip_2_end) ? 254 : $ip_range_explode[8];

								if ($ip_4_counter == 0 && $ip_4_end == 254)
								{
									$ip_4_counter = 256;
									$ip_4_fragment = 256;

									$banlist_ary[] = "$ip_1_counter.$ip_2_counter.$ip_3_counter.*";
								}

								while ($ip_4_counter <= $ip_4_end)
								{
									$banlist_ary[] = "$ip_1_counter.$ip_2_counter.$ip_3_counter.$ip_4_counter";
									$ip_4_counter++;
								}
								$ip_3_counter++;
							}
							$ip_2_counter++;
						}
						$ip_1_counter++;
					}
				}
				else if (preg_match('#^([0-9]{1,3})\.([0-9\*]{1,3})\.([0-9\*]{1,3})\.([0-9\*]{1,3})$#', trim($ban_item)) || preg_match('#^[a-f0-9:]+\*?$#i', trim($ban_item)))
				{
					// Normal IP address
					$banlist_ary[] = trim($ban_item);
				}
				else if (preg_match('#^\*$#', trim($ban_item)))
				{
					// Ban all IPs
					$banlist_ary[] = '*';
				}
				else if (preg_match('#^([\w\-_]\.?){2,}$#is', trim($ban_item)))
				{
					// hostname
					$ip_ary = gethostbynamel(trim($ban_item));

					if (!empty($ip_ary))
					{
						foreach ($ip_ary as $ip)
						{
							if ($ip)
							{
								if (strlen($ip) > 40)
								{
									continue;
								}

								$banlist_ary[] = $ip;
							}
						}
					}
				}
				break;

			case 'email':
				$type = 'ban_email';
				$ban_item = trim($ban_item);
				if (preg_match('#^.*?@*|(([a-z0-9\-]+\.)+([a-z]{2,3}))$#i', $ban_item))
				{
					if (strlen($ban_item) > 100)
					{
						continue;
					}
					$banlist_ary[] = $ban_item;
				}
			break;

			case 'user':
				$type = 'ban_userid';
				$banlist_ary[] = $ban_item;
			break;
		}

		// We have some entities to ban
		if (sizeof($banlist_ary))
		{
			$sql_ary = array();

			foreach ($banlist_ary as $ban_entry)
			{
				$sql_ary[] = array(
					$type				=> $ban_entry,
					'ban_start'			=> (int) $row['ban_time'],
					'ban_end'			=> (int) null_to_zero($row['expire_time']),
					'ban_reason'		=> (string) utf8_htmlspecialchars(smf_set_encoding($row['reason'])),
				);
			}
			unset ($banlist_ary);
		}
		$db->sql_multi_insert(BANLIST_TABLE, $sql_ary);
	}
	$cache->destroy('sql', BANLIST_TABLE);
}

function encode_ip($dotquad_ip)
{
	$ip_sep = explode('.', $dotquad_ip);
	return sprintf('%02x%02x%02x%02x', $ip_sep[0], $ip_sep[1], $ip_sep[2], $ip_sep[3]);
}

function add_bbcodes()
{
	global $db, $cache, $convert, $user;

	$add_bbcode_ary = array(
					'font=' => array(
						'bbcode_tag' 				=> 'font=',
    					'bbcode_match' 				=> '[font={SIMPLETEXT}]{TEXT}[/font]',
					    'bbcode_tpl' 				=> '{TEXT}',
    					'display_on_posting' 		=> '1',
					    'bbcode_helpline' 			=> '[font=Georgia]Georgia font[/font]',
					    'first_pass_match' 			=> '!\[font\=([a-zA-Z0-9-+.,_ ]+)\](.*?)\[/font\]!ies',
					    'first_pass_replace' 		=> '\'[font=${1}:$uid]\'.str_replace(array("\\r\\n", \'\\"\', \'\\\'\', \'(\', \')\'), array("\\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${2}\')).\'[/font:$uid]\'',
					    'second_pass_match' 		=> '!\[font\=([a-zA-Z0-9-+.,_ ]+):$uid\](.*?)\[/font:$uid\]!s',
					    'second_pass_replace' 		=> '<span style="font-family: ${1};">${2}</span>'
					),
					'align=' => array(
						'bbcode_tag' 				=> 'align=',
						'bbcode_match'				=> '[align={SIMPLETEXTTEXT}]{TEXT}[/align]',
						'bbcode_tpl'				=> '<div style="text-align: {SIMPLETEXT};">{TEXT}</div>',
						'display_on_posting'		=> '1',
						'bbcode_helpline'			=> 'Alignment: can use center, left, right',
						'first_pass_match'			=> '!\[align\=(.*?)\](.*?)\[/align\]!ies',
						'first_pass_replace'		=> '\'[align=${1}:$uid]\'.str_replace(array("\\r\\n", \'\\"\', \'\\\'\', \'(\', \')\'), array("\\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${2}\')).\'[/align:$uid]\'',
						'second_pass_match'			=> '!\[align\=(.*?):$uid\](.*?)\[/align:$uid\]!s',
						'second_pass_replace'		=> '<div style="text-align: ${1};">${2}</div>'
					),
					'hr' => array(
						'bbcode_tag' 				=> 'hr',
						'bbcode_match'				=> '[hr][/hr]',
						'bbcode_tpl'				=> '<hr />',
						'display_on_posting'		=> '1',
						'bbcode_helpline'			=> '[hr][/hr]  *Note: no in-between text is necessary.',
						'first_pass_match'			=> '!\[hr\]\[/hr\]!i',
						'first_pass_replace'		=> '[hr:$uid][/hr:$uid]',
						'second_pass_match'			=> '[hr:$uid][/hr:$uid]',
						'second_pass_replace'		=> ''
					),
					's' => array(
						'bbcode_tag' 				=> 's',
						'bbcode_match'				=> '[s]{TEXT}[/s]',
						'bbcode_tpl'				=> '<span style="text-decoration: line-through;">{TEXT}</span>',
						'display_on_posting'		=> '1',
						'bbcode_helpline'			=> '[s]strikethrough text[/s]',
						'first_pass_match'			=> '!\[s\](.*?)\[/s\]!ies',
						'first_pass_replace'		=> '\'[s:$uid]\'.str_replace(array("\\r\\n", \'\\"\', \'\\\'\', \'(\', \')\'), array("\\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/s:$uid]\'',
						'second_pass_match'			=> '!\[s:$uid\](.*?)\[/s:$uid\]!s',
						'second_pass_replace'		=> '<span style="text-decoration: line-through;">${1}</span>'
					),
					'sub' => array(
						'bbcode_tag' 				=> 'sub',
						'bbcode_match'				=> '[sub]{TEXT}[/sub]',
						'bbcode_tpl'				=> '<span style="vertical-align: sub;">{TEXT}</span>',
						'display_on_posting'		=> '1',
						'bbcode_helpline'			=> 'H[sub]2[/sub]O',
						'first_pass_match'			=> '!\[sub\](.*?)\[/sub\]!ies',
						'first_pass_replace'		=> '\'[sub:$uid]\'.str_replace(array("\\r\\n", \'\\"\', \'\\\'\', \'(\', \')\'), array("\\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/sub:$uid]\'',
						'second_pass_match'			=> '!\[sub:$uid\](.*?)\[/sub:$uid\]!s',
						'second_pass_replace'		=> '<span style="vertical-align: sub;">${1}</span>'
					),
					'sup' => array(
						'bbcode_tag' 				=> 'sup',
						'bbcode_match'				=> '[sup]{TEXT}[/sup]',
						'bbcode_tpl'				=> '<span style="vertical-align: super;">{TEXT}</span>',
						'display_on_posting'		=> '1',
						'bbcode_helpline'			=> 'x[sup]3[/sup]',
						'first_pass_match'			=> '!\[sup\](.*?)\[/sup\]!ies',
						'first_pass_replace'		=> '\'[sup:$uid]\'.str_replace(array("\\r\\n", \'\\"\', \'\\\'\', \'(\', \')\'), array("\\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/sup:$uid]\'',
						'second_pass_match'			=> '!\[sup:$uid\](.*?)\[/sup:$uid\]!s',
						'second_pass_replace'		=> '<span style="vertical-align: super;">${1}</span>'
					),
					'tt' => array(
						'bbcode_tag' 				=> 'tt',
						'bbcode_match'				=> '[tt]{TEXT}[/tt]',
						'bbcode_tpl'				=> '<tt>{TEXT}</tt>',
						'display_on_posting'		=> '1',
						'bbcode_helpline'			=> 'TrueType: [tt]Message[/tt]',
						'first_pass_match'			=> '!\[tt\](.*?)\[/tt\]!ies',
						'first_pass_replace'		=> '\'[tt:$uid]\'.str_replace(array("\\r\\n", \'\\"\', \'\\\'\', \'(\', \')\'), array("\\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/tt:$uid]\'',
						'second_pass_match'			=> '!\[tt:$uid\](.*?)\[/tt:$uid\]!s',
						'second_pass_replace'		=> '<tt>${1}</tt>'
					),
					'move' => array(
						'bbcode_tag' 				=> 'move',
						'bbcode_match'				=> '[move]{TEXT}[/move]',
						'bbcode_tpl'				=> '<marquee>{TEXT}</marquee>',
						'display_on_posting'		=> '1',
						'bbcode_helpline'			=> 'Marquee: [move]Message[/move]',
						'first_pass_match'			=> '!\[move\](.*?)\[/move\]!ies',
						'first_pass_replace'		=> '\'[move:$uid]\'.str_replace(array("\\r\\n", \'\\"\', \'\\\'\', \'(\', \')\'), array("\\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/move:$uid]\'',
						'second_pass_match'			=> '!\[move:$uid\](.*?)\[/move:$uid\]!s',
						'second_pass_replace'		=> '<marquee>${1}</marquee>'
					),
					'glow' => array(
						'bbcode_tag' 				=> 'glow',
						'bbcode_match'				=> '[glow={COLOR}]{TEXT}[/glow]',
						'bbcode_tpl'				=> '<span style="filter: Glow(color={COLOR, strength=2); height:20">{TEXT}</span>',
						'display_on_posting'		=> '1',
						'bbcode_helpline'			=> '[glow=red]Message[/glow] or [glow=#000000]Message[/glow]',
						'first_pass_match'			=> '!\[glow\=([a-z]+|#[0-9abcdef]+)\](.*?)\[/glow\]!ies',
						'first_pass_replace'		=> '\'[glow=${1}:$uid]\'.str_replace(array("\\r\\n", \'\\"\', \'\\\'\', \'(\', \')\'), array("\\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${2}\')).\'[/glow:$uid]\'',
						'second_pass_match'			=> '!\[glow\=([a-zA-Z]+|#[0-9abcdefABCDEF]+):$uid\](.*?)\[/glow:$uid\]!s',
						'second_pass_replace'		=> '<span style="filter: Glow(color={COLOR, strength=2); height:20">${2}</span>'
					),
					'shadow' => array(
						'bbcode_tag' 				=> 'shadow',
						'bbcode_match'				=> '[shadow={COLOR}]{TEXT}[/shadow]',
						'bbcode_tpl'				=> '<span style="filter: shadow(color={COLOR}); height:20">{TEXT}</span>',
						'display_on_posting'		=> '1',
						'bbcode_helpline'			=> '[shadow=red]Message[/shadow] or [shadow=#000000]Message[/shadow]',
						'first_pass_match'			=> '!\[shadow\=([a-z]+|#[0-9abcdef]+)\](.*?)\[/shadow\]!ies',
						'first_pass_replace'		=> '\'[shadow=${1}:$uid]\'.str_replace(array("\\r\\n", \'\\"\', \'\\\'\', \'(\', \')\'), array("\\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${2}\')).\'[/shadow:$uid]\'',
						'second_pass_match'			=> '!\[shadow\=([a-zA-Z]+|#[0-9abcdefABCDEF]+):$uid\](.*?)\[/shadow:$uid\]!s',
						'second_pass_replace'		=> '<span style="filter: shadow(color=${1}); height:20">${2}</span>'
					)
			);

	foreach ($add_bbcode_ary as $v1) 
	{
		$sql_ary =array();

		foreach ($v1 as $k => $v)
		{
			$sql_ary[$k] = $v;
		}
		$sql = 'SELECT 1 as test
			FROM ' . BBCODES_TABLE . "
			WHERE LOWER(bbcode_tag) = '" . $db->sql_escape(strtolower($sql_ary['bbcode_tag'])) . "'";
		$result = $db->sql_query($sql);
		$info = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		// If the bbcode does not already exist?
		if ($info['test'] != '1')
		{
			$sql = 'SELECT MAX(bbcode_id) as max_bbcode_id
				FROM ' . BBCODES_TABLE;
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			if ($row)
			{
				$bbcode_id = $row['max_bbcode_id'] + 1;

				// Make sure it is greater than the core bbcode ids...
				if ($bbcode_id <= NUM_CORE_BBCODES)
				{
					$bbcode_id = NUM_CORE_BBCODES + 1;
				}
			}
			else
			{
				$bbcode_id = NUM_CORE_BBCODES + 1;
			}
			// 1511 is the maximum bbcode_id allowed.
			if ($bbcode_id < 1512)
			{
				$sql_ary['bbcode_id'] = (int) $bbcode_id;

				$db->sql_query('INSERT INTO ' . BBCODES_TABLE . $db->sql_build_array('INSERT', $sql_ary));
				$cache->destroy('sql', BBCODES_TABLE);
			}
		}
	}
}

// Trash Bin
/********************************************************************************
* Convert the group name, making sure to avoid conflicts with 3.0 special groups
*/
function phpbb_convert_group_name($group_name)
{
	$default_groups = array(
		'GUESTS',
		'REGISTERED',
		'REGISTERED_COPPA',
		'GLOBAL_MODERATORS',
		'ADMINISTRATORS',
		'BOTS',
	);

	if (in_array(strtoupper($group_name), $default_groups))
	{
		$group_name = 'SMF - ' . $group_name;
	}

	return utf8_htmlspecialchars(smf_set_encoding($group_name));
}

/**
* Convert the avatar type constants
*/
function phpbb_avatar_type($type)
{
	switch ($type)
	{
		case 1:
			return AVATAR_UPLOAD;
		break;

		case 2:
			return AVATAR_REMOTE;
		break;

		case 3:
			return AVATAR_GALLERY;
		break;
	}

	return 0;
}

/*  Have a stab at generating a filename, from the physical file name.
*/
function smf_get_attachment_name($filename)
{
	$Ex = substr(strrchr($filename, '.'), 1);
	$name = str_replace('.' . $Ex, '', $filename);
	$name = str_replace('_', ' ', $name);
	return $name;
}

function phpbb_set_primary_group($group_id)
{
	global $convert_row;

	if ($group_id == 1)
	{
		return get_group_id('administrators');
	}
	
	else if ($group_id == 2)
	{
		return get_group_id('global_moderators');
	}
	
	else if ($group_id == 3)
	{
		return get_group_id('moderators');
	}

	else if ($convert_row['user_active'])
	{
		return get_group_id('registered');
	}

	return 0;
}



function smf_file_ext($filename)
{
	return substr(strrchr($filename,'.'),1);
}

function smf_file_mime($filename)
{
	return mimetype($filename);
}

function smf_is_thumbnail($type)
{
	if ($type)
	{
		$type = 1;
	}
	return (int) $type;
}

function smf_import_upload_avatar($source, $use_target = false, $user_id = false)
{
	if (empty($source) || preg_match('#^https?:#i', $source) || preg_match('#blank\.(gif|png)$#i', $source))
	{
		return;
	}

	global $convert, $phpbb_root_path, $config, $user;
	// Changed from avatar_path to avatars_upload_path
	if (!isset($convert->convertor['avatars_upload_path']))
	{
		$convert->p_master->error(sprintf($user->lang['CONV_ERROR_NO_AVATAR_PATH'], 'import_avatar()'), __LINE__, __FILE__);
	}
	
	if ($use_target === false && $user_id !== false)
	{
		$use_target = $config['avatar_salt'] . '_' . $user_id . '.' . substr(strrchr($source, '.'), 1);
	}

	$result = _import_check('avatars_upload_path', $source, $use_target);

	return ((!empty($user_id)) ? $user_id : $use_target) . '.' . substr(strrchr($source, '.'), 1);
}

function smf_avatar_get_user_upload()
{
	global $phpbb_root_path, $db, $src_db, $same_db, $convert, $config;

	$sql = "SELECT filename, id_member
		FROM {$convert->src_table_prefix}attachments 
		WHERE id_member > 0";

	if (!($result = $src_db->sql_query($sql)))
	{
		$convert->p_master->error('Error in obtaining user_avatar_data', __LINE__, __FILE__);
	}

	while ($row = $src_db->sql_fetchrow($result))
	{
		$row['id_member'] = smf_user_id($row['id_member']);
		$db->sql_query('UPDATE ' . USERS_TABLE . ' SET user_avatar = \'' . $db->sql_escape($row['filename']) . '\', user_avatar_type = ' . AVATAR_UPLOAD . ' WHERE user_id = ' . smf_user_id($row['id_member']));

		// Copy the file
		$upload_dir = $phpbb_root_path . $config['avatar_path'];

		$filename = $row['filename'];
		$file = $convert->options['forum_path'] . '/' . $convert->convertor['upload_path'] . $filename;

		$physical = $filename;

		copy($file, $upload_dir . '/' . $physical);
	}
	$src_db->sql_freeresult($result);

}
?>
