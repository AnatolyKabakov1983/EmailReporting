<?php
require_once( config_get( 'class_path' ) . 'MantisPlugin.class.php' );

class EmailReportingPlugin extends MantisPlugin {
	/**
	 *  A method that populates the plugin information and minimum requirements.
	 */ 
	function register() {
		$this->name = plugin_lang_get( 'title' );
		$this->description = plugin_lang_get( 'description' );
		$this->page = 'config';

		$this->version = '0.6.3';
		$this->requires = array(
			'MantisCore' => '1.2',
		);

		$this->author = plugin_lang_get( 'author' );
		$this->contact = '';
		$this->url = 'http://www.mantisbt.org/bugs/view.php?id=4286';
	}

	/**
	 * EmailReporting plugin configuration.
	 */
	function config() {
		return array(
			'reset_schema' => 0,
			'schema' => -1,

			# --- mail reporting settings -----
			# Empty default mailboxes array. This array will be used for all the mailbox
			# accounts
			'mailboxes' => array(),
			
			# Do you want to secure the EmailReporting script so that it cannot be run
			# via a webserver?
			'mail_secured_script'			=> ON,

			# This tells Mantis to report all the Mail with only one account
			# ON = mail uses the reporter account in the setting below
			# OFF = it identifies the reporter using the email address of the sender
			'mail_use_reporter'			=> ON,
		
			# The account's name for mail reporting
			# Also used for fallback if a user is not found in database
			'mail_reporter'				=> 'Mail',
		
			# Signup new users automatically (possible security risk!)
			# Default is OFF, if mail_use_reporter is ON and this is off then it will
			# fallback on the mail_reporter account above
			'mail_auto_signup'			=> OFF,
		
			# How many mails should be fetched at the same time
			# If big mails with attachments should be received, specify only one
			'mail_fetch_max'			=> 1,
		
			# Add complete email into the attachments
			'mail_add_complete_email'			=> OFF,
		
			# Write sender of the message into the bug report
			'mail_save_from'			=> ON,
		
			# Parse MIME mails (may require a lot of memory)
			'mail_parse_mime'			=> OFF,
		
			# Parse HTML mails
			'mail_parse_html'			=> ON,

			# Try to identify only the reply parts in emails incase of notes
			'mail_identify_reply'		=> ON,
		
			# directory for saving temporary mail content
			'mail_tmp_directory'		=> '/tmp',
		
			# Delete incoming mail from POP3 server
			'mail_delete'				=> ON,
		
			# Used for debugging the system.
			# Use with care
			'mail_debug'				=> OFF,
		
			# Save mail contents to this directory if debug mode is ON
			'mail_directory'			=> '/tmp/mantis',
		
			# The auth method used for POP3
			# Valid methods are: 'DIGEST-MD5','CRAM-MD5','LOGIN','PLAIN','APOP','USER'
			'mail_auth_method'			=> 'USER',
		
			# Looks for priority header field
			'mail_use_bug_priority' 	=> ON,
		
			# Default priority for mail reported bugs
			'mail_bug_priority_default'	=> NORMAL,

			# Use the following text when the subject is missing from the email
			'mail_nosubject' 			=> 'No subject found', 

			# Use the following text when the description is missing from the email
			'mail_nodescription' 		=> 'No description found', 
		
			# Classify bug priorities
			'mail_bug_priority' 		=> array(
				'5 (lowest)'	=> 10,
				'4 (low)'		=> 20,
				'3 (normal)'	=> 30,
				'2 (high)'		=> 40,
				'1 (highest)'	=> 50,
				'5'		=> 20,
				'4'		=> 20,
				'3'		=> 30,
				'2'		=> 40,
				'1'		=> 50,
				'0'		=> 10,
				'low'			=> 20,
				'normal' 		=> 30,
				'high' 			=> 40,
				'' 				=> 30,
				'?' 			=> 30
			),
		
			# Need to set the character encoding to which the email will be converted
			# This should be the same as the character encoding used in the database system used for mantis
			# values should be acceptable to the following function: http://www.php.net/mb_convert_encoding
			'mail_encoding' 			=> 'UTF-8', 
		);
	} 

	/**
	 * EmailReporting installation function.
	 */
	function install(){
		$t_random_user_number = plugin_config_get( 'random_user_number', 'NOT FOUND' );
		if ( $t_random_user_number === 'NOT FOUND' )
		{
			# We need to allow blank emails for a sec
			$t_allow_blank_email = config_get( 'allow_blank_email' );
			config_set( 'allow_blank_email', ON );

			$t_rand = RAND();

			$t_username = plugin_config_get( 'mail_reporter', 'Mail' ) . $t_rand;

			$t_email = '';

			$t_seed = $t_email . $t_username;

			# Create random password
			$t_password = auth_generate_random_password( $t_seed );

			# create the user
			$t_result_user_create = user_create( $t_username, $t_password, $t_email, REPORTER, false, true, 'Mail Reporter' );

			# Save these after the user has been created succesfully
			if ( $t_result_user_create )
			{
				plugin_config_set( 'random_user_number', $t_rand );
				plugin_config_set( 'mail_reporter', $t_username );
				plugin_config_set( 'reset_schema', 1 );
			}

			# return the setting back to its usual value
			config_set( 'allow_blank_email', $t_allow_blank_email );

			return( $t_result_user_create );
		}

		return( TRUE );
	}

	/**
	 * EmailReporting uninstallation function.
	 */
	function uninstall(){
		# User removal from the install function will not be done
		# The reason being thats its possibly connected to issues in the system
		return( TRUE );
	}

	/**
	 * EmailReporting initialation function.
	 */
	function init() {
		$t_path = config_get_global('plugin_path' ). plugin_get_current() . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR;

		set_include_path(get_include_path() . PATH_SEPARATOR . $t_path);
	} 

	/**
	 * EmailReporting plugin hooks.
	 */
	function hooks( ) {
		$hooks = array(
			'EVENT_MENU_MANAGE'			=> 'EmailReporting_maintain_mailbox_menu',
			'EVENT_CORE_READY'			=> 'EmailReporting_core_ready',
		);

		return $hooks;
	}

	/**
	 * EmailReporting plugin hooks - add mailbox settings menu item.
	 */
	function EmailReporting_maintain_mailbox_menu( ) {
		return array( '<a href="' . plugin_page( 'maintainmailbox' ) . '">' . plugin_lang_get( 'mailbox_settings' ) . '</a>', );
	}

	/* 
	 * Since schema is not used anymore some corrections need to be applied
	 * Schema will be completely reset by this just once
	 */
	function EmailReporting_core_ready( )
	{
		$t_reset_schema = plugin_config_get( 'reset_schema', 0 );

		if ( $t_reset_schema === 0 )
		{
			$t_username = plugin_config_get( 'mail_reporter' );
			$t_user_id = user_get_id_by_name( $t_username );
	
			if ( $t_user_id !== false )
			{
				$t_user_email = user_get_field( $t_user_id, 'email' );
			
				if ( $t_user_email === 'nomail' )
				{
					user_set_field( $t_user_id, 'email', '' );
				}
			}
	
			plugin_config_set( 'schema', -1 );
			plugin_config_set( 'reset_schema', 1 );
		}
	}
}