<?php
/**
 * Rolling 24-hour event log for Demo sandbox.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Demo_Log {

	public const OPTION = 'choir_rehearsal_demo_event_log';

	public const TTL = 86400;

	/**
	 * @param array<string, mixed> $meta
	 */
	public static function add( string $code, string $message, array $meta = array() ): void {
		$entries   = self::entries();
		$entries[] = array(
			'ts'      => time(),
			'code'    => sanitize_key( $code ),
			'message' => $message,
			'meta'    => $meta,
		);
		$entries = self::prune( $entries );
		update_option( self::OPTION, $entries, false );
	}

	/**
	 * @return list<array{ts:int,code:string,message:string,meta:array<string,mixed>}>
	 */
	public static function entries(): array {
		$raw = get_option( self::OPTION, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		return self::prune( $raw );
	}

	/**
	 * @param list<array<string, mixed>> $entries
	 * @return list<array{ts:int,code:string,message:string,meta:array<string,mixed>}>
	 */
	private static function prune( array $entries ): array {
		$cutoff = time() - self::TTL;
		$out    = array();

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$ts = isset( $entry['ts'] ) ? (int) $entry['ts'] : 0;
			if ( $ts < $cutoff ) {
				continue;
			}
			$out[] = array(
				'ts'      => $ts,
				'code'    => isset( $entry['code'] ) ? (string) $entry['code'] : '',
				'message' => isset( $entry['message'] ) ? (string) $entry['message'] : '',
				'meta'    => isset( $entry['meta'] ) && is_array( $entry['meta'] ) ? $entry['meta'] : array(),
			);
		}

		return $out;
	}
}
