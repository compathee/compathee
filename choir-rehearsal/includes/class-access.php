<?php
/**
 * Access control for rehearsal pages.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Access {

	private static string $login_error = '';

	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'maybe_handle_logout' ), 5 );
		add_action( 'template_redirect', array( self::class, 'maybe_handle_login' ), 6 );
		add_filter( 'login_redirect', array( self::class, 'login_redirect' ), 10, 3 );
	}

	public static function requires_login(): bool {
		return (bool) get_option( 'choir_rehearsal_require_login', true );
	}

	public static function is_rehearsal_request(): bool {
		if ( is_singular( Choir_Rehearsal_Post_Types::SONG ) ) {
			return true;
		}

		if ( Choir_Rehearsal_Pages::is_library_page() ) {
			return true;
		}

		global $post;
		if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'choir_rehearsal' ) ) {
			return true;
		}

		return false;
	}

	public static function should_show_login(): bool {
		return self::requires_login() && self::is_rehearsal_request() && ! is_user_logged_in();
	}

	public static function can_view(): bool {
		if ( ! self::requires_login() ) {
			return true;
		}

		return is_user_logged_in();
	}

	public static function can_manage(): bool {
		return current_user_can( 'edit_posts' );
	}

	public static function get_login_error(): string {
		return self::$login_error;
	}

	public static function maybe_handle_login(): void {
		if ( is_admin() || ! self::requires_login() || ! self::is_rehearsal_request() || is_user_logged_in() ) {
			return;
		}

		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['choir_rehearsal_login'] ) ) {
			return;
		}

		if ( ! isset( $_POST['choir_rehearsal_login_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['choir_rehearsal_login_nonce'] ) ), 'choir_rehearsal_login' ) ) {
			self::$login_error = __( 'Security check failed. Please try again.', 'choir-rehearsal' );
			return;
		}

		$username = isset( $_POST['log'] ) ? sanitize_user( wp_unslash( (string) $_POST['log'] ) ) : '';
		$password = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '';
		$remember = ! empty( $_POST['rememberme'] );

		if ( '' === $username || '' === $password ) {
			self::$login_error = __( 'Please enter your username and password.', 'choir-rehearsal' );
			return;
		}

		$user = wp_signon(
			array(
				'user_login'    => $username,
				'user_password' => $password,
				'remember'      => $remember,
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			self::$login_error = wp_strip_all_tags( $user->get_error_message() );
			return;
		}

		$redirect = self::get_requested_redirect_url();
		wp_safe_redirect( $redirect );
		exit;
	}

	public static function maybe_handle_logout(): void {
		if ( ! is_user_logged_in() || ! isset( $_GET['choir_rehearsal_logout'] ) ) {
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'choir_rehearsal_logout' ) ) {
			return;
		}

		wp_logout();

		$redirect = Choir_Rehearsal_Pages::get_library_url();
		wp_safe_redirect( $redirect );
		exit;
	}

	public static function get_logout_url(): string {
		$url = add_query_arg(
			array(
				'choir_rehearsal_logout' => '1',
				'_wpnonce'               => wp_create_nonce( 'choir_rehearsal_logout' ),
			),
			self::get_current_rehearsal_url()
		);

		return is_string( $url ) ? $url : home_url( '/rehearsal/' );
	}

	public static function get_requested_redirect_url(): string {
		if ( isset( $_POST['redirect_to'] ) ) {
			$redirect = esc_url_raw( wp_unslash( (string) $_POST['redirect_to'] ) );
			if ( '' !== $redirect ) {
				return $redirect;
			}
		}

		if ( is_singular( Choir_Rehearsal_Post_Types::SONG ) ) {
			$permalink = get_permalink();
			if ( is_string( $permalink ) && '' !== $permalink ) {
				return $permalink;
			}
		}

		$archive = Choir_Rehearsal_Pages::get_library_url();
		return $archive;
	}

	public static function get_current_rehearsal_url(): string {
		if ( is_singular() ) {
			$permalink = get_permalink();
			if ( is_string( $permalink ) && '' !== $permalink ) {
				return $permalink;
			}
		}

		if ( Choir_Rehearsal_Pages::is_library_page() ) {
			return Choir_Rehearsal_Pages::get_library_url();
		}

		global $wp;
		$current = home_url( add_query_arg( array(), $wp->request ?? '' ) );
		return is_string( $current ) ? $current : home_url( '/rehearsal/' );
	}

	public static function login_redirect( string $redirect_to, string $requested_redirect_to, $user ): string {
		if ( is_string( $requested_redirect_to ) && '' !== $requested_redirect_to ) {
			return $requested_redirect_to;
		}

		if ( $user instanceof WP_User && $user->exists() ) {
			return Choir_Rehearsal_Pages::get_library_url();
		}

		return $redirect_to;
	}
}
