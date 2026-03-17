<?php

class CloudflareClient
{
	private $api_token;
	private $base_url = 'https://api.cloudflare.com/client/v4';

	public function __construct($api_token)
	{
		$this->api_token = $api_token;
	}

	/**
	 * Make a request to Cloudflare API
	 */
	public function cfRequest($method, $endpoint, $data = null)
	{
		$url = $this->base_url . $endpoint;

		$headers = [
			'Authorization: Bearer ' . $this->api_token,
			'Content-Type: application/json',
		];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

		if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if (!$response) {
			throw new Exception('Failed to connect to Cloudflare API');
		}

		$result = json_decode($response, true);

		if ($http_code >= 400 || !$result['success']) {
			$error_msg = 'Unknown error';
			if (isset($result['errors']) && is_array($result['errors'])) {
				$error_messages = [];
				foreach ($result['errors'] as $error) {
					if (isset($error['message'])) {
						$error_messages[] = $error['message'] . (isset($error['code']) ? " (Code: {$error['code']})" : "");
					}
				}
				if (!empty($error_messages)) {
					$error_msg = implode(', ', $error_messages);
				}
			}

			throw new Exception("Cloudflare API error: $error_msg (HTTP $http_code)");
		}

		return $result;
	}

	/**
	 * List zones
	 */
	public function listZones()
	{
		$response = $this->cfRequest('GET', '/zones');
		return $response['result'];
	}

	/**
	 * Find zone by domain name
	 */
	public function findZone($domain)
	{
		$zones = $this->listZones();
		foreach ($zones as $zone) {
			if ($zone['name'] === $domain) {
				return $zone['id'];
			}
		}
		return null;
	}

	/**
	 * Get account ID
	 */
	public function getAccountId()
	{
		$response = $this->cfRequest('GET', '/accounts');
		if (empty($response['result'])) {
			throw new Exception('No accounts found');
		}
		return $response['result'][0]['id'];
	}

	/**
	 * Create a new zone
	 */
	public function createZone($domain)
	{
		// 1. Check if zone already exists
		$existing = $this->getZoneByName($domain);

		if ($existing) {
			// Optional: log instead of silent return
			echo "Zone already exists for $domain (ID: {$existing['id']})\n";

			return $existing['id'];
		}

		// 2. Otherwise create it
		$account_id = $this->getAccountId();

		$data = [
			'name' => $domain,
			'account' => [
				'id' => $account_id
			]
		];

		$response = $this->cfRequest('POST', '/zones', $data);

		return $response['result']['id'];
	}

	/**
	 * List DNS records for a zone
	 */
	public function listDnsRecords($zone_id)
	{
		$response = $this->cfRequest('GET', "/zones/$zone_id/dns_records");
		return $response['result'];
	}

	/**
	 * Create a DNS record
	 */
	public function createRecord($zone_id, $record)
	{
		// Debug: Log the record being sent
		error_log("Creating record: " . json_encode($record));

		$response = $this->cfRequest('POST', "/zones/$zone_id/dns_records", $record);
		return $response['result'];
	}

	/**
	 * Update a DNS record
	 */
	public function updateRecord($zone_id, $record_id, $record)
	{
		// Debug: Log the record being sent
		error_log("Updating record {$record_id}: " . json_encode($record));

		$response = $this->cfRequest('PATCH', "/zones/$zone_id/dns_records/$record_id", $record);
		return $response['result'];
	}

	/**
	 * Delete a DNS record
	 */
	public function deleteRecord($zone_id, $record_id)
	{
		$response = $this->cfRequest('DELETE', "/zones/$zone_id/dns_records/$record_id");
		return $response['result'];
	}

	/**
	 * Get zone by domain name
	 */
	public function getZoneByName($domain)
	{
		$response = $this->cfRequest('GET', "/zones?name=$domain");
		return $response['result'][0] ?? null;
	}
}