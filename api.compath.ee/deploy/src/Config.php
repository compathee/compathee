<?php

declare(strict_types=1);

namespace Compath\Hub;

final class Config {

	/** @var array<string, mixed> */
	private array $data;

	/**
	 * @param array<string, mixed> $data
	 */
	private function __construct( array $data ) {
		$this->data = $data;
	}

	public static function load( string $path ): self {
		if ( ! is_file( $path ) ) {
			throw new \RuntimeException( 'Config file not found: ' . $path );
		}

		/** @var array<string, mixed> $data */
		$data = require $path;

		return new self( $data );
	}

	public function getString( string $key, string $default = '' ): string {
		$value = $this->data[ $key ] ?? $default;
		return is_string( $value ) ? $value : $default;
	}

	public function getFloat( string $key, float $default = 0.0 ): float {
		$value = $this->data[ $key ] ?? $default;
		return is_numeric( $value ) ? (float) $value : $default;
	}

	/** @return array<string, mixed> */
	public function getArray( string $key ): array {
		$value = $this->data[ $key ] ?? array();
		return is_array( $value ) ? $value : array();
	}

	public function accountingProvider(): string {
		return $this->getString( 'accounting_provider', 'erply' );
	}
}
