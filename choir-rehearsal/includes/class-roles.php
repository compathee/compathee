<?php
/**
 * WordPress roles and capabilities for Choir Rehearsal.
 *
 * Role display names "Singer" and "Voice Leader" stay in English (not translated).
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

	public const CAP_LISTEN = 'choir_rehearsal_listen';
	public const CAP_MANAGE = 'choir_rehearsal_manage_songs';

	public static function register(): void {
		add_action( 'init', array( self::class, 'maybe_install' ), 4 );
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
