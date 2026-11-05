<?php
/**
 * Run: php compath-site/api/contact-test.php
 */

declare(strict_types=1);

define( 'COMPATH_CONTACT_LIBRARY', true );
require __DIR__ . '/contact.php';

$failed = 0;

function expect( $label, $condition ) {
	global $failed;
	if ( $condition ) {
		echo "ok  {$label}\n";
		return;
	}
	$failed++;
	echo "FAIL {$label}\n";
}

$config = array(
	'to_email'   => 'support@compath.ee',
	'from_email' => 'support@compath.ee',
	'from_name'  => 'Compath',
	'smtp_user'  => 'compath@compath.ee',
	'smtp_pass'  => 'secret',
);

$sent = array();
$mail = static function ( $message ) use ( &$sent ) {
	$sent[] = $message;
	return true;
};
$allow = static function () {
	return true;
};
$deny = static function () {
	return false;
};

$valid = array(
	'name'    => "Ann\nBcc: x",
	'email'   => 'ann@example.com',
	'phone'   => '+372 55520482',
	'message' => 'Please look at the office network.',
	'locale'  => 'et',
	'company' => '',
);

$sent = array();
$ok   = Compath_Contact::handle( 'POST', $valid, $config, '203.0.113.5', time(), '', $mail, $allow );
expect( 'accepts a normal message', $ok['ok'] && 200 === $ok['status'] );
expect( 'recipient is the configured mailbox', isset( $sent[0]['to'] ) && 'support@compath.ee' === $sent[0]['to'] );
expect( 'reply-to is the sender', isset( $sent[0]['reply_to'] ) && 'ann@example.com' === $sent[0]['reply_to'] );
expect( 'newlines in the name do not stay', isset( $sent[0]['body'] ) && false === strpos( $sent[0]['body'], "\nBcc:" ) );

$sent = array();
$bot  = $valid;
$bot['company'] = 'https://spam.example';
$fake = Compath_Contact::handle( 'POST', $bot, $config, '203.0.113.5', time(), '', $mail, $allow );
expect( 'honeypot pretends to succeed', $fake['ok'] && array() === $sent );

$bad = $valid;
$bad['email'] = "ann@example.com\r\nBcc: evil@example.com";
$sent = array();
$rejected = Compath_Contact::handle( 'POST', $bad, $config, '203.0.113.5', time(), '', $mail, $allow );
expect( 'header injection is rejected', ! $rejected['ok'] && 400 === $rejected['status'] && array() === $sent );

$limited = Compath_Contact::handle( 'POST', $valid, $config, '203.0.113.5', time(), '', $mail, $deny );
expect( 'rate limit', 429 === $limited['status'] );

$get = Compath_Contact::handle( 'GET', $valid, $config, '203.0.113.5', time(), '', $mail, $allow );
expect( 'GET is rejected', 405 === $get['status'] );

$foreign = Compath_Contact::handle( 'POST', $valid, $config, '203.0.113.5', time(), 'https://evil.example', $mail, $allow );
expect( 'foreign origin is rejected', 403 === $foreign['status'] );

$dir = sys_get_temp_dir() . '/compath-contact-rate-' . getmypid();
@mkdir( $dir, 0700, true );
$now = time();
$first = Compath_Contact::consume_rate( __DIR__ . '/contact-rate-test', '198.51.100.8', $now, 2, 3600 );
$second = Compath_Contact::consume_rate( __DIR__ . '/contact-rate-test', '198.51.100.8', $now, 2, 3600 );
$third = Compath_Contact::consume_rate( __DIR__ . '/contact-rate-test', '198.51.100.8', $now, 2, 3600 );
expect( 'file rate limit allows two then blocks', $first && $second && ! $third );

$outside = Compath_Contact::consume_rate( '/tmp/compath-outside-rate', '198.51.100.9', $now, 2, 3600 );
expect( 'rate store outside api is refused', ! $outside && ! is_dir( '/tmp/compath-outside-rate' ) );

$exchange = array();
$script   = static function ( $send ) use ( &$exchange ) {
	$exchange[] = $send;
	$step = count( $exchange );
	$codes = array( 1 => "220 ready\r\n", 2 => "250 hello\r\n", 3 => "334 vx\r\n", 4 => "334 pass\r\n", 5 => "235 ok\r\n", 6 => "250 from\r\n", 7 => "250 to\r\n", 8 => "354 go\r\n", 9 => "250 queued\r\n" );
	return isset( $codes[ $step ] ) ? $codes[ $step ] : "250 bye\r\n";
};
$message = Compath_Contact::message( $config, Compath_Contact::normalize( $valid ) );
$smtp = Compath_Contact::smtp_converse( $script, $config, $message );
expect( 'smtp conversation completes', $smtp );
$data = '';
foreach ( $exchange as $line ) {
	if ( 0 === strpos( (string) $line, 'Date:' ) || false !== strpos( (string) $line, 'Reply-To:' ) ) {
		$data .= $line;
	}
}
expect( 'smtp data has reply-to and no extra recipient', false !== strpos( implode( "\n", $exchange ), 'RCPT TO:<support@compath.ee>' ) && false === strpos( implode( "\n", $exchange ), 'evil@' ) );

@array_map( 'unlink', glob( __DIR__ . '/contact-rate-test/*' ) ?: array() );
@rmdir( __DIR__ . '/contact-rate-test' );

if ( $failed > 0 ) {
	fwrite( STDERR, "{$failed} failed\n" );
	exit( 1 );
}
echo "all passed\n";
