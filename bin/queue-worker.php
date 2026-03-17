<?php

$base = realpath(__DIR__ . '/..');
$queueFile = "$base/storage/queue";

if (!file_exists($queueFile)) {
	exit;
}

$fp = fopen($queueFile, 'c+');
if (!$fp) {
	exit;
}

/*
 |---------------------------------------------------------
 | Lock queue so only one worker runs at a time
 |---------------------------------------------------------
 */
if (!flock($fp, LOCK_EX | LOCK_NB)) {
	// another worker already running
	exit;
}

$domains = file($queueFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

if (!$domains) {
	flock($fp, LOCK_UN);
	fclose($fp);
	exit;
}

/*
 |---------------------------------------------------------
 | Clear queue immediately
 |---------------------------------------------------------
 */
ftruncate($fp, 0);
fflush($fp);

$domains = array_unique($domains);

foreach ($domains as $domain) {

	echo "Syncing $domain\n";

	$cmd = "/usr/bin/php $base/bin/cloudflare_sync-cli.php sync " . escapeshellarg($domain);

	exec($cmd);
}

flock($fp, LOCK_UN);
fclose($fp);