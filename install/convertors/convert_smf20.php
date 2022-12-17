<?php
/** 
* @package install
* @version $Id: convert_smf20RC2.php,v 1.02 2010/03/06 Dicky Exp $
* 
* @copyright (c) 2007 Andy Miller
* @copyright Some Changes (c) 2007 A_Jelly_Doughnut
* @copyright Rewritten for SMF 2.0 Beta & RC (c) 2008 Dicky
* @copyright Updated to work with SMF 2.0 stable (c) 2013 prototech
* @copyright Updated to work with phpBB 3.2 (c) 2017 Juju
* @contributions by phpBB Group, Kellanved
* @some code borrowed from SMF 1.0.x convertor to phpBB2.0.x (c) Slapshot134
* 
* @license http://opensource.org/licenses/gpl-license.php GNU Public License 
*   begin                : Wednesday, July 20, 2005
*   email                : 
*   author				  : Dicky <rfoote@tellink.net>
*	 					  : A_Jelly_Doughnut <support@jd1.clawz.com>
*	 					  : Based on phpBB2 -> phpBB3 convertor by the phpBB Development Team
*/

/**
* NOTE to potential convertor authors. Please use this file to get
* familiar with the structure since we added some bare explanations here.
*
* Since this file gets included more than once on one page you are not able to add functions to it.
* Instead use a functions_ file.
*
* @ignore
*/

/*
* Possible items to do later (minor things):
* 	- Add smilies instead of overwriting phpBB3 smilies. Ashe did it!  Will depend upon feedback
*/
$site_desc = 'My Forums';  //This is the text that will appear under your Forum Name.

if (!defined('IN_PHPBB'))
{
	exit;
}

include($phpbb_root_path . 'config.' . $phpEx);
unset($dbpasswd);

/**
* $convertor_data provides some basic information about this convertor which is
* used on the initial list of convertors and to populate the default settings
*/
$convertor_data = array(
	'forum_name'	=> 'Simple Machines Forum 2.0.x',
	'version'		=> '0.4.5',
	'phpbb_version'	=> '3.2.1',
	'author'		=> '<a href="https://www.phpbb.com/community/memberlist.php?mode=viewprofile&u=163542">Dicky</a>, <a href="https://www.phpbb.com/community/memberlist.php?mode=viewprofile&u=43818">A_Jelly_Doughnut</a>, <a href="https://www.phpbb.com/community/memberlist.php?mode=viewprofile&u=304651">prototech</a>, Juju',
	'dbms'			=> $dbms,
	'dbhost'		=> $dbhost,
	'dbport'		=> $dbport,
	'dbuser'		=> $dbuser,
	'dbpasswd'		=> '',
	'dbname'		=> $dbname,
	'table_prefix'	=> 'smf_',
	'forum_path'	=> '../forums',
	'author_notes'	=> '',
);

$current_time = time();

/**
* $tables is a list of the tables (minus prefix) which we expect to find in the
* source forum. It is used to guess the prefix if the specified prefix is incorrect
*/
$tables = array(
	'attachments',
	'ban_groups',
	'ban_items',
	'boards',
	'board_permissions',
	'calendar',
	'calendar_holidays',
	'categories',
	'collapsed_categories',
	'log_actions',
	'log_activity',
	'log_banned',
	'log_boards',
	'log_errors',
	'log_floodcontrol',
	'log_karma',
	'log_mark_read',
	'log_notify',
	'log_online',
	'pm_recipients',
	'personal_messages',
	'log_polls',
	'log_search_messages',
	'log_topics',
	'membergroups',
	'members',
	'messages',
	'moderators',
	'permissions',
	'personal_messages',
	'pm_recipients',
	'poll_choices',
	'polls',
	'sessions',
	'settings',
	'smileys',
	'themes',
	'topics'
);

