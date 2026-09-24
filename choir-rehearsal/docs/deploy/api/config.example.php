<?php
/**
 * Copy this file to the private directory OUTSIDE the web root:
 *   /domains/rehearsal.compath.ee/private/feedback-config.php
 *
 * Do not commit the real file. Do not upload it into public_html.
 *
 * @return array<string, mixed>
 */
return array(
	'jira_base'       => 'https://compath.atlassian.net',
	'jira_email'      => '',
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
