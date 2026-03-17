<?php

class LockManager
{
	private $lock_dir;
	private $max_age = 120; // seconds

	public function __construct($lock_dir = null)
	{
		$this->lock_dir = $lock_dir ?? __DIR__ . '/../storage/locks';
	}

	/**
	 * Acquire a lock for domain
	 */
	public function acquireLock($domain)
	{
		if (!is_dir($this->lock_dir)) {
			mkdir($this->lock_dir, 0755, true);
		}

		$lock_file = $this->lock_dir . '/' . $domain . '.lock';

		// Check if lock exists and is still valid
		if (file_exists($lock_file)) {
			$lock_time = filemtime($lock_file);
			$age = time() - $lock_time;

			if ($age < $this->max_age) {
				return false; // Lock still active
			}

			// Remove stale lock
			unlink($lock_file);
		}

		// Create new lock
		return touch($lock_file);
	}

	/**
	 * Release a lock for domain
	 */
	public function releaseLock($domain)
	{
		$lock_file = "{$this->lock_dir}/$domain.lock";

		if (file_exists($lock_file)) {
			return unlink($lock_file);
		}

		return true;
	}

	/**
	 * Check if domain is locked
	 */
	public function isLocked($domain)
	{
		$lock_file = "{$this->lock_dir}/$domain.lock";

		if (!file_exists($lock_file)) {
			return false;
		}

		$lock_time = filemtime($lock_file);
		$age = time() - $lock_time;

		return $age < $this->max_age;
	}

	/**
	 * Clean up stale locks
	 */
	public function cleanupStaleLocks()
	{
		if (!is_dir($this->lock_dir)) {
			return;
		}

		$files = glob("{$this->lock_dir}/*.lock");
		$cleaned = 0;

		foreach ($files as $lock_file) {
			$lock_time = filemtime($lock_file);
			$age = time() - $lock_time;

			if ($age >= $this->max_age) {
				unlink($lock_file);
				$cleaned++;
			}
		}

		return $cleaned;
	}
}