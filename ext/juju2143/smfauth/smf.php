<?php
/**
* Database auth plug-in for phpBB3
*
* Authentication plug-ins is largely down to Sergey Kanareykin, our thanks to him.
*
* This is for authentication via the integrated user table
*
* @package login
* @version $Id$
* @version: auth_smf.php 1.0.0 2010/03/06 Dicky
* @version: auth_smf.php 2.0.0 2017/10/01 Juju
* @copyright (c) 2005 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace juju2143\smfauth;

/**
 * Database authentication provider for phpBB3
 * This is for authentication via the integrated user table
 */
class smf extends \phpbb\auth\provider\base
{
	/**
	* phpBB passwords manager
	*
	* @var \phpbb\passwords\manager
	*/
	protected $passwords_manager;

	/**
	* DI container
	*
	* @var \Symfony\Component\DependencyInjection\ContainerInterface
	*/
	protected $phpbb_container;

	/**
	 * SMF Authentication Constructor
	 *
	 * @param	\phpbb\db\driver\driver_interface		$db		Database object
	 * @param	\phpbb\config\config		$config		Config object
	 * @param	\phpbb\passwords\manager	$passwords_manager		Passwords manager object
	 * @param	\phpbb\user			$user		User object
	 * @param	\Symfony\Component\DependencyInjection\ContainerInterface $phpbb_container DI container
	 */
	public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\config\config $config, \phpbb\passwords\manager $passwords_manager, \phpbb\user $user, \Symfony\Component\DependencyInjection\ContainerInterface $phpbb_container)
	{
		$this->db = $db;
		$this->config = $config;
		$this->passwords_manager = $passwords_manager;
		$this->user = $user;
		$this->phpbb_container = $phpbb_container;
	}

	/**
	* Login function
	*
	* @param string $username
	* @param string $password
	* @return array				A associative array of the format
	*							array(
	*								'status' => status constant
	*								'error_msg' => string
	*								'user_row' => array
	*							)
	*/
	public function login($username, $password)
	{
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
		// do not allow empty password
		if (!$password)
		{
			return array(
				'status'	=> LOGIN_ERROR_PASSWORD,
				'error_msg'	=> 'NO_PASSWORD_SUPPLIED',
				'user_row'	=> array('user_id' => ANONYMOUS),
			);
		}

		if (!$username)
		{
			return array(
				'status'	=> LOGIN_ERROR_USERNAME,
				'error_msg'	=> 'LOGIN_ERROR_USERNAME',
				'user_row'	=> array('user_id' => ANONYMOUS),
			);
		}

		$username_clean = utf8_clean_string($username);

		$sql = 'SELECT user_id, username, user_password, user_passchg, user_pass_convert, user_email, user_type, user_login_attempts, login_name, user_passwd_salt
			FROM ' . USERS_TABLE . "
			WHERE username_clean = '" . $this->db->sql_escape($username_clean) . "'";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if (($this->user->ip && !$this->config['ip_login_limit_use_forwarded']) ||
			($this->user->forwarded_for && $this->config['ip_login_limit_use_forwarded']))
		{
			$sql = 'SELECT COUNT(*) AS attempts
				FROM ' . LOGIN_ATTEMPT_TABLE . '
				WHERE attempt_time > ' . (time() - (int) $this->config['ip_login_limit_time']);
			if ($this->config['ip_login_limit_use_forwarded'])
			{
				$sql .= " AND attempt_forwarded_for = '" . $this->db->sql_escape($this->user->forwarded_for) . "'";
			}
			else
			{
				$sql .= " AND attempt_ip = '" . $this->db->sql_escape($this->user->ip) . "' ";
			}

			$result = $this->db->sql_query($sql);
			$attempts = (int) $this->db->sql_fetchfield('attempts');
			$this->db->sql_freeresult($result);

			$attempt_data = array(
				'attempt_ip'			=> $this->user->ip,
				'attempt_browser'		=> trim(substr($this->user->browser, 0, 149)),
				'attempt_forwarded_for'	=> $this->user->forwarded_for,
				'attempt_time'			=> time(),
				'user_id'				=> ($row) ? (int) $row['user_id'] : 0,
				'username'				=> $username,
				'username_clean'		=> $username_clean,
			);
			$sql = 'INSERT INTO ' . LOGIN_ATTEMPT_TABLE . $this->db->sql_build_array('INSERT', $attempt_data);
			$result = $this->db->sql_query($sql);
		}
		else
		{
			$attempts = 0;
		}

		if (!$row)
		{
			if ($this->config['ip_login_limit_max'] && $attempts >= $this->config['ip_login_limit_max'])
			{
				return array(
					'status'		=> LOGIN_ERROR_ATTEMPTS,
					'error_msg'		=> 'LOGIN_ERROR_ATTEMPTS',
					'user_row'		=> array('user_id' => ANONYMOUS),
				);
			}

			return array(
				'status'	=> LOGIN_ERROR_USERNAME,
				'error_msg'	=> 'LOGIN_ERROR_USERNAME',
				'user_row'	=> array('user_id' => ANONYMOUS),
			);
		}

		$show_captcha = ($this->config['max_login_attempts'] && $row['user_login_attempts'] >= $this->config['max_login_attempts']) ||
			($this->config['ip_login_limit_max'] && $attempts >= $this->config['ip_login_limit_max']);

		// If there are too much login attempts, we need to check for an confirm image
		// Every auth module is able to define what to do by itself...
		if ($show_captcha)
		{
			/* @var $captcha_factory \phpbb\captcha\factory */
			$captcha_factory = $this->phpbb_container->get('captcha.factory');
			$captcha = $captcha_factory->get_instance($this->config['captcha_plugin']);
			$captcha->init(CONFIRM_LOGIN);
			$vc_response = $captcha->validate($row);
			if ($vc_response)
			{
				return array(
					'status'		=> LOGIN_ERROR_ATTEMPTS,
					'error_msg'		=> 'LOGIN_ERROR_ATTEMPTS',
					'user_row'		=> $row,
				);
			}
			else
			{
				$captcha->reset();
			}

		}

		// If the password convert flag is set we need to convert it
		if ($row['user_pass_convert']>0)
		{
			$old_format = htmlspecialchars_decode($password);
			$other_passwords = array();
			$other_passwords[] = sha1(strtolower($row['login_name']) . $old_format); // SMF 1.1.x, SMF 2.0.x
			$other_passwords[] = sha1($old_format); // Wotlab Burning Board
			$other_passwords[] = md5($old_format);
			$other_passwords[] = md5($row['user_passwd_salt'] . md5($old_format)); // MyBB
			$other_passwords[] = md5(md5($row['user_passwd_salt']) . md5($old_format));
			$other_passwords[] = md5(md5($old_format) . $row['user_passwd_salt']); // vB3
			//die(strtolower($row['login_name']) . $old_format . ", " . $row['user_password']);
			//die((password_verify(strtolower($row['login_name']) . $old_format, $row['user_password']))?"true":"false");
			if (in_array($row['user_password'], $other_passwords) || password_verify(strtolower($row['login_name']) . $old_format, $row['user_password'])) // SMF 2.0 bcrypt mod
			{
				$phpbb_hash = phpbb_hash($password);

				// we have an SMF password.  change it into a phpBB hash
				$sql = 'UPDATE ' . USERS_TABLE . ' SET user_password = \'' . $this->db->sql_escape($phpbb_hash) . '\',
					user_pass_convert = 0
				WHERE user_id = ' . (int) $row['user_id'];
				$this->db->sql_query($sql);

				$row['user_pass_convert'] = 0;
				$row['user_password'] = $phpbb_hash;
			}
			else
			{
				// Although we weren't able to convert this password we have to
				// increase login attempt count to make sure this cannot be exploited
				$sql = 'UPDATE ' . USERS_TABLE . '
					SET user_login_attempts = user_login_attempts + 1
					WHERE user_id = ' . (int) $row['user_id'] . '
						AND user_login_attempts < ' . LOGIN_ATTEMPTS_MAX;
				$this->db->sql_query($sql);

				return array(
					'status'		=> LOGIN_ERROR_PASSWORD_CONVERT,
					'error_msg'		=> 'LOGIN_ERROR_PASSWORD_CONVERT',
					'user_row'		=> $row,
				);
			}
		}

		// Check password ...
		if ($row['user_pass_convert']==0 && phpbb_check_hash($password, $row['user_password']))
		{
			// Check for old password hash...
			if (strlen($row['user_password']) == 32)
			{
				$hash = phpbb_hash($password);

				// Update the password in the users table to the new format
				$sql = 'UPDATE ' . USERS_TABLE . "
					SET user_password = '" . $this->db->sql_escape($hash) . "',
						user_pass_convert = 0
					WHERE user_id = {$row['user_id']}";
				$this->db->sql_query($sql);

				$row['user_password'] = $hash;
			}

			$sql = 'DELETE FROM ' . LOGIN_ATTEMPT_TABLE . '
				WHERE user_id = ' . $row['user_id'];
			$this->db->sql_query($sql);

			if ($row['user_login_attempts'] != 0)
			{
				// Successful, reset login attempts (the user passed all stages)
				$sql = 'UPDATE ' . USERS_TABLE . '
					SET user_login_attempts = 0
					WHERE user_id = ' . $row['user_id'];
				$this->db->sql_query($sql);
			}

			// User inactive...
			if ($row['user_type'] == USER_INACTIVE || $row['user_type'] == USER_IGNORE)
			{
				return array(
					'status'		=> LOGIN_ERROR_ACTIVE,
					'error_msg'		=> 'ACTIVE_ERROR',
					'user_row'		=> $row,
				);
			}

			// Successful login... set user_login_attempts to zero...
			return array(
				'status'		=> LOGIN_SUCCESS,
				'error_msg'		=> false,
				'user_row'		=> $row,
			);
		}

		// Password incorrect - increase login attempts
		$sql = 'UPDATE ' . USERS_TABLE . '
			SET user_login_attempts = user_login_attempts + 1
			WHERE user_id = ' . (int) $row['user_id'] . '
				AND user_login_attempts < ' . LOGIN_ATTEMPTS_MAX;
		$this->db->sql_query($sql);

		// Give status about wrong password...
		return array(
			'status'		=> ($show_captcha) ? LOGIN_ERROR_ATTEMPTS : LOGIN_ERROR_PASSWORD,
			'error_msg'		=> ($show_captcha) ? 'LOGIN_ERROR_ATTEMPTS' : 'LOGIN_ERROR_PASSWORD',
			'user_row'		=> $row,
		);
	}
}

?>
