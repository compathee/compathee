<?php
/**
 * Copy to config.php in this same directory on the server.
 * Do not commit config.php. The API .htaccess denies config.php.
 *
 * The contact script reads this file only. It writes rate-limit data and
 * contact-mail.log inside the api directory and nowhere else.
 *
 * smtp_user must be allowed to send as from_email. The rehearsal relay uses
 * the mailbox compath@compath.ee on compath.ee port 465. Confirm that before
 * relying on these defaults. to_email is the only recipient.
 *
 * @return array<string, mixed>
 */
return array(
	'smtp_host'   => 'compath.ee',
	'smtp_port'   => 465,
	'smtp_user'   => 'compath@compath.ee',
	'smtp_pass'   => '',
	'from_email'  => 'support@compath.ee',
	'from_name'   => 'Compath',
	'to_email'    => 'support@compath.ee',
	'rate_limit'  => 8,
	'rate_window' => 3600,
);
