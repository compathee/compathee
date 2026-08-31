<?php
/**
 * WordPress page integration for the rehearsal library URL.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Pages {

	public const OPTION_PAGE_ID = 'choir_rehearsal_page_id';
	public const OPTION_VERSION = 'choir_rehearsal_installed_version';

	public static function register(): void {
		add_action( 'admin_post_choir_rehearsal_flush_rewrites', array( self::class, 'handle_flush_rewrites' ) );
	}

	public static function register_settings(): void {
		register_setting(
			'choir_rehearsal_settings',
			self::OPTION_PAGE_ID,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( self::class, 'sanitize_page_id' ),
				'default'           => 0,
			)
		);
	}

	public static function sanitize_page_id( $value ): int {
		return absint( $value );
	}

	public static function maybe_upgrade(): void {
		if ( ! function_exists( 'wp_insert_post' ) ) {
			return;
		}

		$stored = (string) get_option( self::OPTION_VERSION, '' );
		if ( version_compare( $stored, CHOIR_REHEARSAL_VERSION, '>=' ) ) {
			return;
		}

		$page_id = self::ensure_library_page();
		if ( $page_id > 0 ) {
			self::normalize_page_content( $page_id );
		}

		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}

		update_option( self::OPTION_VERSION, CHOIR_REHEARSAL_VERSION );
	}

	public static function ensure_library_page(): int {
		$page_id = self::get_page_id();
		if ( $page_id > 0 && 'trash' !== get_post_status( $page_id ) ) {
			self::ensure_page_has_shortcode( $page_id );
			return $page_id;
		}

		$existing = get_page_by_path( 'rehearsal' );
		if ( $existing instanceof WP_Post && 'trash' !== $existing->post_status ) {
			update_option( self::OPTION_PAGE_ID, (int) $existing->ID );
			self::ensure_page_has_shortcode( (int) $existing->ID );
			return (int) $existing->ID;
		}

		$new_id = wp_insert_post(
			array(
				'post_title'   => __( 'Rehearsal Library', 'choir-rehearsal' ),
				'post_name'    => 'rehearsal',
				'post_content' => '[choir_rehearsal]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => self::get_setup_user_id(),
			),
			true
		);

		if ( is_wp_error( $new_id ) || ! $new_id ) {
			return 0;
		}

		$page_id = (int) $new_id;
		update_option( self::OPTION_PAGE_ID, $page_id );
		return $page_id;
	}

	private static function get_setup_user_id(): int {
		$users = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);

		if ( ! empty( $users ) ) {
			$first = $users[0];
			if ( is_object( $first ) && isset( $first->ID ) ) {
				return (int) $first->ID;
			}
			if ( is_numeric( $first ) ) {
				return (int) $first;
			}
		}

		$current = get_current_user_id();
		return $current > 0 ? $current : 1;
	}

	private static function ensure_page_has_shortcode( int $page_id ): void {
		self::normalize_page_content( $page_id );
	}

	public static function page_has_shortcode( string $content ): bool {
		if ( has_shortcode( $content, 'choir_rehearsal' ) ) {
			return true;
		}

		return false !== stripos( $content, '[choir_rehearsal' );
	}

	public static function normalize_page_content( int $page_id ): void {
		$post = get_post( $page_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$content     = (string) $post->post_content;
		$shortcodes  = self::count_shortcodes( $content );
		$has_one     = 1 === $shortcodes && self::page_has_shortcode( $content );
		$only_markup = '' === trim( wp_strip_all_tags( $content ) ) && self::page_has_shortcode( $content );

		if ( $has_one || ( $only_markup && $shortcodes >= 1 ) ) {
			return;
		}

		wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => "[choir_rehearsal]\n",
			)
		);
	}

	private static function count_shortcodes( string $content ): int {
		if ( ! preg_match_all( '/\[choir_rehearsal(?:\s|\]|\/)/i', $content, $matches ) ) {
			return 0;
		}

		return count( $matches[0] );
	}

	public static function get_page_id(): int {
		return (int) get_option( self::OPTION_PAGE_ID, 0 );
	}

	public static function is_library_page(): bool {
		$page_id = self::get_page_id();
		return $page_id > 0 && is_page( $page_id );
	}

	public static function get_library_url(): string {
		$page_id = self::get_page_id();
		if ( $page_id > 0 ) {
			$url = get_permalink( $page_id );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		$page = get_page_by_path( 'rehearsal' );
		if ( $page instanceof WP_Post ) {
			$url = get_permalink( $page );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		return home_url( '/rehearsal/' );
	}

	public static function get_flush_rewrites_url(): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=choir_rehearsal_flush_rewrites' ),
			'choir_rehearsal_flush_rewrites'
		);
	}

	public static function handle_flush_rewrites(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage options.', 'choir-rehearsal' ) );
		}

		check_admin_referer( 'choir_rehearsal_flush_rewrites' );

		self::ensure_library_page();
		$page_id = self::get_page_id();
		if ( $page_id > 0 ) {
			self::normalize_page_content( $page_id );
		}
		flush_rewrite_rules( false );

		wp_safe_redirect( admin_url( 'edit.php?post_type=choir_song&page=choir-rehearsal-settings&choir_rewrites_flushed=1' ) );
		exit;
	}
}
