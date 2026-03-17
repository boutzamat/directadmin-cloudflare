<?php

class Admin
{
	private $configPath;
	private $config = [];
	private $params = null;

	public function __construct($configPath = null)
	{
		$this->configPath = $configPath ?? dirname(__DIR__) . '/config.json';
		$this->config = $this->loadConfig();
	}

	// --------------------------------------------------
	// Params
	// --------------------------------------------------

	public function params()
	{
		if ($this->params !== null) {
			return $this->params;
		}

		$this->params = [];

		$query = getenv('QUERY_STRING');

		if ($query) {
			parse_str(html_entity_decode($query), $this->params);
		}

		return $this->params;
	}

	public function getParam($key, $default = null)
	{
		$params = $this->params();
		return $params[$key] ?? $default;
	}

	public function hasParam($key)
	{
		$params = $this->params();
		return isset($params[$key]);
	}

	// --------------------------------------------------
	// Config
	// --------------------------------------------------

	public function loadConfig()
	{
		if (!file_exists($this->configPath)) {
			return [];
		}

		$content = file_get_contents($this->configPath);

		if (!$content) {
			return [];
		}

		return json_decode($content, true) ?? [];
	}

	public function saveConfig(array $data)
	{
		$this->config = $data;

		return file_put_contents(
			$this->configPath,
			json_encode($data, JSON_PRETTY_PRINT)
		);
	}

	public function config($key = null, $default = null)
	{
		if ($key === null) {
			return $this->config;
		}

		return $this->config[$key] ?? $default;
	}

	// --------------------------------------------------
	// Messages (UI helper)
	// --------------------------------------------------

	public function message($text, $type = 'success')
	{
		$class = match ($type) {
			'error' => 'message-error',
			'warning' => 'message-warning',
			default => 'message-success'
		};

		return "<div class=\"message {$class}\">{$text}</div>";
	}

	// --------------------------------------------------
	// Utils
	// --------------------------------------------------

	public function isChecked($key, $default = false)
	{
		$value = $this->config($key, $default);
		return $value ? 'checked' : '';
	}

	public function value($key, $default = '')
	{
		return htmlspecialchars($this->config($key, $default) ?? '', ENT_QUOTES, 'UTF-8');
	}
}