#!/usr/bin/env php
<?php

require_once __DIR__ . '/../cloudflare_sync.php';

if (php_sapi_name() === 'cli') {
	if ($argc < 2) {
		echo "Usage: php cloudflare_sync-cli.php [command] [options]\n";
		echo "Commands:\n";
		echo "  sync <domain> [--dry-run]     Sync specific domain\n";
		echo "  sync-all [--dry-run]          Sync all domains\n";
		echo "  create-zone <domain>          Create Cloudflare zone\n";
		echo "  delete-zone <domain>          Remove zone mapping\n";
		exit(1);
	}

	$sync = new CloudflareSync();
	$command = $argv[1];

	switch ($command) {
		case 'sync':
			if (!isset($argv[2])) {
				die("Domain name required\n");
			}
			$domain = $argv[2];
			$dry_run = in_array('--dry-run', $argv);
			$sync->syncDomain($domain, $dry_run);
			break;

		case 'sync-all':
			$dry_run = in_array('--dry-run', $argv);
			$sync->syncAllDomains($dry_run);
			break;

		case 'create-zone':
			if (!isset($argv[2])) {
				die("Domain name required\n");
			}
			$sync->createZone($argv[2]);
			break;

		case 'delete-zone':
			if (!isset($argv[2])) {
				die("Domain name required\n");
			}
			$sync->deleteZone($argv[2]);
			break;

		default:
			echo "Unknown command: $command\n";
			exit(1);
	}
}
