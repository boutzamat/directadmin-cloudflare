<?php

class MetadataManager
{
	private $storage_dir;

	public function __construct($storage_dir = null)
	{
		$this->storage_dir = $storage_dir ?? __DIR__ . '/../storage/metadata';
	}

	/**
	 * Get metadata for a domain
	 */
	public function getMetaData($domain)
	{
		$file = "{$this->storage_dir}/$domain.json";

		if (!file_exists($file)) {
			return ['records' => []];
		}

		$content = file_get_contents($file);
		return json_decode($content, true) ?? ['records' => []];
	}

	/**
	 * Save metadata for a domain
	 */
	public function saveMetaData($domain, $metadata)
	{
		if (!is_dir($this->storage_dir)) {
			mkdir($this->storage_dir, 0755, true);
		}

		$file = "{$this->storage_dir}/$domain.json";
		$json = json_encode($metadata, JSON_PRETTY_PRINT);

		return file_put_contents($file, $json) !== false;
	}

	/**
	 * Get proxy setting for a record
	 */
	public function getProxyRecord($domain, $record)
	{
		$metadata = $this->getMetaData($domain);
		$key = DNSDiff::getRecordKey($record);

		return $metadata['records'][$key]['proxied'] ?? false;
	}

	/**
	 * Set proxy setting for a record
	 */
	public function setProxyRecord($domain, $record, $proxied)
	{
		$metadata = $this->getMetaData($domain);
		$key = DNSDiff::getRecordKey($record);

		if (!isset($metadata['records'])) {
			$metadata['records'] = [];
		}

		if (!isset($metadata['records'][$key])) {
			$metadata['records'][$key] = [];
		}

		$metadata['records'][$key]['proxied'] = $proxied;

		return $this->saveMetaData($domain, $metadata);
	}

	/**
	 * Apply metadata to records
	 */
	public function applyMetaData($domain, $records)
	{
		$metadata = $this->getMetaData($domain);

		foreach ($records as &$record) {
			$key = DNSDiff::getRecordKey($record);
			$record['proxied'] = $metadata['records'][$key]['proxied'] ?? false;
		}

		return $records;
	}

	/**
	 * Clean up orphaned metadata
	 */
	public function cleanupMetaData($domain, $active_records)
	{
		$metadata = $this->getMetaData($domain);
		$active_keys = [];

		foreach ($active_records as $record) {
			$active_keys[] = DNSDiff::getRecordKey($record);
		}

		// Remove metadata for records that no longer exist
		foreach (array_keys($metadata['records']) as $key) {
			if (!in_array($key, $active_keys)) {
				unset($metadata['records'][$key]);
			}
		}

		return $this->saveMetaData($domain, $metadata);
	}
}