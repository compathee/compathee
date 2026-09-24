<?php
/**
 * Must-use guard for the public Choir Rehearsal demo accounts.
 *
 * Install this single file at wp-content/mu-plugins/compath-rehearsal-demo-guard.php.
 * It locks the shared demo logins (password, email, profile, role, deletion),
 * keeps them out of the WordPress dashboard, and restores a content baseline.
 *
 * Song and track edits stay available for the Voice Leader demo. The nightly
 * baseline restore puts that library back. See mu-plugins/README.md.
 *
 * @package Compath_Rehearsal_Demo_Guard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Compath_Rehearsal_Demo_Guard {

	public const TEXT_DOMAIN = 'compath-rehearsal-demo-guard';

	public const BASELINE_VERSION = 1;

	public const DEFAULT_MAX_UPLOAD_BYTES = 8388608;

	public const NOTICE = 'This is a shared demo. Changes are reset every night.';

	/**
	 * Role display names are never passed through gettext.
	 *
	 * @var list<string>
	 */
	private const DEFAULT_LOGINS = array( 'demosinger', 'demoleader' );

	/**
	 * @var list<string>
	 */
	private const CONTENT_POST_TYPES = array( 'choir_song', 'choir_track' );

	/**
	 * @var list<string>
	 */
	private const ATTACHMENT_META_KEYS = array( '_choir_audio_id', '_choir_score_pdf_id' );

	private const TAXONOMY = 'choir_voice_type';

	private static bool $restoring = false;

	private static bool $notice_rendered = false;

	/**
	 * True while this class is calling wp_get_current_user() or current_user_can().
	 * Those calls re-enter determine_current_user, which must not call back in.
	 */
	private static bool $resolving_actor = false;

	private static bool $resolving_locale = false;

	private static bool $resolving_caps = false;

	/** @var string|array<int, mixed>|null */
	private static $xmlrpc_edit_profile = null;

	/** @var string|array<int, mixed>|null */
	private static $xmlrpc_set_options = null;

	public static function register(): void {
		add_filter( 'gettext', array( self::class, 'filter_gettext' ), 10, 3 );
		add_filter( 'show_admin_bar', array( self::class, 'filter_show_admin_bar' ) );
		add_action( 'admin_init', array( self::class, 'redirect_admin' ), 0 );
		add_action( 'admin_head', array( self::class, 'hide_profile_fields' ) );
		add_filter( 'show_password_fields', array( self::class, 'filter_show_password_fields' ), 10, 2 );
		add_filter( 'wp_is_application_passwords_available_for_user', array( self::class, 'filter_application_passwords' ), 10, 2 );
		add_action( 'user_profile_update_errors', array( self::class, 'block_profile_update' ), 10, 3 );
		add_filter( 'wp_pre_insert_user_data', array( self::class, 'filter_user_data' ), 10, 4 );
		add_filter( 'insert_user_meta', array( self::class, 'filter_user_meta' ), 10, 4 );
		add_filter( 'update_user_metadata', array( self::class, 'block_locked_usermeta' ), 10, 5 );
		add_filter( 'delete_user_metadata', array( self::class, 'block_locked_usermeta_delete' ), 10, 5 );
		add_filter( 'allow_password_reset', array( self::class, 'filter_allow_password_reset' ), 10, 2 );
		add_action( 'lostpassword_post', array( self::class, 'block_lost_password' ), 10, 2 );
		add_action( 'validate_password_reset', array( self::class, 'block_password_reset_form' ), 10, 2 );
		add_action( 'login_init', array( self::class, 'block_reset_screen' ) );
		add_filter( 'login_message', array( self::class, 'filter_login_message' ) );
		add_filter( 'send_password_change_email', array( self::class, 'filter_password_change_email' ), 10, 2 );
		add_filter( 'send_email_change_email', array( self::class, 'filter_email_change_email' ), 10, 2 );
		add_filter( 'rest_pre_dispatch', array( self::class, 'filter_rest_dispatch' ), 10, 3 );
		add_filter( 'xmlrpc_methods', array( self::class, 'filter_xmlrpc_methods' ) );
		add_filter( 'wp_handle_upload_prefilter', array( self::class, 'filter_upload' ) );
		add_filter( 'pre_delete_post', array( self::class, 'filter_pre_delete_post' ), 10, 3 );
		add_filter( 'map_meta_cap', array( self::class, 'filter_map_meta_cap' ), 10, 4 );
		add_action( 'delete_user', array( self::class, 'block_delete_user' ), 0, 1 );
		add_action( 'wp_body_open', array( self::class, 'render_notice' ) );
		add_action( 'wp_footer', array( self::class, 'render_notice' ), 1 );
		add_action( 'init', array( self::class, 'maybe_schedule_wp_cron' ) );
		add_action( self::cron_hook(), array( self::class, 'cron_restore' ) );
		self::register_cli();
	}

	public static function cron_hook(): string {
		return 'compath_demo_guard_baseline_restore';
	}

	/**
	 * Published demo logins. Constant COMPATH_DEMO_GUARDED_LOGINS may be a
	 * comma-separated string or an array. A blank constant keeps the defaults.
	 *
	 * @return list<string>
	 */
	public static function logins(): array {
		$raw = self::DEFAULT_LOGINS;
		if ( defined( 'COMPATH_DEMO_GUARDED_LOGINS' ) ) {
			$parsed = self::parse_login_list( COMPATH_DEMO_GUARDED_LOGINS );
			if ( array() !== $parsed ) {
				$raw = $parsed;
			}
		}

		$logins = array();
		foreach ( $raw as $login ) {
			$login = strtolower( trim( (string) $login ) );
			if ( '' === $login || in_array( $login, $logins, true ) ) {
				continue;
			}
			$logins[] = $login;
		}

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'compath_demo_guarded_logins', $logins );
			if ( is_array( $filtered ) ) {
				$logins = array();
				foreach ( $filtered as $login ) {
					$login = strtolower( trim( (string) $login ) );
					if ( '' !== $login && ! in_array( $login, $logins, true ) ) {
						$logins[] = $login;
					}
				}
			}
		}

		return $logins;
	}

	/**
	 * @param mixed $value Constant value.
	 * @return list<string>
	 */
	public static function parse_login_list( $value ): array {
		if ( is_string( $value ) ) {
			$parts = array_map( 'trim', explode( ',', $value ) );
		} elseif ( is_array( $value ) ) {
			$parts = $value;
		} else {
			return array();
		}

		$logins = array();
		foreach ( $parts as $login ) {
			$login = strtolower( trim( (string) $login ) );
			if ( '' !== $login && ! in_array( $login, $logins, true ) ) {
				$logins[] = $login;
			}
		}

		return $logins;
	}

	public static function is_guarded_login( string $login ): bool {
		$login = strtolower( trim( $login ) );
		return '' !== $login && in_array( $login, self::logins(), true );
	}

	/**
	 * WP_User::__get() returns false for a missing user_login (user ID 0 during
	 * install). Passing that false into is_guarded_login() is a TypeError.
	 *
	 * @param mixed $user User object.
	 */
	private static function user_login_string( $user ): string {
		if ( ! $user instanceof WP_User ) {
			return '';
		}
		$login = $user->user_login;
		return is_string( $login ) ? $login : '';
	}

	/**
	 * Known-good account fields. Passwords are the published demo passwords.
	 * Override with the COMPATH_DEMO_ACCOUNT_BASELINE constant (array keyed by login).
	 *
	 * Role slugs are data. Do not translate them.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function account_baseline(): array {
		$accounts = self::default_accounts();
		if ( defined( 'COMPATH_DEMO_ACCOUNT_BASELINE' ) && is_array( COMPATH_DEMO_ACCOUNT_BASELINE ) ) {
			foreach ( COMPATH_DEMO_ACCOUNT_BASELINE as $login => $spec ) {
				if ( ! is_string( $login ) || ! is_array( $spec ) ) {
					continue;
				}
				$login = strtolower( trim( $login ) );
				if ( ! self::is_guarded_login( $login ) ) {
					continue;
				}
				$accounts[ $login ] = array_merge( $accounts[ $login ] ?? array(), $spec );
			}
		}

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'compath_demo_account_baseline', $accounts );
			if ( is_array( $filtered ) ) {
				$accounts = $filtered;
			}
		}

		$safe = array();
		foreach ( $accounts as $login => $spec ) {
			if ( ! is_string( $login ) || ! is_array( $spec ) || ! self::is_guarded_login( $login ) ) {
				continue;
			}
			$role = self::sanitize_demo_role( (string) ( $spec['role'] ?? '' ) );
			if ( '' === $role ) {
				continue;
			}
			$spec['role'] = $role;
			$safe[ strtolower( $login ) ] = $spec;
		}

		return $safe;
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	public static function default_accounts(): array {
		return array(
			'demosinger' => array(
				'user_email'   => 'demouser@shop.compath.ee',
				'user_pass'    => 'DMo5ingERzz26',
				'display_name' => 'Demo Singer',
				'nickname'     => 'demosinger',
				'first_name'   => 'Demo',
				'last_name'    => 'Singer',
				'role'         => 'choir_singer',
			),
			'demoleader' => array(
				'user_email'   => 'demoleader@rehearsal.compath.ee',
				'user_pass'    => 'DMoLiidERzz26',
				'display_name' => 'Demo Voice Leader',
				'nickname'     => 'demoleader',
				'first_name'   => 'Demo',
				'last_name'    => 'Voice Leader',
				'role'         => 'choir_voice_leader',
			),
		);
	}

	public static function sanitize_demo_role( string $role ): string {
		$allowed = array( 'choir_singer', 'choir_voice_leader' );
		return in_array( $role, $allowed, true ) ? $role : '';
	}

	/**
	 * @return list<string>
	 */
	public static function guarded_emails(): array {
		$emails = array();
		foreach ( self::account_baseline() as $spec ) {
			$email = strtolower( trim( (string) ( $spec['user_email'] ?? '' ) ) );
			if ( '' !== $email && ! in_array( $email, $emails, true ) ) {
				$emails[] = $email;
			}
		}
		return $emails;
	}

	public static function is_guarded_login_or_email( string $value, array $logins, array $emails ): bool {
		$value = strtolower( trim( $value ) );
		if ( '' === $value ) {
			return false;
		}
		$logins = array_map(
			static function ( $login ): string {
				return strtolower( trim( (string) $login ) );
			},
			$logins
		);
		$emails = array_map(
			static function ( $email ): string {
				return strtolower( trim( (string) $email ) );
			},
			$emails
		);
		return in_array( $value, $logins, true ) || in_array( $value, $emails, true );
	}

	public static function should_block_user_mutation( bool $actor_is_admin, bool $target_is_guarded ): bool {
		if ( self::$restoring ) {
			return false;
		}
		return $target_is_guarded && ! $actor_is_admin;
	}

	public static function should_block_password_reset( bool $target_is_guarded ): bool {
		return $target_is_guarded && ! self::$restoring;
	}

	/**
	 * @param array<string, mixed> $incoming
	 * @param array<string, mixed> $current
	 * @return array<string, mixed>
	 */
	public static function lock_user_row( array $incoming, array $current ): array {
		foreach ( array( 'user_login', 'user_pass', 'user_nicename', 'user_email', 'user_url', 'display_name', 'user_activation_key' ) as $key ) {
			if ( array_key_exists( $key, $current ) ) {
				$incoming[ $key ] = $current[ $key ];
			}
		}
		return $incoming;
	}

	/**
	 * @return list<string>
	 */
	public static function locked_meta_keys( string $prefix ): array {
		return array(
			'nickname',
			'first_name',
			'last_name',
			'description',
			'rich_editing',
			'syntax_highlighting',
			'admin_color',
			'comment_shortcuts',
			'admin_bar_front',
			'locale',
			'use_ssl',
			'show_admin_bar_front',
			'_new_email',
			'_application_passwords',
			$prefix . 'capabilities',
			$prefix . 'user_level',
		);
	}

	public static function is_locked_meta_key( string $meta_key, string $prefix ): bool {
		return in_array( $meta_key, self::locked_meta_keys( $prefix ), true );
	}

	/**
	 * @param array<string, mixed> $query
	 */
	public static function is_admin_screen_allowed( string $script, array $query, bool $can_manage_songs, string $resolved_post_type = '' ): bool {
		$script = strtolower( basename( $script ) );
		$always = array( 'admin-ajax.php', 'async-upload.php', 'admin-async-upload.php', 'load-scripts.php', 'load-styles.php' );
		if ( in_array( $script, $always, true ) ) {
			return true;
		}

		if ( ! $can_manage_songs ) {
			return false;
		}

		if ( isset( $query['page'] ) && '' !== (string) $query['page'] ) {
			return false;
		}

		$post_type = isset( $query['post_type'] ) ? (string) $query['post_type'] : $resolved_post_type;
		$song_types = self::CONTENT_POST_TYPES;

		if ( in_array( $script, array( 'edit.php', 'post-new.php' ), true ) ) {
			return in_array( $post_type, $song_types, true );
		}

		if ( 'post.php' === $script ) {
			return in_array( $post_type, $song_types, true );
		}

		if ( in_array( $script, array( 'media-upload.php', 'media-new.php' ), true ) ) {
			return true;
		}

		return false;
	}

	public static function upload_block_reason( int $size, int $max ): string {
		if ( $max <= 0 || $size <= $max ) {
			return '';
		}
		return 'This file is too large for the shared demo.';
	}

	public static function max_upload_bytes(): int {
		if ( defined( 'COMPATH_DEMO_MAX_UPLOAD_BYTES' ) ) {
			return max( 0, (int) COMPATH_DEMO_MAX_UPLOAD_BYTES );
		}
		return self::DEFAULT_MAX_UPLOAD_BYTES;
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	public static function catalog(): array {
		return array(
			'et' => array(
				self::NOTICE                                      => 'See on ühine demo. Muudatused lähtestatakse igal ööl.',
				'Demo accounts cannot be changed.'                => 'Demo konto andmeid ei saa muuta.',
				'Password reset is disabled for demo accounts.'   => 'Demo konto parooli ei saa lähtestada.',
				'Demo accounts cannot be deleted.'                => 'Demo kontot ei saa kustutada.',
				'This file is too large for the shared demo.'     => 'Fail on demo jaoks liiga suur.',
			),
			'ru' => array(
				self::NOTICE                                      => 'Это общая демонстрация. Изменения сбрасываются каждую ночь.',
				'Demo accounts cannot be changed.'                => 'Данные демо-аккаунта нельзя изменить.',
				'Password reset is disabled for demo accounts.'   => 'Сброс пароля демо-аккаунта отключён.',
				'Demo accounts cannot be deleted.'                => 'Демо-аккаунт нельзя удалить.',
				'This file is too large for the shared demo.'     => 'Файл слишком большой для демо.',
			),
		);
	}

	public static function translate( string $text, string $locale ): string {
		$lang    = strtolower( substr( str_replace( '-', '_', $locale ), 0, 2 ) );
		$catalog = self::catalog();
		if ( isset( $catalog[ $lang ][ $text ] ) ) {
			return $catalog[ $lang ][ $text ];
		}
		return $text;
	}

	public static function notice_text(): string {
		if ( function_exists( '__' ) ) {
			return __( self::NOTICE, self::TEXT_DOMAIN );
		}
		return self::NOTICE;
	}

	/**
	 * @param string $translation Existing translation.
	 * @param string $text        Original English text.
	 * @param string $domain      Text domain.
	 */
	public static function filter_gettext( string $translation, string $text, string $domain ): string {
		if ( self::TEXT_DOMAIN !== $domain ) {
			return $translation;
		}
		$custom = self::translate( $text, self::locale_for_catalog() );
		return $custom !== $text ? $custom : $translation;
	}

	/**
	 * Site locale until the current user is set. determine_locale() can call
	 * get_user_locale(), which calls wp_get_current_user(), while that user is
	 * still being resolved.
	 */
	private static function locale_for_catalog(): string {
		if ( self::$resolving_locale ) {
			return 'en_US';
		}
		self::$resolving_locale = true;
		$avoid_user             = self::$resolving_actor || ( function_exists( 'did_action' ) && ! did_action( 'set_current_user' ) );
		if ( $avoid_user && function_exists( 'get_locale' ) ) {
			$locale = (string) get_locale();
		} elseif ( function_exists( 'determine_locale' ) ) {
			$locale = (string) determine_locale();
		} elseif ( function_exists( 'get_locale' ) ) {
			$locale = (string) get_locale();
		} else {
			$locale = 'en_US';
		}
		self::$resolving_locale = false;
		return '' !== $locale ? $locale : 'en_US';
	}

	/**
	 * Block REST user mutations from demo accounts, and any mutation of a demo account
	 * unless the actor is an administrator.
	 */
	public static function rest_mutation_decision( string $route, string $method, bool $actor_is_admin, bool $actor_is_guarded, bool $target_is_guarded ): string {
		$method = strtoupper( $method );
		if ( ! in_array( $method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
			return 'allow';
		}
		if ( ! preg_match( '#^/wp/v2/users(?:/|$)#', $route ) ) {
			return 'allow';
		}
		if ( self::$restoring ) {
			return 'allow';
		}
		if ( str_contains( $route, '/application-passwords' ) && ( $target_is_guarded || $actor_is_guarded ) ) {
			return 'block';
		}
		if ( $actor_is_guarded && ! $actor_is_admin ) {
			return 'block';
		}
		if ( $target_is_guarded && ! $actor_is_admin ) {
			return 'block';
		}
		return 'allow';
	}

	public static function block_xmlrpc_method( string $method, string $username ): bool {
		$blocked = array( 'wp.editProfile', 'wp.setOptions' );
		return in_array( $method, $blocked, true ) && self::is_guarded_login( $username );
	}

	public static function serialized_grants_role( string $meta_value, string $role ): bool {
		$pattern = '/"' . preg_quote( $role, '/' ) . '";b:1/';
		return 1 === preg_match( $pattern, $meta_value );
	}

	public static function safe_upload_relative( string $path ): ?string {
		$path = str_replace( '\\', '/', trim( $path ) );
		if ( '' === $path || str_contains( $path, '..' ) || str_starts_with( $path, '/' ) || str_contains( $path, "\0" ) ) {
			return null;
		}
		return $path;
	}

	public static function is_restorable_option( string $name ): bool {
		if ( ! str_starts_with( $name, 'choir_rehearsal_' ) ) {
			return false;
		}
		foreach ( array( 'transient', 'reset_key', 'license', 'token' ) as $needle ) {
			if ( str_contains( $name, $needle ) ) {
				return false;
			}
		}
		return true;
	}

	public static function remap_user_meta_key( string $key, string $from_prefix, string $to_prefix ): string {
		foreach ( array( 'capabilities', 'user_level', 'user-settings', 'user-settings-time' ) as $suffix ) {
			if ( $key === $from_prefix . $suffix ) {
				return $to_prefix . $suffix;
			}
		}
		return $key;
	}

	/**
	 * @param array<string, int> $snapshot_user_ids_by_login
	 * @param array<string, int> $live_user_ids_by_login
	 */
	public static function rewrite_post_author( int $author, array $snapshot_user_ids_by_login, array $live_user_ids_by_login ): int {
		foreach ( $snapshot_user_ids_by_login as $login => $snap_id ) {
			if ( $author === (int) $snap_id && isset( $live_user_ids_by_login[ $login ] ) ) {
				return (int) $live_user_ids_by_login[ $login ];
			}
		}
		return $author;
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param list<string>         $logins
	 * @return list<string>
	 */
	public static function validate_snapshot_payload( array $payload, array $logins ): array {
		$errors = array();
		$logins = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $login ): string {
							return strtolower( trim( (string) $login ) );
						},
						$logins
					)
				)
			)
		);

		if ( array() === $logins ) {
			$errors[] = 'guarded login list is empty';
		}
		if ( (int) ( $payload['version'] ?? 0 ) !== self::BASELINE_VERSION ) {
			$errors[] = 'unsupported baseline version';
		}

		$users = isset( $payload['users'] ) && is_array( $payload['users'] ) ? $payload['users'] : array();
		if ( count( $users ) > count( $logins ) ) {
			$errors[] = 'snapshot has more users than the guarded list';
		}

		$allowed_ids = array();
		foreach ( $users as $user ) {
			if ( ! is_array( $user ) ) {
				$errors[] = 'snapshot user row is invalid';
				continue;
			}
			$login = strtolower( trim( (string) ( $user['user_login'] ?? '' ) ) );
			if ( ! in_array( $login, $logins, true ) ) {
				$errors[] = 'refusing user ' . $login;
				continue;
			}
			$user_id = (int) ( $user['ID'] ?? 0 );
			if ( $user_id <= 0 ) {
				$errors[] = 'refusing user id for ' . $login;
				continue;
			}
			$allowed_ids[ $user_id ] = $login;
		}

		$usermeta = isset( $payload['usermeta'] ) && is_array( $payload['usermeta'] ) ? $payload['usermeta'] : array();
		foreach ( $usermeta as $meta ) {
			if ( ! is_array( $meta ) ) {
				$errors[] = 'snapshot usermeta row is invalid';
				continue;
			}
			$user_id = (int) ( $meta['user_id'] ?? 0 );
			if ( ! isset( $allowed_ids[ $user_id ] ) ) {
				$errors[] = 'refusing usermeta for user ' . $user_id;
			}
			$key   = (string) ( $meta['meta_key'] ?? '' );
			$value = (string) ( $meta['meta_value'] ?? '' );
			if ( str_ends_with( $key, 'capabilities' ) && self::serialized_grants_role( $value, 'administrator' ) ) {
				$errors[] = 'refusing administrator capability on a demo account';
			}
		}

		$posts = isset( $payload['posts'] ) && is_array( $payload['posts'] ) ? $payload['posts'] : array();
		$post_ids = array();
		foreach ( $posts as $post ) {
			if ( ! is_array( $post ) ) {
				$errors[] = 'snapshot post row is invalid';
				continue;
			}
			$type = (string) ( $post['post_type'] ?? '' );
			if ( ! in_array( $type, array( 'choir_song', 'choir_track', 'attachment' ), true ) ) {
				$errors[] = 'refusing post type ' . $type;
			}
			$post_ids[] = (int) ( $post['ID'] ?? 0 );
		}

		$postmeta = isset( $payload['postmeta'] ) && is_array( $payload['postmeta'] ) ? $payload['postmeta'] : array();
		foreach ( $postmeta as $meta ) {
			if ( ! is_array( $meta ) ) {
				continue;
			}
			$post_id = (int) ( $meta['post_id'] ?? 0 );
			if ( ! in_array( $post_id, $post_ids, true ) ) {
				$errors[] = 'refusing postmeta for post ' . $post_id;
			}
		}

		$files = isset( $payload['files'] ) && is_array( $payload['files'] ) ? $payload['files'] : array();
		foreach ( $files as $file ) {
			if ( null === self::safe_upload_relative( (string) $file ) ) {
				$errors[] = 'unsafe upload path';
			}
		}

		$taxonomies = isset( $payload['term_taxonomy'] ) && is_array( $payload['term_taxonomy'] ) ? $payload['term_taxonomy'] : array();
		foreach ( $taxonomies as $tax ) {
			if ( ! is_array( $tax ) ) {
				continue;
			}
			$taxonomy = (string) ( $tax['taxonomy'] ?? '' );
			if ( self::TAXONOMY !== $taxonomy ) {
				$errors[] = 'refusing taxonomy ' . $taxonomy;
			}
		}

		$options = isset( $payload['options'] ) && is_array( $payload['options'] ) ? $payload['options'] : array();
		foreach ( $options as $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}
			$name = (string) ( $option['option_name'] ?? '' );
			if ( ! self::is_restorable_option( $name ) ) {
				$errors[] = 'refusing option ' . $name;
			}
		}

		return array_values( array_unique( $errors ) );
	}

	/**
	 * @param array<string, int> $where
	 */
	public static function user_restore_where( int $user_id, string $login, array $guarded_logins ): ?array {
		$login   = strtolower( trim( $login ) );
		$guarded = array_map(
			static function ( $item ): string {
				return strtolower( trim( (string) $item ) );
			},
			$guarded_logins
		);
		if ( $user_id <= 0 || '' === $login || ! in_array( $login, $guarded, true ) ) {
			return null;
		}
		return array(
			'ID'         => $user_id,
			'user_login' => $login,
		);
	}

	public static function next_tallinn_three_am( int $now ): int {
		$tz  = new DateTimeZone( 'Europe/Tallinn' );
		$dt  = ( new DateTimeImmutable( '@' . $now ) )->setTimezone( $tz );
		$run = $dt->setTime( 3, 0, 0 );
		if ( $run <= $dt ) {
			$run = $run->modify( '+1 day' );
		}
		return $run->getTimestamp();
	}

	public static function baseline_dir(): string {
		if ( defined( 'COMPATH_DEMO_BASELINE_DIR' ) && is_string( COMPATH_DEMO_BASELINE_DIR ) && '' !== COMPATH_DEMO_BASELINE_DIR ) {
			return rtrim( COMPATH_DEMO_BASELINE_DIR, '/\\' );
		}
		return dirname( ABSPATH ) . '/compath-demo-baseline';
	}

	public static function baseline_file(): string {
		return self::baseline_dir() . '/baseline.json';
	}

	public static function baseline_is_ready(): bool {
		$file = self::baseline_file();
		if ( ! is_file( $file ) ) {
			return false;
		}
		$payload = json_decode( (string) file_get_contents( $file ), true );
		if ( ! is_array( $payload ) ) {
			return false;
		}
		return array() === self::validate_snapshot_payload( $payload, self::logins() );
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param list<string>         $logins
	 * @return list<string> Empty when the file was written.
	 */
	public static function write_snapshot( string $dir, array $payload, array $logins ): array {
		$errors = self::validate_snapshot_payload( $payload, $logins );
		if ( array() !== $errors ) {
			return $errors;
		}
		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0750, true ) && ! is_dir( $dir ) ) {
			return array( 'could not create baseline directory' );
		}
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $payload ) : json_encode( $payload );
		if ( ! is_string( $json ) ) {
			return array( 'could not encode baseline' );
		}
		$file = rtrim( $dir, '/\\' ) . '/baseline.json';
		if ( false === file_put_contents( $file, $json ) ) {
			return array( 'could not write baseline' );
		}
		file_put_contents( rtrim( $dir, '/\\' ) . '/.htaccess', "Require all denied\n" );
		file_put_contents( rtrim( $dir, '/\\' ) . '/index.php', "<?php\n// Silence is golden.\n" );
		return array();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function empty_result( bool $ok, string $message ): array {
		return array(
			'ok'      => $ok,
			'message' => $message,
			'songs'   => 0,
			'tracks'  => 0,
			'media'   => 0,
			'users'   => 0,
		);
	}

	/* ---------------------------------------------------------------------
	 * WordPress integration
	 * ------------------------------------------------------------------- */

	public static function filter_show_admin_bar( bool $show ): bool {
		if ( self::current_is_guarded() ) {
			return false;
		}
		return $show;
	}

	public static function redirect_admin(): void {
		if ( ! self::current_is_guarded() || self::actor_is_admin() ) {
			return;
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}

		global $pagenow;
		$script = is_string( $pagenow ) ? $pagenow : '';
		$query  = array();
		foreach ( array( 'post_type', 'page', 'action', 'post' ) as $key ) {
			if ( isset( $_REQUEST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$query[ $key ] = sanitize_text_field( wp_unslash( (string) $_REQUEST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}

		$resolved = '';
		if ( 'post.php' === $script && isset( $query['post'] ) && function_exists( 'get_post_type' ) ) {
			$post_type = get_post_type( (int) $query['post'] );
			$resolved  = is_string( $post_type ) ? $post_type : '';
		}

		if ( self::is_admin_screen_allowed( $script, $query, self::current_can_manage_songs(), $resolved ) ) {
			return;
		}

		wp_safe_redirect( self::rehearsal_url() );
		exit;
	}

	public static function hide_profile_fields(): void {
		if ( ! self::current_is_guarded() ) {
			return;
		}
		echo '<style>'
			. '.user-email-wrap,.user-pass1-wrap,.user-pass2-wrap,.user-nickname-wrap,.user-display-name-wrap,'
			. '.user-first-name-wrap,.user-last-name-wrap,.user-description-wrap,.user-url-wrap,.user-admin-color-wrap,'
			. '.user-language-wrap,.user-rich-editing-wrap,.user-syntax-highlighting-wrap,.user-admin-bar-front-wrap,'
			. '.user-comment-shortcuts-wrap,#application-passwords-section,.application-passwords{display:none!important;}'
			. '</style>';
	}

	/**
	 * @param WP_User|mixed $profile_user Profile user.
	 */
	public static function filter_show_password_fields( bool $show, $profile_user ): bool {
		if ( $profile_user instanceof WP_User && self::is_guarded_login( self::user_login_string( $profile_user ) ) && ! self::actor_is_admin() ) {
			return false;
		}
		return $show;
	}

	/**
	 * Per-user only. The global wp_is_application_passwords_available filter runs
	 * inside wp_validate_application_password(), which runs inside
	 * determine_current_user(). Calling wp_get_current_user() from that filter
	 * recurses until the stack overflows.
	 *
	 * @param WP_User|mixed $user User.
	 */
	public static function filter_application_passwords( bool $available, $user ): bool {
		if ( $user instanceof WP_User && self::is_guarded_login( self::user_login_string( $user ) ) ) {
			return false;
		}
		return $available;
	}

	/**
	 * @param WP_Error $errors Errors.
	 * @param bool     $update Whether this is an update.
	 * @param stdClass $user   User object being saved.
	 */
	public static function block_profile_update( $errors, bool $update, $user ): void {
		unset( $update );
		if ( ! $errors instanceof WP_Error ) {
			return;
		}
		$user_id = isset( $user->ID ) ? (int) $user->ID : 0;
		$login   = isset( $user->user_login ) ? (string) $user->user_login : '';
		$guarded = self::is_guarded_login( $login ) || self::is_guarded_user_id( $user_id );
		if ( self::should_block_user_mutation( self::actor_is_admin(), $guarded ) ) {
			$errors->add( 'compath_demo_guard', __( 'Demo accounts cannot be changed.', self::TEXT_DOMAIN ) );
		}
	}

	/**
	 * @param array<string, mixed> $data     User row.
	 * @param bool                 $update   Update flag.
	 * @param int|null             $user_id  User ID.
	 * @param array<string, mixed> $userdata Raw userdata.
	 * @return array<string, mixed>
	 */
	public static function filter_user_data( array $data, bool $update, $user_id, array $userdata ): array {
		unset( $userdata );
		if ( self::$restoring || self::actor_is_admin() ) {
			return $data;
		}
		if ( ! $update ) {
			if ( self::current_is_guarded() ) {
				$data['user_login'] = '';
			}
			return $data;
		}
		if ( ! self::is_guarded_user_id( (int) $user_id ) ) {
			return $data;
		}
		$existing = get_userdata( (int) $user_id );
		if ( ! $existing instanceof WP_User ) {
			return $data;
		}
		return self::lock_user_row(
			$data,
			array(
				'user_login'          => $existing->user_login,
				'user_pass'           => $existing->user_pass,
				'user_nicename'       => $existing->user_nicename,
				'user_email'          => $existing->user_email,
				'user_url'            => $existing->user_url,
				'display_name'        => $existing->display_name,
				'user_activation_key' => $existing->user_activation_key,
			)
		);
	}

	/**
	 * @param array<string, mixed> $meta     Meta.
	 * @param WP_User              $user     User.
	 * @param bool                 $update   Update flag.
	 * @param array<string, mixed> $userdata Raw userdata.
	 * @return array<string, mixed>
	 */
	public static function filter_user_meta( array $meta, $user, bool $update, array $userdata ): array {
		unset( $userdata );
		if ( self::$restoring || ! $update || self::actor_is_admin() ) {
			return $meta;
		}
		if ( ! $user instanceof WP_User || ! self::is_guarded_login( self::user_login_string( $user ) ) ) {
			return $meta;
		}
		$prefix = isset( $GLOBALS['wpdb']->prefix ) ? (string) $GLOBALS['wpdb']->prefix : 'wp_';
		foreach ( self::locked_meta_keys( $prefix ) as $key ) {
			if ( array_key_exists( $key, $meta ) ) {
				unset( $meta[ $key ] );
			}
		}
		return $meta;
	}

	/**
	 * @param mixed  $check     Short-circuit.
	 * @param int    $user_id   User ID.
	 * @param string $meta_key  Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @param mixed  $prev_value Previous value.
	 * @return mixed
	 */
	public static function block_locked_usermeta( $check, $user_id, $meta_key, $meta_value, $prev_value ) {
		unset( $meta_value, $prev_value );
		if ( null !== $check || self::$restoring ) {
			return $check;
		}
		$prefix = isset( $GLOBALS['wpdb']->prefix ) ? (string) $GLOBALS['wpdb']->prefix : 'wp_';
		// Session tokens and other unlocked keys are written while WordPress is
		// still resolving the current user. Do not touch wp_get_current_user() then.
		if ( ! self::is_locked_meta_key( (string) $meta_key, $prefix ) ) {
			return $check;
		}
		if ( ! self::is_guarded_user_id( (int) $user_id ) || self::actor_is_admin() ) {
			return $check;
		}
		return false;
	}

	/**
	 * @param mixed  $check    Short-circuit.
	 * @param int    $user_id  User ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @param bool   $delete_all Delete all flag.
	 * @return mixed
	 */
	public static function block_locked_usermeta_delete( $check, $user_id, $meta_key, $meta_value, $delete_all ) {
		unset( $meta_value, $delete_all );
		return self::block_locked_usermeta( $check, $user_id, $meta_key, null, null );
	}

	public static function filter_allow_password_reset( bool $allow, int $user_id ): bool {
		if ( self::should_block_password_reset( self::is_guarded_user_id( $user_id ) ) ) {
			return false;
		}
		return $allow;
	}

	/**
	 * @param WP_Error     $errors    Errors.
	 * @param WP_User|null $user_data Looked-up user.
	 */
	public static function block_lost_password( $errors, $user_data = null ): void {
		if ( ! $errors instanceof WP_Error ) {
			return;
		}
		$posted = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['user_login'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$match  = self::is_guarded_login_or_email( $posted, self::logins(), self::guarded_emails() );
		$email = ( $user_data instanceof WP_User && is_string( $user_data->user_email ) ) ? strtolower( $user_data->user_email ) : '';
		if ( $user_data instanceof WP_User && ( self::is_guarded_login( self::user_login_string( $user_data ) ) || in_array( $email, self::guarded_emails(), true ) ) ) {
			$match = true;
		}
		if ( $match ) {
			$errors->add( 'compath_demo_guard', __( 'Password reset is disabled for demo accounts.', self::TEXT_DOMAIN ) );
		}
	}

	/**
	 * @param WP_Error     $errors Errors.
	 * @param WP_User|null $user   User.
	 */
	public static function block_password_reset_form( $errors, $user = null ): void {
		if ( ! $errors instanceof WP_Error ) {
			return;
		}
		if ( $user instanceof WP_User && self::should_block_password_reset( self::is_guarded_login( self::user_login_string( $user ) ) ) ) {
			$errors->add( 'compath_demo_guard', __( 'Password reset is disabled for demo accounts.', self::TEXT_DOMAIN ) );
		}
	}

	public static function block_reset_screen(): void {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $action, array( 'rp', 'resetpass' ), true ) ) {
			return;
		}
		$login = isset( $_REQUEST['login'] ) ? sanitize_user( wp_unslash( (string) $_REQUEST['login'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::is_guarded_login( $login ) ) {
			wp_die( esc_html__( 'Password reset is disabled for demo accounts.', self::TEXT_DOMAIN ), '', array( 'response' => 403 ) );
		}
	}

	public static function filter_login_message( string $message ): string {
		return $message . '<p class="message">' . esc_html( self::notice_text() ) . '</p>';
	}

	/**
	 * @param WP_User|mixed $user User.
	 */
	public static function filter_password_change_email( bool $send, $user ): bool {
		if ( $user instanceof WP_User && self::is_guarded_login( self::user_login_string( $user ) ) && ! self::actor_is_admin() && ! self::$restoring ) {
			return false;
		}
		return $send;
	}

	/**
	 * @param WP_User|mixed $user User.
	 */
	public static function filter_email_change_email( bool $send, $user ): bool {
		return self::filter_password_change_email( $send, $user );
	}

	/**
	 * @param mixed           $result  Response.
	 * @param WP_REST_Server  $server  Server.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function filter_rest_dispatch( $result, $server, $request ) {
		unset( $server );
		if ( null !== $result || ! $request instanceof WP_REST_Request ) {
			return $result;
		}
		$route  = (string) $request->get_route();
		$method = (string) $request->get_method();
		$target = self::rest_target_is_guarded( $route );
		$decision = self::rest_mutation_decision(
			$route,
			$method,
			self::actor_is_admin(),
			self::current_is_guarded(),
			$target
		);
		if ( 'block' !== $decision ) {
			return $result;
		}
		return new WP_Error(
			'compath_demo_guard',
			__( 'Demo accounts cannot be changed.', self::TEXT_DOMAIN ),
			array( 'status' => 403 )
		);
	}

	/**
	 * @param array<string, mixed> $methods XML-RPC methods.
	 * @return array<string, mixed>
	 */
	public static function filter_xmlrpc_methods( array $methods ): array {
		if ( isset( $methods['wp.editProfile'] ) ) {
			self::$xmlrpc_edit_profile   = $methods['wp.editProfile'];
			$methods['wp.editProfile']   = array( self::class, 'xmlrpc_edit_profile' );
		}
		if ( isset( $methods['wp.setOptions'] ) ) {
			self::$xmlrpc_set_options  = $methods['wp.setOptions'];
			$methods['wp.setOptions']  = array( self::class, 'xmlrpc_set_options' );
		}
		return $methods;
	}

	/**
	 * @param array<int, mixed> $args XML-RPC args.
	 * @return mixed
	 */
	public static function xmlrpc_edit_profile( array $args ) {
		$username = isset( $args[1] ) ? (string) $args[1] : '';
		if ( self::block_xmlrpc_method( 'wp.editProfile', $username ) ) {
			return new IXR_Error( 403, __( 'Demo accounts cannot be changed.', self::TEXT_DOMAIN ) );
		}
		return self::call_xmlrpc_original( self::$xmlrpc_edit_profile, 'wp_editProfile', $args );
	}

	/**
	 * @param array<int, mixed> $args XML-RPC args.
	 * @return mixed
	 */
	public static function xmlrpc_set_options( array $args ) {
		$username = isset( $args[1] ) ? (string) $args[1] : '';
		if ( self::block_xmlrpc_method( 'wp.setOptions', $username ) ) {
			return new IXR_Error( 403, __( 'Demo accounts cannot be changed.', self::TEXT_DOMAIN ) );
		}
		return self::call_xmlrpc_original( self::$xmlrpc_set_options, 'wp_setOptions', $args );
	}

	/**
	 * @param array<string, mixed> $file Upload file.
	 * @return array<string, mixed>
	 */
	public static function filter_upload( array $file ): array {
		if ( ! self::current_is_guarded() ) {
			return $file;
		}
		$size   = isset( $file['size'] ) ? (int) $file['size'] : 0;
		$reason = self::upload_block_reason( $size, self::max_upload_bytes() );
		if ( '' !== $reason ) {
			$file['error'] = __( $reason, self::TEXT_DOMAIN );
		}
		return $file;
	}

	/**
	 * @param mixed   $check        Short-circuit.
	 * @param WP_Post $post         Post.
	 * @param bool    $force_delete Force flag.
	 * @return mixed
	 */
	public static function filter_pre_delete_post( $check, $post, bool $force_delete ) {
		unset( $force_delete );
		if ( null !== $check || self::$restoring || ! self::current_is_guarded() || self::actor_is_admin() ) {
			return $check;
		}
		if ( ! $post instanceof WP_Post ) {
			return $check;
		}
		if ( in_array( $post->post_type, self::CONTENT_POST_TYPES, true ) ) {
			return $check;
		}
		if ( 'attachment' === $post->post_type ) {
			if ( (int) $post->post_author === get_current_user_id() || self::attachment_is_choir_media( (int) $post->ID ) ) {
				return $check;
			}
		}
		return false;
	}

	/**
	 * @param list<string>     $caps    Caps.
	 * @param string           $cap     Capability.
	 * @param int              $user_id Actor.
	 * @param array<int,mixed> $args    Args.
	 * @return list<string>
	 */
	public static function filter_map_meta_cap( array $caps, string $cap, int $user_id, array $args ): array {
		if ( self::$restoring || self::$resolving_caps ) {
			return $caps;
		}
		if ( ! in_array( $cap, array( 'delete_user', 'remove_user', 'promote_user' ), true ) ) {
			return $caps;
		}
		$target = isset( $args[0] ) ? (int) $args[0] : 0;
		if ( ! self::is_guarded_user_id( $target ) ) {
			return $caps;
		}
		// user_can() maps caps again. Use the passed user id, not the current user.
		self::$resolving_caps = true;
		$is_manager           = function_exists( 'user_can' ) && user_can( $user_id, 'manage_options' );
		self::$resolving_caps = false;
		if ( $is_manager ) {
			return $caps;
		}
		return array( 'do_not_allow' );
	}

	public static function block_delete_user( int $user_id ): void {
		if ( self::$restoring || ! self::is_guarded_user_id( $user_id ) || self::actor_is_admin() ) {
			return;
		}
		wp_die( esc_html__( 'Demo accounts cannot be deleted.', self::TEXT_DOMAIN ), '', array( 'response' => 403 ) );
	}

	public static function render_notice(): void {
		if ( self::$notice_rendered || ( function_exists( 'is_admin' ) && is_admin() ) ) {
			return;
		}
		self::$notice_rendered = true;
		echo '<div class="compath-demo-guard-notice" role="status">' . esc_html( self::notice_text() ) . '</div>';
		echo '<style>.compath-demo-guard-notice{box-sizing:border-box;margin:0;padding:10px 16px;background:#1d2327;color:#fff;text-align:center;font-size:14px;line-height:1.4;}</style>';
	}

	public static function rehearsal_url(): string {
		$path = '/rehearsal/';
		if ( defined( 'COMPATH_DEMO_REHEARSAL_PATH' ) && is_string( COMPATH_DEMO_REHEARSAL_PATH ) && '' !== COMPATH_DEMO_REHEARSAL_PATH ) {
			$path = COMPATH_DEMO_REHEARSAL_PATH;
		}
		if ( function_exists( 'home_url' ) ) {
			$url = home_url( $path );
			return is_string( $url ) ? $url : $path;
		}
		return $path;
	}

	public static function maybe_schedule_wp_cron(): void {
		$hook = self::cron_hook();
		$use  = defined( 'COMPATH_DEMO_GUARD_USE_WP_CRON' ) && COMPATH_DEMO_GUARD_USE_WP_CRON;
		$next = wp_next_scheduled( $hook );
		if ( ! $use ) {
			if ( $next ) {
				wp_unschedule_event( $next, $hook );
			}
			return;
		}
		if ( ! $next ) {
			wp_schedule_event( self::next_tallinn_three_am( time() ), 'daily', $hook );
		}
	}

	public static function cron_restore(): void {
		self::restore_baseline( 'wp-cron' );
	}

	public static function register_cli(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) {
			return;
		}
		\WP_CLI::add_command( 'compath-demo baseline-save', array( self::class, 'cli_save' ) );
		\WP_CLI::add_command( 'compath-demo baseline-restore', array( self::class, 'cli_restore' ) );
	}

	/**
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Associative args.
	 */
	public static function cli_save( array $args = array(), array $assoc_args = array() ): void {
		unset( $args, $assoc_args );
		$result = self::save_baseline();
		if ( ! empty( $result['ok'] ) ) {
			\WP_CLI::success( (string) $result['message'] );
			return;
		}
		\WP_CLI::error( (string) $result['message'] );
	}

	/**
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Associative args.
	 */
	public static function cli_restore( array $args = array(), array $assoc_args = array() ): void {
		unset( $args, $assoc_args );
		$result = self::restore_baseline( 'cli' );
		if ( ! empty( $result['ok'] ) ) {
			\WP_CLI::success( (string) $result['message'] );
			return;
		}
		\WP_CLI::error( (string) $result['message'] );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function save_baseline(): array {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return self::empty_result( false, 'Database is not available.' );
		}

		$logins = self::logins();
		if ( array() === $logins ) {
			return self::empty_result( false, 'Guarded login list is empty.' );
		}

		$song_ids = self::ids_for_post_types( self::CONTENT_POST_TYPES );
		$attachment_ids = self::choir_attachment_ids( $song_ids );
		$post_ids = array_values( array_unique( array_merge( $song_ids, $attachment_ids ) ) );
		$posts    = self::rows_by_ids( $wpdb->posts, 'ID', $post_ids );
		$postmeta = self::rows_by_ids( $wpdb->postmeta, 'post_id', $post_ids );

		$users = self::user_rows( $logins );
		$user_ids = array();
		foreach ( $users as $user ) {
			$user_ids[] = (int) $user['ID'];
		}
		$usermeta = self::rows_by_ids( $wpdb->usermeta, 'user_id', $user_ids );

		$terms = self::plugin_terms( $post_ids );
		$options = self::plugin_options();
		$files = self::upload_relatives_from_meta( $postmeta );

		$payload = array(
			'version'            => self::BASELINE_VERSION,
			'table_prefix'       => (string) $wpdb->prefix,
			'logins'             => $logins,
			'posts'              => $posts,
			'postmeta'           => $postmeta,
			'users'              => $users,
			'usermeta'           => $usermeta,
			'terms'              => $terms['terms'],
			'term_taxonomy'      => $terms['term_taxonomy'],
			'term_relationships' => $terms['term_relationships'],
			'options'            => $options,
			'files'              => $files,
		);

		$errors = self::write_snapshot( self::baseline_dir(), $payload, $logins );
		if ( array() !== $errors ) {
			return self::empty_result( false, implode( '; ', $errors ) );
		}

		self::copy_uploads_to_baseline( $files );

		$songs  = 0;
		$tracks = 0;
		$media  = 0;
		foreach ( $posts as $post ) {
			if ( 'choir_song' === ( $post['post_type'] ?? '' ) ) {
				++$songs;
			} elseif ( 'choir_track' === ( $post['post_type'] ?? '' ) ) {
				++$tracks;
			} elseif ( 'attachment' === ( $post['post_type'] ?? '' ) ) {
				++$media;
			}
		}

		return array(
			'ok'      => true,
			'message' => sprintf( 'Saved baseline (%d songs, %d tracks, %d media, %d demo accounts).', $songs, $tracks, $media, count( $users ) ),
			'songs'   => $songs,
			'tracks'  => $tracks,
			'media'   => $media,
			'users'   => count( $users ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function restore_baseline( string $source = 'cron' ): array {
		unset( $source );
		$file = self::baseline_file();
		if ( ! is_file( $file ) ) {
			return self::empty_result( false, 'No baseline file. Run baseline-save first.' );
		}
		$payload = json_decode( (string) file_get_contents( $file ), true );
		if ( ! is_array( $payload ) ) {
			return self::empty_result( false, 'Baseline file is not valid JSON.' );
		}
		$errors = self::validate_snapshot_payload( $payload, self::logins() );
		if ( array() !== $errors ) {
			return self::empty_result( false, implode( '; ', $errors ) );
		}

		self::$restoring = true;
		try {
			$content = self::restore_content( $payload );
			if ( empty( $content['ok'] ) ) {
				return $content;
			}
			$users = self::restore_accounts( $payload );
			if ( empty( $users['ok'] ) ) {
				return $users;
			}
			return array(
				'ok'      => true,
				'message' => sprintf(
					'Restored baseline (%d songs, %d tracks, %d media, %d demo accounts). Other users were not changed.',
					(int) $content['songs'],
					(int) $content['tracks'],
					(int) $content['media'],
					(int) $users['users']
				),
				'songs'   => (int) $content['songs'],
				'tracks'  => (int) $content['tracks'],
				'media'   => (int) $content['media'],
				'users'   => (int) $users['users'],
			);
		} finally {
			self::$restoring = false;
		}
	}

	/**
	 * Reset demo account passwords, emails, names, and roles from configuration.
	 * Never updates a user whose login is outside the guarded list.
	 *
	 * @return array<string, mixed>
	 */
	public static function restore_accounts_from_config(): array {
		self::$restoring = true;
		try {
			return self::apply_account_specs( self::account_baseline() );
		} finally {
			self::$restoring = false;
		}
	}

	/**
	 * @param array<string, mixed> $payload Snapshot.
	 * @return array<string, mixed>
	 */
	private static function restore_content( array $payload ): array {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return self::empty_result( false, 'Database is not available.' );
		}

		$posts = isset( $payload['posts'] ) && is_array( $payload['posts'] ) ? $payload['posts'] : array();
		foreach ( $posts as $post ) {
			if ( ! is_array( $post ) ) {
				continue;
			}
			$id = (int) ( $post['ID'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}
			$existing_type = $wpdb->get_var( $wpdb->prepare( "SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", $id ) );
			if ( is_string( $existing_type ) && ! in_array( $existing_type, array( 'choir_song', 'choir_track', 'attachment' ), true ) ) {
				return self::empty_result( false, 'Refusing to overwrite post ' . $id . ' (' . $existing_type . ').' );
			}
		}

		$current_song_ids = self::ids_for_post_types( self::CONTENT_POST_TYPES );
		$current_media    = self::choir_attachment_ids( $current_song_ids );
		$snapshot_media   = array();
		foreach ( $posts as $post ) {
			if ( is_array( $post ) && 'attachment' === ( $post['post_type'] ?? '' ) ) {
				$snapshot_media[] = (int) $post['ID'];
			}
		}
		$media_to_delete = array_values( array_unique( array_merge( $current_media, $snapshot_media ) ) );
		$removed_files   = self::attached_files_for_ids( $media_to_delete );

		self::delete_plugin_posts( $media_to_delete, array( 'attachment' ) );
		self::delete_plugin_posts( $current_song_ids, self::CONTENT_POST_TYPES );

		$snap_users = array();
		foreach ( (array) ( $payload['users'] ?? array() ) as $user ) {
			if ( is_array( $user ) ) {
				$snap_users[ strtolower( (string) $user['user_login'] ) ] = (int) $user['ID'];
			}
		}
		$live_users = array();
		foreach ( self::logins() as $login ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_login = %s", $login ), ARRAY_A );
			if ( is_array( $row ) ) {
				$live_users[ $login ] = (int) $row['ID'];
			}
		}

		$songs  = 0;
		$tracks = 0;
		$media  = 0;
		foreach ( $posts as $post ) {
			if ( ! is_array( $post ) ) {
				continue;
			}
			$row = self::pick_post_row( $post );
			if ( ! isset( $row['ID'], $row['post_type'] ) ) {
				continue;
			}
			$row['post_author'] = self::rewrite_post_author( (int) $row['post_author'], $snap_users, $live_users );
			$wpdb->insert( $wpdb->posts, $row );
			if ( 'choir_song' === $row['post_type'] ) {
				++$songs;
			} elseif ( 'choir_track' === $row['post_type'] ) {
				++$tracks;
			} elseif ( 'attachment' === $row['post_type'] ) {
				++$media;
			}
		}

		foreach ( (array) ( $payload['postmeta'] ?? array() ) as $meta ) {
			if ( ! is_array( $meta ) ) {
				continue;
			}
			$wpdb->insert(
				$wpdb->postmeta,
				array(
					'post_id'    => (int) ( $meta['post_id'] ?? 0 ),
					'meta_key'   => (string) ( $meta['meta_key'] ?? '' ),
					'meta_value' => (string) ( $meta['meta_value'] ?? '' ),
				)
			);
		}

		self::restore_terms( $payload );
		self::restore_options( $payload );
		self::copy_baseline_uploads( (array) ( $payload['files'] ?? array() ) );
		self::delete_removed_uploads( $removed_files, (array) ( $payload['files'] ?? array() ) );
		self::delete_extra_demo_uploads( array_values( $live_users ), $snapshot_media, (array) ( $payload['files'] ?? array() ) );

		return array(
			'ok'      => true,
			'message' => 'content restored',
			'songs'   => $songs,
			'tracks'  => $tracks,
			'media'   => $media,
			'users'   => 0,
		);
	}

	/**
	 * @param array<string, mixed> $payload Snapshot.
	 * @return array<string, mixed>
	 */
	private static function restore_accounts( array $payload ): array {
		global $wpdb;
		$prefix_from = isset( $payload['table_prefix'] ) ? (string) $payload['table_prefix'] : (string) $wpdb->prefix;
		$prefix_to   = (string) $wpdb->prefix;
		$by_login    = array();
		foreach ( (array) ( $payload['users'] ?? array() ) as $user ) {
			if ( is_array( $user ) ) {
				$by_login[ strtolower( (string) ( $user['user_login'] ?? '' ) ) ] = $user;
			}
		}

		$applied = self::apply_account_specs( self::account_baseline() );
		if ( empty( $applied['ok'] ) ) {
			return $applied;
		}

		foreach ( $by_login as $login => $snap_user ) {
			if ( ! self::is_guarded_login( $login ) ) {
				continue;
			}
			$where = self::live_user_where( $login );
			if ( null === $where ) {
				continue;
			}
			$snap_id = (int) ( $snap_user['ID'] ?? 0 );
			foreach ( (array) ( $payload['usermeta'] ?? array() ) as $meta ) {
				if ( ! is_array( $meta ) || (int) ( $meta['user_id'] ?? 0 ) !== $snap_id ) {
					continue;
				}
				$key = self::remap_user_meta_key( (string) ( $meta['meta_key'] ?? '' ), $prefix_from, $prefix_to );
				if ( str_ends_with( $key, 'capabilities' ) && self::serialized_grants_role( (string) ( $meta['meta_value'] ?? '' ), 'administrator' ) ) {
					return self::empty_result( false, 'Refusing administrator capability on a demo account.' );
				}
				$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $where['ID'], 'meta_key' => $key ) );
				$wpdb->insert(
					$wpdb->usermeta,
					array(
						'user_id'    => $where['ID'],
						'meta_key'   => $key,
						'meta_value' => (string) ( $meta['meta_value'] ?? '' ),
					)
				);
			}
			$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $where['ID'], 'meta_key' => '_application_passwords' ) );
			$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $where['ID'], 'meta_key' => 'session_tokens' ) );
		}

		// Config wins over the snapshot for password, email, name, and role.
		return self::apply_account_specs( self::account_baseline() );
	}

	/**
	 * @param array<string, array<string, mixed>> $specs Account specs keyed by login.
	 * @return array<string, mixed>
	 */
	private static function apply_account_specs( array $specs ): array {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return self::empty_result( false, 'Database is not available.' );
		}

		$count = 0;
		foreach ( $specs as $login => $spec ) {
			if ( ! is_string( $login ) || ! is_array( $spec ) || ! self::is_guarded_login( $login ) ) {
				continue;
			}
			$role = self::sanitize_demo_role( (string) ( $spec['role'] ?? '' ) );
			if ( '' === $role ) {
				continue;
			}

			$where = self::live_user_where( $login );
			if ( null === $where ) {
				$user_id = wp_insert_user(
					array(
						'user_login'   => $login,
						'user_email'   => (string) ( $spec['user_email'] ?? '' ),
						'user_pass'    => (string) ( $spec['user_pass'] ?? '' ),
						'display_name' => (string) ( $spec['display_name'] ?? $login ),
						'nickname'     => (string) ( $spec['nickname'] ?? $login ),
						'first_name'   => (string) ( $spec['first_name'] ?? '' ),
						'last_name'    => (string) ( $spec['last_name'] ?? '' ),
						'role'         => $role,
					)
				);
				if ( is_wp_error( $user_id ) ) {
					return self::empty_result( false, 'Could not recreate demo account ' . $login . '.' );
				}
				$where = self::live_user_where( $login );
				if ( null === $where ) {
					return self::empty_result( false, 'Could not find recreated demo account ' . $login . '.' );
				}
			}

			$fields = array(
				'user_email'          => (string) ( $spec['user_email'] ?? '' ),
				'user_nicename'       => sanitize_title( $login ),
				'display_name'        => (string) ( $spec['display_name'] ?? $login ),
				'user_url'            => '',
				'user_activation_key' => '',
			);
			if ( isset( $spec['user_pass'] ) && '' !== (string) $spec['user_pass'] ) {
				$fields['user_pass'] = wp_hash_password( (string) $spec['user_pass'] );
			}
			$updated = $wpdb->update( $wpdb->users, $fields, $where );
			if ( false === $updated ) {
				return self::empty_result( false, 'Could not update demo account ' . $login . '.' );
			}

			$prefix = (string) $wpdb->prefix;
			self::replace_user_meta( $where['ID'], 'nickname', (string) ( $spec['nickname'] ?? $login ) );
			self::replace_user_meta( $where['ID'], 'first_name', (string) ( $spec['first_name'] ?? '' ) );
			self::replace_user_meta( $where['ID'], 'last_name', (string) ( $spec['last_name'] ?? '' ) );
			self::replace_user_meta( $where['ID'], $prefix . 'capabilities', self::role_capabilities_meta( $role ) );
			self::replace_user_meta( $where['ID'], $prefix . 'user_level', '0' );
			$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $where['ID'], 'meta_key' => '_application_passwords' ) );
			$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $where['ID'], 'meta_key' => 'session_tokens' ) );
			clean_user_cache( $where['ID'] );
			++$count;
		}

		return array(
			'ok'      => true,
			'message' => 'demo accounts restored',
			'songs'   => 0,
			'tracks'  => 0,
			'media'   => 0,
			'users'   => $count,
		);
	}

	private static function role_capabilities_meta( string $role ): string {
		$caps = array( $role => true );
		if ( 'choir_voice_leader' === $role && class_exists( 'Choir_Rehearsal_Roles' ) ) {
			$caps = array_merge( $caps, Choir_Rehearsal_Roles::voice_leader_capabilities() );
		} elseif ( 'choir_singer' === $role && class_exists( 'Choir_Rehearsal_Roles' ) ) {
			$caps = array_merge( $caps, Choir_Rehearsal_Roles::singer_capabilities() );
		} elseif ( 'choir_voice_leader' === $role ) {
			$caps['read']                          = true;
			$caps['choir_rehearsal_listen']        = true;
			$caps['choir_rehearsal_manage_songs']  = true;
			$caps['upload_files']                  = true;
		} else {
			$caps['read']                   = true;
			$caps['choir_rehearsal_listen'] = true;
		}
		return (string) maybe_serialize( $caps );
	}

	/**
	 * @return array{ID:int,user_login:string}|null
	 */
	private static function live_user_where( string $login ): ?array {
		global $wpdb;
		if ( ! self::is_guarded_login( $login ) ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT ID, user_login FROM {$wpdb->users} WHERE user_login = %s", $login ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return null;
		}
		return self::user_restore_where( (int) $row['ID'], (string) $row['user_login'], self::logins() );
	}

	private static function replace_user_meta( int $user_id, string $key, string $value ): void {
		global $wpdb;
		$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $user_id, 'meta_key' => $key ) );
		$wpdb->insert(
			$wpdb->usermeta,
			array(
				'user_id'    => $user_id,
				'meta_key'   => $key,
				'meta_value' => $value,
			)
		);
	}

	/**
	 * @param list<string> $types Post types.
	 * @return list<int>
	 */
	private static function ids_for_post_types( array $types ): array {
		global $wpdb;
		if ( array() === $types ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$sql          = $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ($placeholders)", ...$types );
		$ids          = $wpdb->get_col( $sql );
		return array_values( array_map( 'intval', is_array( $ids ) ? $ids : array() ) );
	}

	/**
	 * @param list<int> $song_ids Song and track IDs.
	 * @return list<int>
	 */
	private static function choir_attachment_ids( array $song_ids ): array {
		global $wpdb;
		$ids = array();
		if ( array() !== $song_ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $song_ids ), '%d' ) );
			$parent_sql   = $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_parent IN ($placeholders)", ...$song_ids );
			$parents      = $wpdb->get_col( $parent_sql );
			foreach ( (array) $parents as $id ) {
				$ids[] = (int) $id;
			}

			$meta_keys    = self::ATTACHMENT_META_KEYS;
			$key_slots    = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
			$meta_sql     = $wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($placeholders) AND meta_key IN ($key_slots)",
				...array_merge( $song_ids, $meta_keys )
			);
			$meta_values  = $wpdb->get_col( $meta_sql );
			foreach ( (array) $meta_values as $value ) {
				$attachment_id = (int) $value;
				if ( $attachment_id > 0 ) {
					$ids[] = $attachment_id;
				}
			}
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * @param list<int> $ids IDs.
	 * @return list<array<string, mixed>>
	 */
	private static function rows_by_ids( string $table, string $column, array $ids ): array {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( array() === $ids ) {
			return array();
		}
		if ( ! in_array( $column, array( 'ID', 'post_id', 'user_id' ), true ) ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = $wpdb->prepare( "SELECT * FROM {$table} WHERE {$column} IN ($placeholders)", ...$ids );
		$rows         = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param list<string> $logins Logins.
	 * @return list<array<string, mixed>>
	 */
	private static function user_rows( array $logins ): array {
		global $wpdb;
		if ( array() === $logins ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $logins ), '%s' ) );
		$sql          = $wpdb->prepare(
			"SELECT ID, user_login, user_pass, user_email, user_nicename, display_name, user_url, user_registered, user_status FROM {$wpdb->users} WHERE user_login IN ($placeholders)",
			...$logins
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$safe = array();
		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) || ! self::is_guarded_login( (string) ( $row['user_login'] ?? '' ) ) ) {
				continue;
			}
			$safe[] = $row;
		}
		return $safe;
	}

	/**
	 * @param list<int> $post_ids Post IDs.
	 * @return array{terms:list<array<string,mixed>>,term_taxonomy:list<array<string,mixed>>,term_relationships:list<array<string,mixed>>}
	 */
	private static function plugin_terms( array $post_ids ): array {
		global $wpdb;
		$empty = array(
			'terms'              => array(),
			'term_taxonomy'      => array(),
			'term_relationships' => array(),
		);
		if ( array() === $post_ids ) {
			$tax_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", self::TAXONOMY ), ARRAY_A );
		} else {
			$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
			$tax_rows     = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT tt.* FROM {$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy = %s AND tr.object_id IN ($placeholders)",
					...array_merge( array( self::TAXONOMY ), $post_ids )
				),
				ARRAY_A
			);
		}
		$tax_rows = is_array( $tax_rows ) ? $tax_rows : array();
		$term_ids = array();
		$tt_ids   = array();
		foreach ( $tax_rows as $row ) {
			if ( self::TAXONOMY !== ( $row['taxonomy'] ?? '' ) ) {
				continue;
			}
			$term_ids[] = (int) $row['term_id'];
			$tt_ids[]   = (int) $row['term_taxonomy_id'];
		}
		$terms = array();
		if ( array() !== $term_ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
			$terms        = $wpdb->get_results( $wpdb->prepare( "SELECT term_id, name, slug, term_group FROM {$wpdb->terms} WHERE term_id IN ($placeholders)", ...$term_ids ), ARRAY_A );
		}
		$rels = array();
		if ( array() !== $tt_ids && array() !== $post_ids ) {
			$tt_slots   = implode( ',', array_fill( 0, count( $tt_ids ), '%d' ) );
			$post_slots = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
			$rels       = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT object_id, term_taxonomy_id, term_order FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ($tt_slots) AND object_id IN ($post_slots)",
					...array_merge( $tt_ids, $post_ids )
				),
				ARRAY_A
			);
		}
		return array(
			'terms'              => is_array( $terms ) ? $terms : array(),
			'term_taxonomy'      => $tax_rows,
			'term_relationships' => is_array( $rels ) ? $rels : array(),
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function plugin_options(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( 'choir_rehearsal_' ) . '%'
			),
			ARRAY_A
		);
		$safe = array();
		foreach ( (array) $rows as $row ) {
			if ( is_array( $row ) && self::is_restorable_option( (string) ( $row['option_name'] ?? '' ) ) ) {
				$safe[] = $row;
			}
		}
		return $safe;
	}

	/**
	 * @param list<array<string, mixed>> $meta_rows Meta rows.
	 * @return list<string>
	 */
	public static function upload_relatives_from_meta( array $meta_rows ): array {
		$paths = array();
		foreach ( $meta_rows as $row ) {
			if ( ! is_array( $row ) || '_wp_attached_file' !== ( $row['meta_key'] ?? '' ) ) {
				continue;
			}
			$safe = self::safe_upload_relative( (string) ( $row['meta_value'] ?? '' ) );
			if ( null !== $safe ) {
				$paths[] = $safe;
			}
		}
		return array_values( array_unique( $paths ) );
	}

	/**
	 * @param list<int>    $ids   Post IDs.
	 * @param list<string> $types Allowed types.
	 */
	private static function delete_plugin_posts( array $ids, array $types ): void {
		global $wpdb;
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id <= 0 ) {
				continue;
			}
			$type = $wpdb->get_var( $wpdb->prepare( "SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", $id ) );
			if ( ! is_string( $type ) || ! in_array( $type, $types, true ) ) {
				continue;
			}
			$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $id ), array( '%d' ) );
			$wpdb->delete( $wpdb->term_relationships, array( 'object_id' => $id ), array( '%d' ) );
			$wpdb->delete(
				$wpdb->posts,
				array(
					'ID'        => $id,
					'post_type' => $type,
				),
				array( '%d', '%s' )
			);
		}
	}

	/**
	 * @param array<string, mixed> $post Post row.
	 * @return array<string, mixed>
	 */
	private static function pick_post_row( array $post ): array {
		$columns = array(
			'ID',
			'post_author',
			'post_date',
			'post_date_gmt',
			'post_content',
			'post_title',
			'post_excerpt',
			'post_status',
			'comment_status',
			'ping_status',
			'post_password',
			'post_name',
			'to_ping',
			'pinged',
			'post_modified',
			'post_modified_gmt',
			'post_content_filtered',
			'post_parent',
			'guid',
			'menu_order',
			'post_type',
			'post_mime_type',
			'comment_count',
		);
		$row = array();
		foreach ( $columns as $column ) {
			if ( array_key_exists( $column, $post ) ) {
				$row[ $column ] = $post[ $column ];
			}
		}
		return $row;
	}

	/**
	 * @param array<string, mixed> $payload Snapshot.
	 */
	private static function restore_terms( array $payload ): void {
		global $wpdb;
		$map = array();
		foreach ( (array) ( $payload['term_taxonomy'] ?? array() ) as $tax ) {
			if ( ! is_array( $tax ) || self::TAXONOMY !== ( $tax['taxonomy'] ?? '' ) ) {
				continue;
			}
			$snap_tt   = (int) ( $tax['term_taxonomy_id'] ?? 0 );
			$snap_term = (int) ( $tax['term_id'] ?? 0 );
			$slug      = '';
			$name      = '';
			foreach ( (array) ( $payload['terms'] ?? array() ) as $term ) {
				if ( is_array( $term ) && (int) ( $term['term_id'] ?? 0 ) === $snap_term ) {
					$slug = (string) ( $term['slug'] ?? '' );
					$name = (string) ( $term['name'] ?? $slug );
				}
			}
			if ( '' === $slug ) {
				continue;
			}
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT tt.term_taxonomy_id FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE t.slug = %s AND tt.taxonomy = %s",
					$slug,
					self::TAXONOMY
				),
				ARRAY_A
			);
			if ( is_array( $existing ) ) {
				$map[ $snap_tt ] = (int) $existing['term_taxonomy_id'];
				continue;
			}
			$wpdb->insert(
				$wpdb->terms,
				array(
					'name'       => '' !== $name ? $name : $slug,
					'slug'       => $slug,
					'term_group' => 0,
				)
			);
			$term_id = (int) $wpdb->insert_id;
			$wpdb->insert(
				$wpdb->term_taxonomy,
				array(
					'term_id'     => $term_id,
					'taxonomy'    => self::TAXONOMY,
					'description' => (string) ( $tax['description'] ?? '' ),
					'parent'      => 0,
					'count'       => (int) ( $tax['count'] ?? 0 ),
				)
			);
			$map[ $snap_tt ] = (int) $wpdb->insert_id;
		}

		foreach ( (array) ( $payload['term_relationships'] ?? array() ) as $rel ) {
			if ( ! is_array( $rel ) ) {
				continue;
			}
			$snap_tt = (int) ( $rel['term_taxonomy_id'] ?? 0 );
			if ( ! isset( $map[ $snap_tt ] ) ) {
				continue;
			}
			$wpdb->insert(
				$wpdb->term_relationships,
				array(
					'object_id'        => (int) ( $rel['object_id'] ?? 0 ),
					'term_taxonomy_id' => $map[ $snap_tt ],
					'term_order'       => (int) ( $rel['term_order'] ?? 0 ),
				)
			);
		}
	}

	/**
	 * @param array<string, mixed> $payload Snapshot.
	 */
	private static function restore_options( array $payload ): void {
		foreach ( (array) ( $payload['options'] ?? array() ) as $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}
			$name = (string) ( $option['option_name'] ?? '' );
			if ( ! self::is_restorable_option( $name ) ) {
				continue;
			}
			update_option( $name, maybe_unserialize( (string) ( $option['option_value'] ?? '' ) ), (string) ( $option['autoload'] ?? 'yes' ) );
		}
	}

	/**
	 * @param list<int> $ids Attachment IDs.
	 * @return list<string>
	 */
	private static function attached_files_for_ids( array $ids ): array {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( array() === $ids ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = $wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND post_id IN ($placeholders)",
			...$ids
		);
		$values = $wpdb->get_col( $sql );
		$paths  = array();
		foreach ( (array) $values as $value ) {
			$safe = self::safe_upload_relative( (string) $value );
			if ( null !== $safe ) {
				$paths[] = $safe;
			}
		}
		return array_values( array_unique( $paths ) );
	}

	/**
	 * @param list<string> $files Relative upload paths.
	 */
	private static function copy_uploads_to_baseline( array $files ): void {
		$uploads = self::uploads_basedir();
		$dest    = self::baseline_dir() . '/uploads';
		if ( '' === $uploads ) {
			return;
		}
		foreach ( $files as $relative ) {
			$relative = self::safe_upload_relative( (string) $relative );
			if ( null === $relative ) {
				continue;
			}
			$from = $uploads . '/' . $relative;
			$to   = $dest . '/' . $relative;
			if ( ! is_file( $from ) ) {
				continue;
			}
			$dir = dirname( $to );
			if ( ! is_dir( $dir ) ) {
				mkdir( $dir, 0750, true );
			}
			copy( $from, $to );
		}
	}

	/**
	 * @param list<mixed> $files Relative upload paths.
	 */
	private static function copy_baseline_uploads( array $files ): void {
		$uploads = self::uploads_basedir();
		$source  = self::baseline_dir() . '/uploads';
		if ( '' === $uploads ) {
			return;
		}
		foreach ( $files as $relative ) {
			$relative = self::safe_upload_relative( (string) $relative );
			if ( null === $relative ) {
				continue;
			}
			$from = $source . '/' . $relative;
			$to   = $uploads . '/' . $relative;
			if ( ! self::path_is_inside( $to, $uploads ) || ! is_file( $from ) ) {
				continue;
			}
			$dir = dirname( $to );
			if ( ! is_dir( $dir ) ) {
				mkdir( $dir, 0755, true );
			}
			copy( $from, $to );
		}
	}

	/**
	 * @param list<string> $removed Paths removed with deleted attachments.
	 * @param list<mixed>  $keep    Paths that belong to the baseline.
	 */
	private static function delete_removed_uploads( array $removed, array $keep ): void {
		$uploads = self::uploads_basedir();
		if ( '' === $uploads ) {
			return;
		}
		$keep_safe = array();
		foreach ( $keep as $path ) {
			$safe = self::safe_upload_relative( (string) $path );
			if ( null !== $safe ) {
				$keep_safe[] = $safe;
			}
		}
		foreach ( $removed as $relative ) {
			if ( in_array( $relative, $keep_safe, true ) ) {
				continue;
			}
			$target = $uploads . '/' . $relative;
			if ( self::path_is_inside( $target, $uploads ) && is_file( $target ) ) {
				unlink( $target );
			}
		}
	}

	private static function uploads_basedir(): string {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return '';
		}
		$uploads = wp_upload_dir();
		if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) ) {
			return '';
		}
		return isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
	}

	public static function path_is_inside( string $path, string $root ): bool {
		$root = rtrim( str_replace( '\\', '/', $root ), '/' ) . '/';
		$path = str_replace( '\\', '/', $path );
		return str_starts_with( $path, $root );
	}

	/**
	 * Remove uploads created by demo accounts that are not part of the baseline.
	 * Attachments parented to pages or posts are left in place.
	 *
	 * @param list<int>   $demo_user_ids Live demo user IDs.
	 * @param list<int>   $keep_ids      Baseline attachment IDs.
	 * @param list<mixed> $keep_files    Baseline relative paths.
	 */
	private static function delete_extra_demo_uploads( array $demo_user_ids, array $keep_ids, array $keep_files ): void {
		global $wpdb;
		$demo_user_ids = array_values( array_filter( array_map( 'intval', $demo_user_ids ) ) );
		if ( array() === $demo_user_ids ) {
			return;
		}
		$placeholders = implode( ',', array_fill( 0, count( $demo_user_ids ), '%d' ) );
		$sql          = $wpdb->prepare(
			"SELECT ID, post_parent FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_author IN ($placeholders)",
			...$demo_user_ids
		);
		$rows   = $wpdb->get_results( $sql, ARRAY_A );
		$remove = array();
		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = (int) ( $row['ID'] ?? 0 );
			if ( $id <= 0 || in_array( $id, $keep_ids, true ) ) {
				continue;
			}
			$parent = (int) ( $row['post_parent'] ?? 0 );
			if ( $parent > 0 ) {
				$parent_type = get_post_type( $parent );
				if ( is_string( $parent_type ) && ! in_array( $parent_type, self::CONTENT_POST_TYPES, true ) ) {
					continue;
				}
			}
			$remove[] = $id;
		}
		$files = self::attached_files_for_ids( $remove );
		self::delete_plugin_posts( $remove, array( 'attachment' ) );
		self::delete_removed_uploads( $files, $keep_files );
	}

	private static function attachment_is_choir_media( int $attachment_id ): bool {
		if ( $attachment_id <= 0 ) {
			return false;
		}
		$parent = (int) wp_get_post_parent_id( $attachment_id );
		if ( $parent > 0 ) {
			$parent_type = get_post_type( $parent );
			if ( is_string( $parent_type ) && in_array( $parent_type, self::CONTENT_POST_TYPES, true ) ) {
				return true;
			}
		}
		global $wpdb;
		$keys         = self::ATTACHMENT_META_KEYS;
		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$sql          = $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_value = %s AND meta_key IN ($placeholders) LIMIT 1",
			...array_merge( array( (string) $attachment_id ), $keys )
		);
		$found = (int) $wpdb->get_var( $sql );
		return $found > 0;
	}

	private static function rest_target_is_guarded( string $route ): bool {
		if ( str_contains( $route, '/users/me' ) ) {
			return self::current_is_guarded();
		}
		if ( preg_match( '#/users/(\d+)#', $route, $matches ) ) {
			return self::is_guarded_user_id( (int) $matches[1] );
		}
		return false;
	}

	/**
	 * @param mixed             $original Stored callback.
	 * @param string            $method   Server method name.
	 * @param array<int, mixed> $args     Arguments.
	 * @return mixed
	 */
	private static function call_xmlrpc_original( $original, string $method, array $args ) {
		if ( is_callable( $original ) && ! is_string( $original ) ) {
			return call_user_func( $original, $args );
		}
		if ( class_exists( 'wp_xmlrpc_server' ) ) {
			$server = new wp_xmlrpc_server();
			if ( is_callable( array( $server, $method ) ) ) {
				return $server->$method( $args );
			}
		}
		return new IXR_Error( 403, __( 'Demo accounts cannot be changed.', self::TEXT_DOMAIN ) );
	}

	/**
	 * False while the current user is still being determined. Calling
	 * wp_get_current_user() or current_user_can() in that window re-enters
	 * determine_current_user (application passwords, auth cookies, gettext).
	 */
	private static function current_user_ready(): bool {
		if ( self::$resolving_actor ) {
			return false;
		}
		if ( function_exists( 'did_action' ) && did_action( 'set_current_user' ) > 0 ) {
			return true;
		}
		global $current_user;
		return isset( $current_user ) && $current_user instanceof WP_User;
	}

	private static function current_is_guarded(): bool {
		if ( ! self::current_user_ready() || ! function_exists( 'wp_get_current_user' ) ) {
			return false;
		}
		self::$resolving_actor = true;
		$user                  = wp_get_current_user();
		self::$resolving_actor = false;
		return self::is_guarded_login( self::user_login_string( $user ) );
	}

	private static function actor_is_admin(): bool {
		if ( ! self::current_user_ready() || ! function_exists( 'current_user_can' ) ) {
			return false;
		}
		self::$resolving_actor = true;
		$is_admin              = current_user_can( 'manage_options' );
		self::$resolving_actor = false;
		return $is_admin;
	}

	private static function current_can_manage_songs(): bool {
		if ( ! self::current_user_ready() || ! function_exists( 'current_user_can' ) ) {
			return false;
		}
		self::$resolving_actor = true;
		$can                   = current_user_can( 'choir_rehearsal_manage_songs' ) || current_user_can( 'edit_choir_songs' );
		self::$resolving_actor = false;
		return $can;
	}

	private static function is_guarded_user_id( int $user_id ): bool {
		if ( $user_id <= 0 || ! function_exists( 'get_userdata' ) ) {
			return false;
		}
		$user = get_userdata( $user_id );
		return self::is_guarded_login( self::user_login_string( $user ) );
	}
}

if ( function_exists( 'add_action' ) ) {
	Compath_Rehearsal_Demo_Guard::register();
}
