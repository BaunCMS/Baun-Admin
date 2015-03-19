<?php namespace BaunPlugin\Admin;

use EasyCSRF\Interfaces\SessionProvider;

class CustomCSRFSessionProvider implements SessionProvider {

	protected $session;

	public function __construct($session)
	{
		$this->session = $session;
	}

	public function get($key)
	{
		return $this->session->get($key);
	}

	public function set($key, $value)
	{
		$this->session->set($key, $value);
	}

}