/**
* $config_schema details how the board configuration information is stored in the source forum.
*
* 'table_format' can take the value 'file' to indicate a config file. In this case array_name
* is set to indicate the name of the array the config values are stored in
* 'table_format' can be an array if the values are stored in a table which is an assosciative array
* (as per phpBB 2.0.x)
* If left empty, values are assumed to be stored in a table where each config setting is
* a column (as per phpBB 1.x)
*
* In either of the latter cases 'table_name' indicates the name of the table in the database
*
* 'settings' is an array which maps the name of the config directive in the source forum
* to the config directive in phpBB3. It can either be a direct mapping or use a function.
* Please note that the contents of the old config value are passed to the function, therefore
* an in-built function requiring the variable passed by reference is not able to be used. Since
* empty() is such a function we created the function is_empty() to be used instead.
*/
	$config_schema = array(
		'table_name'		=> 'settings',
		'table_format'	=> array('variable' => 'value'),
		'settings'		=> array(   
			'allow_attachments'		=> 'attachmentEnable',
			'allow_avatar_local'	=> 'is_empty(avatar_allow_server_stored)',
			'allow_avatar_remote'	=> 'is_empty(avatar_allow_external_url)',
			'allow_avatar_upload'	=> 'is_empty(avatar_allow_upload)',
			'allow_bbcode'			=> 'enableBBC',
			'allow_namechange'		=> 'titlesEnable',
			'allow_pm_attach'		=> 'attachmentEnable',
			'allow_sig_bbcode'		=> 'enableBBC',
			'allow_sig_flash'		=> 'enableEmbeddedFlash',
			'allow_sig_smilies'		=> 'signature_allow_smileys',
			'attachment_quota'		=> 'attachmentDirSizeLimit',
			'auth_bbcode_pm'		=> 'enableBBC',
			'auth_flash_pm'			=> 'enableEmbeddedFlash',
			'avatar_max_height'		=> 'avatar_max_height_external',
			'avatar_max_width'		=> 'avatar_max_width_external',
			'coppa_enable'			=> 'requireAgreement',
//		  'default_dateformat'		=> 'smf_convert_dateformat(time_format)', // this work?
			'edit_time'				=> 'edit_wait_time',
			'enable_confirm'		=> 'not(disable_visual_verification)',
			'flood_interval'		=> 'spamWaitTime',
			'gzip_compress'			=> 'enableCompressedOutput',
			'hot_threshold'			=> 'hotTopicPosts',
			'load_online'			=> 'who_enabled',
			'load_onlinetrack'		=> 'onlineEnable',
			'max_login_attempts'	=> 'failed_login_threshold',
			'max_post_chars'		=> 'max_messageLength',
			'num_posts'				=> 'totalMessages',
			'num_topics'			=> 'totalTopics',
			'num_users'				=> 'memberCount',
			'posts_per_page'		=> 'defaultMaxMessages',
			'record_online_date'	=> 'mostDate', // || mostOnlineUpdated
			'record_online_users'	=> 'mostOnline',
			'require_activation'	=> 'registration_method',
			'session_length'		=> 'databaseSession_lifetime',
			'smtp_host'				=> 'smtp_host',
			'smtp_password'			=> 'smtp_password',
			'smtp_port'				=> 'smtp_port',
			'smtp_username'			=> 'smtp_username',
			'topics_per_page'		=> 'defaultMaxTopics',
		)
	);

/**
* $test_file is the name of a file which is present on the source
* forum which can be used to check that the path specified by the 
* user was correct
*/
$test_file = 'Settings.php';

