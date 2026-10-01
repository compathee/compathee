<?php
/**
 * WordPress roles and capabilities for Choir Rehearsal.
 *
 * Display names stay English: Singer, Voice Leader, Administrator, Admin, Guest.
 * Do not wrap those names in gettext. Slugs and capabilities are not labels.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Roles {

	public const ROLE_SINGER       = 'choir_singer';
	public const ROLE_VOICE_LEADER = 'choir_voice_leader';

	/** Display name — do not wrap in gettext. */
	public const LABEL_SINGER = 'Singer';

	/** Display name — do not wrap in gettext. */
	public const LABEL_VOICE_LEADER = 'Voice Leader';

	/** Core WordPress role display name — do not wrap in gettext. */
	public const LABEL_ADMINISTRATOR = 'Administrator';

	/** Short form of the administrator role — do not wrap in gettext. */
	public const LABEL_ADMIN = 'Admin';

	/** Logged-out visitor label — do not wrap in gettext. Not a registered role. */
	public const LABEL_GUEST = 'Guest';

	public const CAP_LISTEN = 'choir_rehearsal_listen';
	public const CAP_MANAGE = 'choir_rehearsal_manage_songs';

	public static function register(): void {
		add_action( 'init', array( self::class, 'maybe_install' ), 4 );
		add_filter( 'gettext', array( self::class, 'keep_role_label_untranslated' ), 20, 3 );
		add_filter( 'gettext_with_context', array( self::class, 'keep_role_label_untranslated_context' ), 20, 4 );
	}

	/**
	 * English labels shown in the UI and on the Users role dropdown.
	 *
	 * @return list<string>
	 */
	public static function protected_role_labels(): array {
		return array(
			self::LABEL_SINGER,
			self::LABEL_VOICE_LEADER,
			self::LABEL_ADMINISTRATOR,
			self::LABEL_ADMIN,
			self::LABEL_GUEST,
		);
	}

	/**
	 * Stop this plugin from translating a role label if one is passed to gettext.
	 */
	public static function keep_role_label_untranslated( string $translation, string $text, string $domain ): string {
		if ( 'compath-choir-rehearsal' === $domain && in_array( $text, self::protected_role_labels(), true ) ) {
			return $text;
		}

		return $translation;
	}

	/**
	 * WordPress translates core role names (Administrator) via context "User role".
	 */
	public static function keep_role_label_untranslated_context( string $translation, string $text, string $context, string $domain ): string {
		if ( 'User role' === $context && in_array( $text, self::protected_role_labels(), true ) ) {
			return $text;
		}

		return self::keep_role_label_untranslated( $translation, $text, $domain );
	}

	public static function describe_activation(): string {
		return sprintf(
			/* translators: 1: Singer, 2: Voice Leader, 3: Administrator, 4: Guest. Substituted role names stay English. */
			__( 'Activation adds WordPress roles %1$s (listen) and %2$s (manage songs). Assign them under Users. Settings stay limited to %3$s. Public songs remain open to %4$s.', 'compath-choir-rehearsal' ),
			self::LABEL_SINGER,
			self::LABEL_VOICE_LEADER,
			self::LABEL_ADMINISTRATOR,
			self::LABEL_GUEST
		);
	}

	public static function describe_role_names_policy(): string {
		return sprintf(
			/* translators: 1: Singer, 2: Voice Leader, 3: Administrator, 4: Guest. Substituted role names stay English. */
			__( 'Role names %1$s, %2$s, %3$s, and %4$s are kept in English on purpose.', 'compath-choir-rehearsal' ),
			self::LABEL_SINGER,
			self::LABEL_VOICE_LEADER,
			self::LABEL_ADMINISTRATOR,
			self::LABEL_GUEST
		);
	}

	public static function describe_feedback_audience(): string {
		return sprintf(
			/* translators: 1: Singer, 2: Voice Leader, 3: Administrator, 4: Guest. Substituted role names stay English. */
			__( '%1$s, %2$s, %3$s, and %4$s can send a wish or bug from the song list. Lite and Pro both include this.', 'compath-choir-rehearsal' ),
			self::LABEL_SINGER,
			self::LABEL_VOICE_LEADER,
			self::LABEL_ADMINISTRATOR,
			self::LABEL_GUEST
		);
	}

	public static function ask_administrator_to_assign_library(): string {
		return sprintf(
			/* translators: 1: Administrator, 2: Singer, 3: Voice Leader. Substituted role names stay English. */
			__( 'Public songs above are open to everyone. Ask someone with the %1$s role to assign you the %2$s or %3$s role for the full rehearsal library.', 'compath-choir-rehearsal' ),
			self::LABEL_ADMINISTRATOR,
			self::LABEL_SINGER,
			self::LABEL_VOICE_LEADER
		);
	}

	public static function ask_administrator_to_assign_song(): string {
		return sprintf(
			/* translators: 1: Administrator, 2: Singer, 3: Voice Leader. Substituted role names stay English. */
			__( 'Ask someone with the %1$s role to assign you the %2$s or %3$s role to open private rehearsal songs.', 'compath-choir-rehearsal' ),
			self::LABEL_ADMINISTRATOR,
			self::LABEL_SINGER,
			self::LABEL_VOICE_LEADER
		);
	}

	public static function maybe_install(): void {
		$flag = (string) get_option( 'choir_rehearsal_roles_version', '' );
		if ( $flag === CHOIR_REHEARSAL_VERSION ) {
			return;
		}
		self::install();
	}

	public static function install(): void {
		self::ensure_roles();
		update_option( 'choir_rehearsal_roles_version', CHOIR_REHEARSAL_VERSION, false );
	}

	/**
	 * @return list<string>
	 */
	public static function song_caps(): array {
		return array(
			'edit_choir_song',
			'read_choir_song',
			'delete_choir_song',
			'edit_choir_songs',
			'edit_others_choir_songs',
			'publish_choir_songs',
			'read_private_choir_songs',
			'delete_choir_songs',
			'delete_private_choir_songs',
			'delete_published_choir_songs',
			'delete_others_choir_songs',
			'edit_private_choir_songs',
			'edit_published_choir_songs',
			'create_choir_songs',
		);
	}

	/**
	 * @return array<string, bool>
	 */
	public static function singer_capabilities(): array {
		return array(
			'read'           => true,
			self::CAP_LISTEN => true,
		);
	}

	/**
	 * @return array<string, bool>
	 */
	public static function voice_leader_capabilities(): array {
		$caps                     = self::singer_capabilities();
		$caps[ self::CAP_MANAGE ] = true;
		$caps['upload_files']     = true;
		foreach ( self::song_caps() as $cap ) {
			$caps[ $cap ] = true;
		}

		return $caps;
	}

	public static function ensure_roles(): void {
		// Drop early draft slug if present (never shipped).
		if ( get_role( 'choir_voice_recorder' ) instanceof WP_Role ) {
			remove_role( 'choir_voice_recorder' );
		}

		self::upsert_role( self::ROLE_SINGER, self::LABEL_SINGER, self::singer_capabilities() );
		self::upsert_role( self::ROLE_VOICE_LEADER, self::LABEL_VOICE_LEADER, self::voice_leader_capabilities() );

		$admin = get_role( 'administrator' );
		if ( $admin instanceof WP_Role ) {
			$admin->add_cap( self::CAP_LISTEN );
			$admin->add_cap( self::CAP_MANAGE );
			foreach ( self::song_caps() as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	/**
	 * @param array<string, bool> $caps
	 */
	private static function upsert_role( string $role_key, string $display_name, array $caps ): void {
		$role = get_role( $role_key );
		if ( null === $role ) {
			add_role( $role_key, $display_name, $caps );
			return;
		}

		foreach ( $caps as $cap => $grant ) {
			if ( $grant ) {
				$role->add_cap( (string) $cap );
			}
		}

		// Force English display name in the roles option (never localize these labels).
		$roles = get_option( 'wp_user_roles' );
		if ( is_array( $roles ) && isset( $roles[ $role_key ] ) ) {
			$roles[ $role_key ]['name'] = $display_name;
			$roles[ $role_key ]['capabilities'] = array_merge(
				isset( $roles[ $role_key ]['capabilities'] ) && is_array( $roles[ $role_key ]['capabilities'] )
					? $roles[ $role_key ]['capabilities']
					: array(),
				$caps
			);
			update_option( 'wp_user_roles', $roles );
			if ( isset( $GLOBALS['wp_roles'] ) && $GLOBALS['wp_roles'] instanceof WP_Roles ) {
				$GLOBALS['wp_roles']->roles[ $role_key ]['name']         = $display_name;
				$GLOBALS['wp_roles']->role_names[ $role_key ]            = $display_name;
				$GLOBALS['wp_roles']->roles[ $role_key ]['capabilities'] = $roles[ $role_key ]['capabilities'];
			}
		}
	}
}
