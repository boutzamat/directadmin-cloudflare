#!/usr/bin/env php
<?php

require_once __DIR__ . '/lib/cloudflare.php';
require_once __DIR__ . '/lib/dns.php';
require_once __DIR__ . '/lib/diff.php';
require_once __DIR__ . '/lib/metadata.php';
require_once __DIR__ . '/lib/lock.php';

class CloudflareSync
{
	private $config;
	private $cfClient;
	private $metadataManager;
	private $lockManager;
	private $zonesFile;

	public $path = __DIR__;

	public function __construct()
	{
		$this->config = $this->loadConfig();
		$this->cfClient = new CloudflareClient($this->config['api_token']);
		$this->metadataManager = new MetadataManager();
		$this->lockManager = new LockManager();
		$this->zonesFile = __DIR__ . '/storage/zones.json';
	}

	/**
	 * Load configuration
	 */
	private function loadConfig()
	{
		$config_file = __DIR__ . '/config.json';
		if (!file_exists($config_file)) {
			die("Configuration file not found. Please configure the plugin.\n");
		}

		$config = json_decode(file_get_contents($config_file), true);

		if (empty($config['api_token'])) {
			die("Cloudflare API token not configured.\n");
		}

		return $config;
	}

	/**
	 * Load zones mapping
	 */
	private function loadZones()
	{
		if (!file_exists($this->zonesFile)) {
			return [];
		}

		return json_decode(file_get_contents($this->zonesFile), true) ?? [];
	}

	/**
	 * Save zones mapping
	 */
	private function saveZones($zones)
	{
		return file_put_contents($this->zonesFile, json_encode($zones, JSON_PRETTY_PRINT));
	}

