<?php

class DirectAdminDNS
{

	/**
	 * Get DNS records from DirectAdmin
	 */
	public static function getRecords($domain)
	{
		$records = [];
		$zone_file = "/etc/bind/$domain.db";

		if (!file_exists($zone_file)) {
			return $records;
		}

		$content = file_get_contents($zone_file);
		$lines = explode("\n", $content);

		foreach ($lines as $line) {
			$line = trim($line);
			if (empty($line) || substr($line, 0, 1) == ';') {
				continue;
			}

			$record = self::parseZoneRecord($line, $domain);
			if ($record) {
				$records[] = $record;
			}
		}

		return $records;
	}

	/**
	 * Parse a zone file record
	 */
	private static function parseZoneRecord($line, $domain)
	{
		$parts = preg_split('/\s+/', $line);

		if (count($parts) < 4) {
			return null;
		}

		$name = $parts[0];
		$ttl = is_numeric($parts[1]) ? $parts[1] : 14400;
		$class = 'IN';
		$type = '';
		$content = '';

		// Handle different record formats
		if (is_numeric($parts[1])) {
			$type = $parts[3];
			$content = implode(' ', array_slice($parts, 4));
		} else {
			$type = $parts[2];
			$content = implode(' ', array_slice($parts, 3));
		}

		// Skip unsupported records
		if (in_array($type, ['NS', 'SOA'])) {
			return null;
		}

		// Handle MX record priority and content
		if ($type == 'MX') {
			$mx_parts = explode(' ', trim($content), 2);
			if (count($mx_parts) >= 2) {
				$priority = (int) $mx_parts[0];
				$mx_target = trim($mx_parts[1]);

				// Convert relative hostname to absolute
				if ($mx_target && !str_contains($mx_target, '.')) {
					$mx_target = $mx_target . '.' . $domain;
				}

				$content = $mx_target;
			}
		}

		// Normalize name to Cloudflare format for comparison
		if ($name == '@') {
			$name = $domain;
		} elseif ($name == $domain . '.') {
			$name = $domain;
		} elseif (str_ends_with($name, '.' . $domain . '.')) {
			// Remove trailing domain part (e.g. "ftp.example.com." -> "ftp.example.com")
			$name = rtrim($name, '.');
		} elseif ($name != $domain && !str_contains($name, '.')) {
			// Convert relative name to absolute (e.g. "ftp" -> "ftp.example.com")
			$name = $name . '.' . $domain;
		}

		$record = [
			'type' => $type,
			'name' => $name,
			'content' => trim($content, '"'),
			'ttl' => (int) $ttl,
			'proxied' => false // Will be set from metadata
		];

		// Add priority field for MX records
		if ($type == 'MX' && isset($priority)) {
			$record['priority'] = $priority;
		}

		return $record;
	}

	/**
	 * Supported record types for sync
	 */
	public static function supportedTypes()
	{
		return ['A', 'AAAA', 'CNAME', 'TXT', 'MX', 'SRV', 'CAA'];
	}

	/**
	 * Check if record type can be proxied
	 */
	public static function canProxy($type)
	{
		return in_array($type, ['A', 'AAAA', 'CNAME']);
	}
}