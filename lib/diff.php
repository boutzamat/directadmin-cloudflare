<?php

class DNSDiff
{

	/**
	 * Calculate diff between desired and actual records
	 */
	public static function calculate($desired_records, $actual_records)
	{
		$desired_map = [];
		$actual_map = [];

		// Build record keys
		foreach ($desired_records as $record) {
			$key = self::getRecordKey($record);
			$desired_map[$key] = $record;
		}

		foreach ($actual_records as $record) {
			$key = self::getRecordKey($record);
			$actual_map[$key] = $record;
		}

		$create = [];
		$delete = [];
		$update = [];

		// Detect creates + updates
		foreach ($desired_map as $key => $desired) {

			if (!isset($actual_map[$key])) {
				$create[] = $desired;
				continue;
			}

			$actual = $actual_map[$key];

			if (self::recordNeedsUpdate($desired, $actual)) {

				$update[] = [
					'id' => $actual['id'],
					'record' => $desired
				];
			}
		}

		// Detect deletes
		foreach ($actual_map as $key => $actual) {

			if (!isset($desired_map[$key]) && self::shouldDeleteRecord($actual)) {
				$delete[] = $actual;
			}
		}

		return [
			'create' => $create,
			'delete' => $delete,
			'update' => $update
		];
	}

	/**
	 * Generate a unique key for record comparison
	 */
	public static function getRecordKey($record)
	{
		return $record['type'] . ':' . $record['name'] . ':' . $record['content'];
	}

	/**
	 * Determine if an existing record needs updating
	 */
	private static function recordNeedsUpdate($desired, $actual)
	{

		if (isset($desired['ttl']) && $desired['ttl'] != $actual['ttl']) {
			return true;
		}

		if (isset($desired['proxied']) && isset($actual['proxied']) && $desired['proxied'] != $actual['proxied']) {
			return true;
		}

		if (isset($desired['priority']) && isset($actual['priority']) && $desired['priority'] != $actual['priority']) {
			return true;
		}

		return false;
	}

	/**
	 * Check if record should be deleted (safety check)
	 */
	private static function shouldDeleteRecord($record)
	{

		// Never delete system records
		if (in_array($record['type'], ['NS', 'SOA'])) {
			return false;
		}

		// Never delete Cloudflare system records
		if (strpos($record['name'], '_cf') === 0 || strpos($record['name'], '_acme') === 0) {
			return false;
		}

		return true;
	}

	/**
	 * Format diff operations for display
	 */
	public static function formatOperations($operations)
	{
		$output = [];

		foreach ($operations['create'] as $record) {
			$output[] = "CREATE {$record['type']} {$record['name']} {$record['content']}";
		}

		foreach ($operations['update'] as $record) {
			$r = $record['record'];
			$output[] = "UPDATE {$r['type']} {$r['name']} {$r['content']}";
		}

		foreach ($operations['delete'] as $record) {
			$output[] = "DELETE {$record['type']} {$record['name']} {$record['content']}";
		}

		return $output;
	}
}