/**
* If this is set then we are not generating the first page of information but getting the conversion information.
*/
if (!$get_info)
{
	// Test to see if file_hashing is installed on the source forum
	// If it is, we will convert this data as well
	$src_db->sql_return_on_error(true);

	$sql = "SELECT file_hash
		FROM {$convert->src_table_prefix}attachments";
	$result = $src_db->sql_query_limit($sql, 1);

	if ($result && $row = $src_db->sql_fetchrow($result))
	{
		// Here the constant is defined
		define('FILE_HASH', true);

		$src_db->sql_freeresult($result);
	}
	else if ($result)
	{
		$src_db->sql_freeresult($result);
	}

	// Test to see if the Gender MOD is installed on the destination board
	$db->sql_return_on_error(true);

	$sql = 'SELECT user_gender
		FROM ' . USERS_TABLE;
	$result = $db->sql_query_limit($sql, 1);

	if ($result && $row = $db->sql_fetchrow($result))
	{
		// Here the constant is defined
		define('MOD_GENDER', true);

		// No tables need to be added

		$db->sql_freeresult($result);
	}
	else if ($result)
	{
		$db->sql_freeresult($result);
	}

	$db->sql_return_on_error(false);

	/**
	* Tests for further MODs can be included here.
	* Please use constants for this, prefixing them with MOD_
	*/

	$src_db->sql_return_on_error(false);

	// Overwrite maximum avatar width/height
	@define('DEFAULT_AVATAR_X_CUSTOM', get_config_value('avatar_max_width_external'));
	@define('DEFAULT_AVATAR_Y_CUSTOM', get_config_value('avatar_max_height_external'));

	@define('USERCONV_TABLE', $table_prefix . 'userconv');
/**
*	Description on how to use the convertor framework.
*
*	'schema' Syntax Description
*		-> 'target'			=> Target Table. If not specified the next table will be handled
*		-> 'primary'		=> Primary Key. If this is specified then this table is processed in batches
*		-> 'query_first'	=> array('target' or 'src', Query to execute before beginning the process
*								(if more than one then specified as array))
*		-> 'function_first'	=> Function to execute before beginning the process (if more than one then specified as array)
*								(This is mostly useful if variables need to be given to the converting process)
*		-> 'test_file'		=> This is not used at the moment but should be filled with a file from the old installation
*
*		// DB Functions
*		'distinct'	=> Add DISTINCT to the select query
*		'where'		=> Add WHERE to the select query
*		'group_by'	=> Add GROUP BY to the select query
*		'left_join'	=> Add LEFT JOIN to the select query (if more than one joins specified as array)
*		'having'	=> Add HAVING to the select query
*
*		// DB INSERT array
*		This one consist of three parameters
*		First Parameter: 
*							The key need to be filled within the target table
*							If this is empty, the target table gets not assigned the source value
*		Second Parameter:
*							Source value. If the first parameter is specified, it will be assigned this value.
*							If the first parameter is empty, this only gets added to the select query
*		Third Parameter:
*							Custom Function. Function to execute while storing source value into target table. 
*							The functions return value get stored.
*							The function parameter consist of the value of the second parameter.
*
*							types:
*								- empty string == execute nothing
*								- string == function to execute
*								- array == complex execution instructions
*		
*		Complex execution instructions:
*		@todo test complex execution instructions - in theory they will work fine
*
*							By defining an array as the third parameter you are able to define some statements to be executed. The key
*							is defining what to execute, numbers can be appended...
*
*							'function' => execute function
*							'execute' => run code, whereby all occurrences of {VALUE} get replaced by the last returned value.
*										The result *must* be assigned/stored to {RESULT}.
*							'typecast'	=> typecast value
*
*							The returned variables will be made always available to the next function to continue to work with.
*
*							example (variable inputted is an integer of 1):
*
*							array(
*								'function1'		=> 'increment_by_one',		// returned variable is 2
*								'typecast'		=> 'string',				// typecast variable to be a string
*								'execute'		=> '{RESULT} = {VALUE} . ' is good';', // returned variable is '2 is good'
*								'function2'		=> 'replace_good_with_bad',				// returned variable is '2 is bad'
*							),
*
*/

	$convertor = array(
		'test_file'		=>	'Settings.php',
		'avatar_path'	=>	smf_absolute_to_path(get_config_value('avatar_url')) . '/',
		'avatars_path'	=>	smf_absolute_to_path(get_config_value('avatar_url')) . '/',
		'avatar_gallery_path'	=>	smf_absolute_to_path(get_config_value('avatar_url')),
		'avatars_upload_path'	=>	get_config_value('attachmentUploadDir').'/', // used for smf_import_upload_avatar;
		'smilies_path'	=>	smf_absolute_to_path('Smileys/default/'),
		'theme_default'	=>	get_config_value('theme_guests'),
		'upload_path'	=>	get_config_value('attachmentUploadDir').'/',

		// We empty some tables to have clean data available
		'query_first'	=> array(
			array('target', $convert->truncate_statement . SEARCH_RESULTS_TABLE),
			array('target', $convert->truncate_statement . SEARCH_WORDLIST_TABLE),
			array('target', $convert->truncate_statement . SEARCH_WORDMATCH_TABLE),
			array('target', $convert->truncate_statement . LOG_TABLE),
		),
		
		// phpBB2 allowed some similar usernames to coexist which would have the same
		// username_clean in phpBB3 which is not possible, so we'll give the admin a list
		// of user ids and usernames and let him deicde what he wants to do with them
		'execute_first'	=> '
			smf_conv_file_settings();
			create_userconv_table();
			import_avatar_gallery();
			smf_insert_forums();
			smf_import_banlist();
			add_bbcodes();
			smf_add_login_field();
			set_config("auth_method", "smf");
		',

		'execute_last'	=> array('
			add_bots();
		', '
			convert_words();
		', '
			convert_zebra();
		', '
			update_folder_pm_count();
		', '
			update_unread_count();
		', '
			smf_convert_authentication(\'start\');
		', '
			smf_convert_authentication(\'first\');
		', '
			smf_convert_authentication(\'second\');
		', '
			smf_convert_authentication(\'third\');
		'),

		'schema' => array(

			array(
				'target'	=> USERCONV_TABLE,
				'query_first'   => array('target', $convert->truncate_statement . USERCONV_TABLE),


				array('user_id',			'members.id_member', 	''),
				array('username_clean',		'members.member_name',	array('function1' => 'smf_set_encoding', 'function2' => 'utf8_clean_string')),
			),

			array(
				'target'    => ATTACHMENTS_TABLE,
				'primary'   => 'attachments.id_attach',
				'query_first'   => array('target', $convert->truncate_statement . ATTACHMENTS_TABLE),


				array('attach_id',			'attachments.id_attach',	''),
				array('post_msg_id',		'attachments.id_msg',		''),
				array('topic_id',			'messages.id_topic',		''),
				array('in_message',		0,							''),
				array('is_orphan',			0,							''),
				array('poster_id',			'messages.id_member AS attach_poster',	'smf_user_id'),
				array('physical_filename',	'attachments.filename',		'smf_import_attachment'),
				array('real_filename',		'attachments.filename',		''),
				array('download_count',	'attachments.downloads',	''),
				array('attach_comment',	'',							''),
				array('extension',			'attachments.filename',		'smf_get_attachment_ext'),
				array('mimetype',			'attachments.filename',		'mimetype'),
				array('filesize',			'attachments.size',			''),
				array('filetime',			'messages.poster_time',		''),
				array('thumbnail',			'attachments.id_thumb',		'is_positive'),
				array('',					((defined('FILE_HASH')) ? 'attachments.file_hash' : 0), ''),

				'where'		=> 'messages.id_msg = attachments.id_msg AND attachments.attachment_type = 0',
				'group_by'	=> 'attachments.id_attach'
				
			),

			array(
				'target'		=> RANKS_TABLE,
				'execute_first'	=> 'smf_check_username_collisions();',
				'query_first'	=> array('target', $convert->truncate_statement . RANKS_TABLE),
				'autoincrement'	=> 'rank_id',

				array('rank_id',				'membergroups.id_group',		''),
				array('rank_title',			'membergroups.group_name',		array('function1' => 'smf_set_encoding', 'function2' => 'utf8_htmlspecialchars')),
				array('rank_min',				'membergroups.min_posts',		'smf_rank_min'),
				array('rank_special',			1,								''),
				array('rank_image',			'membergroups.stars',			'smf_rank_image'),

				'where'		=>	'membergroups.min_posts < 0'
			),
			
			array(
				'target'		=> RANKS_TABLE,

				array('rank_id',				'membergroups.id_group',		''),
				array('rank_title',			'membergroups.group_name',		array('function1' => 'smf_set_encoding', 'function2' => 'utf8_htmlspecialchars')),
				array('rank_min',				'membergroups.min_posts',		''),
				array('rank_special',			0,								''),
				array('rank_image',			'membergroups.stars',			'smf_rank_image'),

				'where'		=>	'membergroups.min_posts > -1',
				'order_by'	=>	'membergroups.id_group ASC',
			),

			array(
				'target'		=> ICONS_TABLE,
				'query_first'	=> array('target', $convert->truncate_statement . ICONS_TABLE),

				array('icons_id',				'message_icons.id_icon',		''),
				array('icons_url',				'message_icons.filename',		'smf_message_icon'),
				array('icons_width',			'message_icons.filename',		'smf_icon_width'),
				array('icons_height',			'message_icons.filename',		'smf_icon_height'),
				array('icons_order',			'message_icons.icon_order',		''),
				array('display_on_posting',	1,								''),
			),

			array(
				'target'		=> TOPICS_TABLE,
				'query_first'	=> array('target', $convert->truncate_statement . TOPICS_TABLE),
				'primary'		=> 'topics.id_topic',
				'autoincrement'	=> 'topic_id',

				array('topic_id',				'topics.id_topic',			''),
				array('forum_id',				'topics.id_board',			''),
				array('icon_id',				'messages.icon',			'smf_icon_id'),
				array('topic_title',			'messages.subject',			'smf_set_encoding'),
				array('topic_poster',			'messages.id_member AS poster_id',	'smf_user_id'),
// resync does this				array('topic_first_poster_name', 'messages.poster_name',	''),
				array('topic_time',			'messages.poster_time',		''),
				array('topic_views',			'topics.num_views',			''),
				array('topic_posts_approved',			'topics.num_replies',		''),
				array('topic_posts_unapproved',			'topics.unapproved_posts',		''),
				//array('topic_replies_real',	'topics.num_replies',		''),
				array('topic_first_post_id',	'topics.id_first_msg',		''),
				array('topic_last_post_id',	'topics.id_last_msg',		''),
				array('topic_moved_id',		0,							''),
				array('topic_status',			'topics.locked',			''),
				array('topic_type',			'topics.is_sticky',			''),
				array('topic_visibility',			'topics.approved',			''),
				array('topic_attachment',		0,							''),
				array('topic_last_view_time',	$current_time,				''),

				array('poll_title',			'polls.question',			array('function1' => 'null_to_str', 'function2' => 'smf_set_encoding')),
				array('poll_start',			'topics.id_topic',			'smf_topic_has_poll'), //'null_to_zero'),
				array('poll_length',			'topics.id_poll',			'smf_poll_expire_time'), // 'polls.expireTime - messages.poster_time'),
				array('poll_max_options',		1,							''),
				array('poll_vote_change',		'polls.change_vote',							''),

				'left_join'	=> 'topics LEFT JOIN messages ON messages.id_msg = topics.id_first_msg',
				'left_join'	=> 'topics LEFT JOIN polls ON topics.id_poll = polls.id_poll',
				'where'		=> 'messages.id_topic = topics.id_topic AND messages.id_msg = topics.id_first_msg',
			),

			array(
				'target'		=> TOPICS_WATCH_TABLE,
				'primary'		=> 'log_notify.id_topic',
				'query_first'	=> array('target', $convert->truncate_statement . TOPICS_WATCH_TABLE),

				array('topic_id',				'log_notify.id_topic',		''),
				array('user_id',				'log_notify.id_member',		'smf_user_id'),
				array('notify_status',			'log_notify.sent',			''),

				'where'		=> 'log_notify.id_topic <> 0',
			),

			array(
				'target'		=> FORUMS_WATCH_TABLE,
				'primary'		=> 'log_notify.id_board',
				'query_first'	=> array('target', $convert->truncate_statement . FORUMS_WATCH_TABLE),

				array('forum_id',		'log_notify.id_board',	''),
				array('user_id',		'log_notify.id_member',	'smf_user_id'),
				array('notify_status',	'log_notify.sent',		''),

				'where'		=> 'log_notify.id_topic = 0',
			),

			array(
				'target'		=> SMILIES_TABLE,
				'query_first'	=> array('target', $convert->truncate_statement . SMILIES_TABLE),
				'autoincrement'	=> 'smiley_id',

				array('smiley_id',				'smileys.id_smiley',			''),
				array('code',					'smileys.code',					''),
				array('emotion',				'smileys.description',			'smf_set_encoding'),
				array('smiley_url',			'smileys.filename',				'import_smiley'),
				array('smiley_width',			'smileys.filename',				'get_smiley_width'),
				array('smiley_height',			'smileys.filename',				'get_smiley_height'),
				array('smiley_order',			'smileys.smiley_order',			''),
				array('display_on_posting',	'smileys.id_smiley',			'get_smiley_display'),

				'order_by'		=> 'smileys.id_smiley ASC',
			),

			array(
				'target'		=> POLL_OPTIONS_TABLE,
				'primary'		=> 'poll_choices.id_poll',
				'query_first'	=> array('target', $convert->truncate_statement . POLL_OPTIONS_TABLE),

				array('poll_option_id',		'poll_choices.id_choice',		''),
				array('topic_id',				'topics.id_topic',				''), // Need to convert poll_id to topic_id
				array('poll_option_text',		'poll_choices.label',			'smf_set_encoding'),
				array('poll_option_total',		'poll_choices.votes',			''),

				'where'			=> 'topics.id_poll = poll_choices.id_poll',
			),


			array(
				'target'		=> POLL_VOTES_TABLE,
				//'primary'		=> '.topic_id',
				'query_first'	=> array('target', $convert->truncate_statement . POLL_VOTES_TABLE),

				array('topic_id',			'log_polls.id_poll',		'smf_poll_topic_id'),
				array('poll_option_id',	'log_polls.id_choice',		''),
				array('vote_user_id',		'log_polls.id_member',		'smf_user_id'),
				array('vote_user_ip',		'',							''),

				'order_by'		=> 'log_polls.id_poll ASC, log_polls.ID_member ASC, log_polls.id_choice ASC'
			),

			array(
				'target'		=> POSTS_TABLE,
				'primary'		=> 'messages.id_msg',
				'autoincrement'	=> 'post_id',
				'query_first'	=> array('target', $convert->truncate_statement . POSTS_TABLE),
				'execute_first'	=> '
					$config["max_post_chars"] = -1;
					$config["max_quote_depth"] = 0;
					set_config("max_post_font_size", 1000);
				',

				array('post_id',				'messages.id_msg',				''),
				array('topic_id',				'messages.id_topic',			''),
				array('forum_id',				'messages.id_board',			''),
				array('poster_id',				'messages.id_member',			'smf_user_id'),
				array('icon_id',				'messages.icon',				'smf_icon_id'),
				array('poster_ip',				'messages.poster_ip',			''),
				array('post_time',				'messages.poster_time',			''),
				array('post_visibility',				'messages.approved',			''),
				array('post_reported',			'log_reported.closed',			'smf_post_reported'),
				array('enable_bbcode',			'enableBBC',					array('function1' => 'get_config_value', 'typecast' => 'int')),
				array('enable_smilies',		'messages.smileys_enabled',		''),
				array('enable_sig',			1,								''),
				array('enable_magic_url',		1,								''),
				array('post_username',			'messages.poster_name',			'smf_set_encoding'),
				array('post_subject',			'messages.subject',				'smf_set_encoding'),
				array('post_edit_count',		'messages.modified_time',		'is_positive'),
				array('post_edit_time',		'messages.modified_time',		array('typecast' => 'int')),
				array('post_edit_reason',		'',								''),
				array('post_edit_user',		'messages.modified_name',		array('smf_get_userid_from_username', 'typecast' => 'int')),
				array('post_text',				'messages.body',				'smf_prepare_message'),
				array('post_attachment',		'messages.id_msg',				'smf_post_has_attachment'),
				array('bbcode_bitfield',		'',								'get_bbcode_bitfield'),
				array('bbcode_uid',			'',								'smf_get_bbcode_uid'),
				array('post_checksum',			'',								''),

				'left_join'		=> 'messages LEFT JOIN log_reported ON (messages.id_msg = log_reported.id_msg)',

			),

			array(
				'target'		=> REPORTS_TABLE,
				'primary'		=> 'log_reported_comments.id_comment',
				'autoincrement'	=> 'report_id',
				'query_first'	=> array('target', $convert->truncate_statement . REPORTS_TABLE),

				array('report_id',				'log_reported_comments.id_comment',	''),
				array('reason_id',				4,									''), // Default to "other"
				array('post_id',				'log_reported.id_msg',				''),
				array('pm_id',					0,									''),
				array('user_id',				'log_reported_comments.id_member',	'smf_user_id'),
				array('user_notify',			0,									''),
				array('',						'log_reported.time_updated AS last_report', ''),
				array('report_closed',			'log_reported.closed',				'smf_report_closed'),
				array('report_time',			'log_reported_comments.time_sent',	''),
				array('report_text',			'log_reported_comments.comment',	'smf_set_encoding'),

				'left_join'		=> 'log_reported_comments LEFT JOIN log_reported ON (log_reported_comments.id_report = log_reported.id_report)'
			),

			// --- PM's --------------------------------------------------------------
			array(
			'target'      => PRIVMSGS_TABLE,
			'primary'   =>   'personal_messages.id_pm',
				'query_first'	=> array(
					array('target', $convert->truncate_statement . PRIVMSGS_TABLE),
					array('target', $convert->truncate_statement . PRIVMSGS_RULES_TABLE)
			),

			'execute_first'   => '
				$config["max_post_chars"] = -1;
				$config["max_quote_depth"] = 0;
			',

			array('msg_id',				'personal_messages.id_pm',		''),
			array('root_level',			0,								''),
			array('author_id',				'personal_messages.id_member_from',	'smf_user_id'),
			array('icon_id',				0,								''),
			array('author_ip',				'',								''), // Could be gotten from registration IP
			array('message_time',			'personal_messages.msgtime',	''),
			array('enable_bbcode',			'enableBBC',					array('function1' => 'get_config_value', 'typecast' => 'int')),
			array('enable_smilies',		1,								''),
			array('enable_magic_url',		1,								''),
			array('enable_sig',			1,								''),
			array('message_subject',		'personal_messages.subject',	'smf_set_encoding'),
			array('message_text',			'personal_messages.body',		'smf_prepare_message'),
			array('message_edit_reason',	'',								''),
			array('message_edit_user',		0,								''),
			array('message_edit_time',		0,								''),
			array('message_edit_count',	0,								''),
			array('message_attachment',	0,								''),

			array('bbcode_uid',			'personal_messages.msgtime AS post_time',	'smf_get_bbcode_uid'),
			array('bbcode_bitfield',		'',								'get_bbcode_bitfield'),
			array('',						'pm_recipients.bcc',			''),
			array('to_address',			'pm_recipients.id_member',		'smf_to_address'),
			array('bcc_address',			'personal_messages.id_pm',		'smf_to_address'),

			'where'		=> 'pm_recipients.id_pm = personal_messages.id_pm'
			),
			// ----------------------------------------------------------------

			// Inbox
			array(
				'target'		=> PRIVMSGS_TO_TABLE,
				'primary'		=> 'pm_recipients.id_pm',
				'query_first'	=> array('target', $convert->truncate_statement . PRIVMSGS_TO_TABLE),

				array('msg_id',			'pm_recipients.id_pm',					''),
				array('user_id',			'pm_recipients.id_member',				'smf_user_id'),
				array('author_id',			'personal_messages.id_member_from',		'smf_user_id'),  // I don't have an author. Do a query from the smf_personal_messages table? Join smf_personal_messages on id_pm?
				array('pm_deleted',		'pm_recipients.deleted',				''),
				array('pm_new',			'pm_recipients.is_read',				'not'),
				array('pm_unread',			'pm_recipients.is_read',				'not'),
				array('pm_replied',		0,										''),
				array('pm_marked',			0,										''),
				array('pm_forwarded',		0,										''),
				array('folder_id',			PRIVMSGS_INBOX,							''),

				'left_join' =>  'pm_recipients LEFT JOIN personal_messages ON personal_messages.id_pm = pm_recipients.id_pm',
			),
			

			// Outbox & Sentbox
			array(
				'target'		=> PRIVMSGS_TO_TABLE,
				'primary'		=> 'personal_messages.id_pm',

				array('msg_id',				'personal_messages.id_pm',				''),
				array('user_id',				'personal_messages.id_member_from',		'smf_user_id'),
				array('author_id',				'personal_messages.id_member_from',		'smf_user_id'),
				array('pm_deleted',			'personal_messages.deleted_by_sender',	''),
				array('pm_new',				0,										''),
				array('pm_unread',				0,										''),
				array('pm_replied',			0,										''),
				array('pm_marked',				0,										''),
				array('pm_forwarded',			0,										''),
				array('folder_id',				'',										'smf_pm_box'),
			),

			array(
				'target'		=> GROUPS_TABLE,
				'autoincrement'	=> 'group_id',
				'query_first'	=> array('target', $convert->truncate_statement . GROUPS_TABLE),

				array('group_id',				'membergroups.id_group',			''),
				array('group_type',			GROUP_CLOSED,						''),
				array('group_colour',			'membergroups.online_color',		'smf_group_colour'),
				array('group_display',			0,									''),
				array('group_legend',			0,									''),
				array('group_name',			'membergroups.group_name',			'phpbb_convert_group_name'),
				array('group_desc',			'membergroups.description',			array('function1' => 'smf_set_encoding', 'function2' => 'utf8_htmlspecialchars')),

				'left_join'	=> 'membergroups LEFT JOIN members AS members ON (members.id_group = membergroups.id_group)',
				'order_by'	=> 'membergroups.id_group ASC'
			),

			array(
				'target'		=> USER_GROUP_TABLE,
				'query_first'	=> array('target', $convert->truncate_statement . USER_GROUP_TABLE),
				'execute_first'	=> '
					add_default_groups();
				',

				array('group_id',		'membergroups.id_group',			''),
				array('user_id',		'members.id_member',				'smf_user_id'),
				array('group_leader',	0,									''),
				array('user_pending',	0,									''),
				'where'		=>	'membergroups.id_group = members.id_group AND members.id_member > 1'
			),

			array(
				'target'		=> USER_GROUP_TABLE,

				array('group_id',		'membergroups.id_group',			''),
				array('user_id',		'members.id_member',				'smf_user_id'),
				array('group_leader',	'0',								''),
				array('user_pending',	'0',
								''),
				'where'		=>	'membergroups.id_group IN(members.additional_groups) AND members.id_member > 1',
				'order_by'	=>	'membergroups.id_group ASC'
			),

			array(
				'target'		=> USERS_TABLE,
				'primary'		=> 'members.id_member',
				'autoincrement'	=> 'user_id',
				'query_first'	=> array(
					array('target', 'DELETE FROM ' . USERS_TABLE . ' WHERE user_id <> ' . ANONYMOUS),
					array('target', $convert->truncate_statement . BOTS_TABLE)
				),

				'execute_last'	=> '
					remove_invalid_users();
				',

				array('user_id',				'members.id_member',				'smf_user_id'),
				array('',						'members.id_member AS poster_id',	'smf_user_id'),
				array('user_type',				'members.is_activated',				'set_user_type'),
				array('group_id',				'members.id_group',					'smf_set_primary_group'),
				array('user_ip',				'members.member_ip',				''),
				array('user_regdate',			'members.date_registered',			''),
				array('username',				'members.real_name',				'smf_set_encoding'), // recode to utf8 with default lang
				array('username_clean',		'members.real_name',				array('function1' => 'smf_set_encoding', 'function2' => 'utf8_clean_string')),
				array('login_name',			'members.member_name',				'smf_set_encoding'),
				array('user_password',			'members.passwd',					''),
				array('user_passchg',		1,									''),
				array('user_passwd_salt',		'members.password_salt',			''),
				array('user_posts',			'members.posts',					''),
				array('user_email',			'members.email_address',			'utf8_strtolower'),
				array('user_email_hash',		'members.email_address',			'gen_email_hash'),
				array('user_birthday',			'members.birthdate',				'smf_get_birthday'),
//				array('user_gender',			((defined('MOD_GENDER')) ? 'members.user_gender' : ''),	''),
				array((defined('MOD_GENDER') ? 'user_gender' : ''),	'members.gender',	''),
				array('user_lastvisit',		'members.last_login',				''),
				array('user_lastmark',			'members.date_registered',			''),
				array('user_lang',				$config['default_lang'],			''),
				array('',						'members.lngfile',					''),
				array('user_timezone',			'members.time_offset',				''),
				array('user_dateformat',		'members.time_format',				array('function1' => 'smf_time_format', 'function2' => 'smf_set_encoding', 'function3' => 'fill_dateformat')),
				array('user_inactive_reason',	'',									'smf_inactive_reason'),
				array('user_inactive_time',	'',									'smf_inactive_time'),
				array('user_sig',				'members.signature',				'smf_prepare_message'),
				array('user_sig_bbcode_uid',	'',									'smf_get_bbcode_uid'),
				array('user_sig_bbcode_bitfield',	'',								'get_bbcode_bitfield'),
				//array('user_interests',		'members.personal_text',			'smf_set_encoding'),
				//array('user_occ',				'',									'smf_set_encoding'),
				//array('user_website',			'members.website_url',				'validate_website'),
				array('user_jabber',			'',									''),
				//array('user_msnm',				'members.msn',						'smf_set_encoding'),
				//array('user_yim',				'members.yim',						'smf_set_encoding'),
				//array('user_aim',				'members.aim',						'smf_set_encoding'),
				//array('user_icq',				'members.icq',						'smf_set_encoding'),
				//array('user_from',				'members.location',					'smf_set_encoding'),
				array('user_rank',				'membergroups.id_group',			array('typecast' => 'int')),
				array('user_permissions',		'',									''),
				array('user_avatar',			'members.avatar',					'smf_avatar'),
				array('',						'members.avatar AS avatar_type',	'smf_avatar_type'),
				array('user_avatar_type',		'members.avatar',					'smf_avatar_type'),
				array('user_avatar_width',		'members.avatar',					'smf_get_avatar_width'),
				array('user_avatar_height',	'members.avatar',					'smf_get_avatar_height'),

				array('user_new_privmsg',		'members.unread_messages',			''),
				array('user_unread_privmsg',	'members.unread_messages',			''),
				array('user_last_privmsg',		'',									'smf_user_last_privmsg'),
				array('user_notify',			1,									''),
				array('user_notify_pm',		1,									''),
				array('user_notify_type',		NOTIFY_EMAIL,						''),
				array('user_allow_viewemail',	'members.hide_email',				''),
				array('user_actkey',			'',									''),
				array('user_newpasswd',		'',									''),
				array('user_style',			$config['default_style'],			''),
				array('user_options',			'',									'set_user_options'),
				array('user_pass_convert',		1,									''),

			'where'		=>	'members.id_member > 0',
			'left_join'	=>	'members LEFT JOIN membergroups ON members.id_group = membergroups.id_group',

			'order_by'	=>	'members.id_member ASC'
			),

			array(
				'target'		=> TOPICS_TRACK_TABLE,
				'query_first'	=> array('target', $convert->truncate_statement . TOPICS_TRACK_TABLE),
				
				array('user_id',				'log_topics.id_member',				'smf_user_id'),
				array('topic_id',				'log_topics.id_topic',				''),
				array('forum_id',				'messages.id_board',				''),
				array('mark_time',				'messages.poster_time',				''),

				'left_join'			=> 'log_topics LEFT JOIN messages ON (log_topics.id_msg = messages.id_msg)',
			),

			array(
				'target'		=> FORUMS_TRACK_TABLE,
				'query_first'	=> array('target', $convert->truncate_statement . FORUMS_TRACK_TABLE),

				array('user_id',				'log_mark_read.id_member',			'smf_user_id'),
				array('forum_id',				'log_mark_read.id_board',			''),
				array('mark_time',				'messages.poster_time',				''),

				'left_join'		=> 'log_mark_read LEFT JOIN messages ON (log_mark_read.id_msg = messages.id_msg)',
			),

			array(
				'target'		=> PROFILE_FIELDS_DATA_TABLE,
				'primary'		=> 'members.id_member',
				'query_first'	=> array('target', $convert->truncate_statement . PROFILE_FIELDS_DATA_TABLE),

				array('user_id',				'members.id_member',			'smf_user_id'),
				array('pf_phpbb_interests',		'members.personal_text',			'smf_set_encoding'),
				array('pf_phpbb_occupation',				'',									'smf_set_encoding'),
				array('pf_phpbb_website',			'members.website_url',				'validate_website'),
				//array('pf_phpbb_msnm',				'members.msn',						'smf_set_encoding'),
				array('pf_phpbb_yahoo',				'members.yim',						'smf_set_encoding'),
				array('pf_phpbb_aim',				'members.aim',						'smf_set_encoding'),
				array('pf_phpbb_icq',				'members.icq',						'smf_set_encoding'),
				array('pf_phpbb_location',				'members.location',					'smf_set_encoding'),
			),
		),
	);
}
?>
