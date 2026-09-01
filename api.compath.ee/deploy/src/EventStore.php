<?php

declare(strict_types=1);

namespace Compath\Hub;

use PDO;

final class EventStore {

	private PDO $pdo;

	public function __construct( string $sqlitePath ) {
		$dir = dirname( $sqlitePath );
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}

		$this->pdo = new PDO( 'sqlite:' . $sqlitePath );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$this->pdo->exec(
			'CREATE TABLE IF NOT EXISTS processed_events (
				event_id TEXT PRIMARY KEY,
				event_type TEXT NOT NULL,
				created_at TEXT NOT NULL,
				payload TEXT
			)'
		);
	}

	public function has( string $eventId ): bool {
		$stmt = $this->pdo->prepare( 'SELECT 1 FROM processed_events WHERE event_id = :id LIMIT 1' );
		$stmt->execute( array( 'id' => $eventId ) );
		return (bool) $stmt->fetchColumn();
	}

	public function mark( string $eventId, string $eventType, string $payload ): void {
		$stmt = $this->pdo->prepare(
			'INSERT OR IGNORE INTO processed_events (event_id, event_type, created_at, payload)
			 VALUES (:event_id, :event_type, :created_at, :payload)'
		);
		$stmt->execute(
			array(
				'event_id'   => $eventId,
				'event_type' => $eventType,
				'created_at' => gmdate( 'c' ),
				'payload'    => $payload,
			)
		);
	}
}