	/**
	 * Sync a domain
	 */
	public function syncDomain($domain, $dry_run = false)
	{

		if ($this->config['debug']) {
			echo "Starting sync for domain: $domain\n";
		}


		// Acquire lock
		if (!$this->lockManager->acquireLock($domain)) {
			die("Domain $domain is currently locked by another sync process.\n");
		}

		$serials_file = __DIR__ . '/storage/serials.json';

		$serials = [];
		if (file_exists($serials_file)) {
			$serials = json_decode(file_get_contents($serials_file), true) ?? [];
		}

		$current_serial = getenv('SERIAL');

		if ($current_serial && isset($serials[$domain]) && $serials[$domain] == $current_serial) {
			if ($this->config['debug']) {
				echo "Serial unchanged for $domain ($current_serial), skipping sync\n";
			}

			$this->lockManager->releaseLock($domain);
			return;
		}

		try {
			// Get zone ID
			$zones = $this->loadZones();
			$zone_id = $zones[$domain] ?? null;

			if (!$zone_id) {
				// First try to find existing zone
				echo "Looking for existing zone for $domain...\n";
				$zone_id = $this->cfClient->findZone($domain);

				if ($zone_id) {
					echo "Found existing zone for $domain\n";
					$zones[$domain] = $zone_id;
					$this->saveZones($zones);
				} elseif ($this->config['auto_create_zones']) {
					echo "Creating new Cloudflare zone for $domain\n";
					$zone_id = $this->cfClient->createZone($domain);
					$zones[$domain] = $zone_id;
					$this->saveZones($zones);
				} else {
					throw new Exception("No Cloudflare zone found for $domain and auto_create_zones is disabled");
				}
			}

			// Get records from both sides
			$da_records = DirectAdminDNS::getRecords($domain);

			// Later, we might want to apply metadata transformations here before diffing
			//$da_records = $this->metadataManager->applyMetaData($domain, $da_records);


			$cf_records = $this->cfClient->listDnsRecords($zone_id);

			// Filter supported records
			$da_records = array_filter($da_records, fn($record) => in_array($record['type'], DirectAdminDNS::supportedTypes()));

			// Debug output
			if ($this->config['debug']) {
				echo "DirectAdmin records (" . count($da_records) . "):\n";
				foreach ($da_records as $record) {
					echo "  {$record['type']} {$record['name']} -> {$record['content']}\n";
				}

				echo "\nCloudflare records (" . count($cf_records) . "):\n";
				foreach ($cf_records as $record) {
					echo "  {$record['type']} {$record['name']} -> {$record['content']}\n";
				}
				echo "\n";
			}

			// Calculate diff
			$diff = DNSDiff::calculate($da_records, $cf_records);

			if (empty($diff['create']) && empty($diff['delete']) && empty($diff['update'])) {
				echo "No changes needed for $domain\n";

				if ($current_serial) {
					$serials[$domain] = $current_serial;
					file_put_contents($serials_file, json_encode($serials, JSON_PRETTY_PRINT));
				}

				return;
			}

			// Display operations
			$operations = DNSDiff::formatOperations($diff);
			foreach ($operations as $op) {
				echo "$op\n";
			}

			if ($dry_run) {
				echo "Dry run mode - no changes made\n";
				return;
			}

			// Execute operations
			foreach ($diff['delete'] as $record) {
				$this->cfClient->deleteRecord($zone_id, $record['id']);
			}

			foreach ($diff['update'] as $item) {
				$record = $item['record'];

				$cf_record = [
					'type' => $record['type'],
					'name' => $record['name'],
					'content' => $record['content'],
					'ttl' => $record['ttl'],
				];

				// Add priority for MX records
				if ($record['type'] == 'MX' && isset($record['priority'])) {
					$cf_record['priority'] = $record['priority'];
				}

				if (DirectAdminDNS::canProxy($record['type']) && isset($record['proxied'])) {
					$cf_record['proxied'] = $record['proxied'];
				}

				$this->cfClient->updateRecord($zone_id, $item['id'], $cf_record);
			}

			foreach ($diff['create'] as $record) {
				$cf_record = [
					'type' => $record['type'],
					'name' => $record['name'],
					'content' => $record['content'],
					'ttl' => $record['ttl'],
				];

				// Add priority for MX records
				if ($record['type'] == 'MX' && isset($record['priority'])) {
					$cf_record['priority'] = $record['priority'];
				}

				if (DirectAdminDNS::canProxy($record['type']) && isset($record['proxied'])) {
					$cf_record['proxied'] = $record['proxied'];
				}

				$this->cfClient->createRecord($zone_id, $cf_record);
			}

			echo "Sync completed for $domain\n";

			if ($current_serial) {
				$serials[$domain] = $current_serial;
				file_put_contents($serials_file, json_encode($serials, JSON_PRETTY_PRINT));
			}

		} catch (Exception $e) {
			echo "Error syncing $domain: " . $e->getMessage() . "\n";
		} finally {
			// Always release lock
			$this->lockManager->releaseLock($domain);
		}
	}

	/**
	 * Sync all domains
	 */
	public function syncAllDomains($dry_run = false)
	{
		$zones = $this->loadZones();

		foreach (array_keys($zones) as $domain) {
			$this->syncDomain($domain, $dry_run);
		}
	}

	/**
	 * Create zone for domain
	 */
	public function createZone($domain)
	{
		$zones = $this->loadZones();

		// If zone already known locally, do nothing
		if (isset($zones[$domain])) {
			return;
		}

		// Check if zone already exists in Cloudflare
		$existing = $this->cfClient->findZone($domain);

		if ($existing) {
			$zones[$domain] = $existing;
			$this->saveZones($zones);
			echo "Using existing Cloudflare zone for $domain: $existing\n";
			return;
		}

		try {
			$zone_id = $this->cfClient->createZone($domain);

			$zones[$domain] = $zone_id;
			$this->saveZones($zones);

			echo "Created zone for $domain: $zone_id\n";
		} catch (Exception $e) {
			echo "Error creating zone for $domain: " . $e->getMessage() . "\n";
		}
	}

	/**
	 * Delete zone for domain
	 */
	public function deleteZone($domain)
	{
		$zones = $this->loadZones();

		if (isset($zones[$domain])) {
			unset($zones[$domain]);
			$this->saveZones($zones);
			echo "Removed zone mapping for $domain\n";
		}
	}
}

