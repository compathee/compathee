<?php
/**
 * Access control for rehearsal pages.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Access {

	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'maybe_restrict_frontend' ) );
		add_filter( 'login_redirect', array( self::class, 'login_redirect' ), 10, 3 );
	}

	public static function requires_login(): bool {
		return (bool) get_option( 'choir_rehearsal_require_login', true );
	}

	public static function is_rehearsal_request(): bool {
		if ( is_singular( Choir_Rehearsal_Post_Types::SONG ) ) {
			return true;
		}

		if ( is_post_type_archive( Choir_Rehearsal_Post_Types::SONG ) ) {
			return true;
		}

		global $post;
		if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'choir_rehearsal' ) ) {
			return true;
		}

		return false;
	}

	public static function maybe_restrict_frontend(): void {
		if ( is_admin() || ! self::requires_login() || ! self::is_rehearsal_request() ) {
			return;
		}

		if ( is_user_logged_in() ) {
			return;
		}

		auth_redirect();
	}

	public static function login_redirect( string $redirect_to, string $requested_redirect_to, $user ): string {
		if ( is_string( $requested_redirect_to ) && '' !== $requested_redirect_to ) {
			return $requested_redirect_to;
		}

		if ( $user instanceof WP_User && $user->exists() ) {
			return get_post_type_archive_link( Choir_Rehearsal_Post_Types::SONG ) ?: home_url( '/rehearsal/' );
		}

		return $redirect_to;
	}
}
