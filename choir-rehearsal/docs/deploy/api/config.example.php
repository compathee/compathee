<?php
/**
 * Copy this file beside feedback.php and name it config.php:
 *   /domains/rehearsal.compath.ee/public_html/api/config.php
 *
 * .htaccess in that folder denies config.php. Do not commit the real file.
 * If config.php is absent, the script falls back to
 *   /domains/rehearsal.compath.ee/private/feedback-config.php
 *
 * @return array<string, mixed>
 */
return array(
	'jira_base'       => 'https://compath.atlassian.net',
	'jira_email'      => 'compath@compath.ee',
	'jira_token'      => '',
	'jira_project'    => 'WP',
	'jira_issue_type' => 'Task',
	'smtp_host'       => 'compath.ee',
	'smtp_port'       => 465,
	'smtp_user'       => 'compath@compath.ee',
	'smtp_pass'       => '',
	'from_email'      => 'support@compath.ee',
	'from_name'       => 'Compath Support',
	'reply_to'        => 'support@compath.ee',
	'rate_limit'      => 40,
	'rate_window'     => 3600,
);
