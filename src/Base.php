<?php namespace BaunPlugin\Admin;

class Base {

	protected $config;
	protected $session;
	protected $events;
	protected $router;
	protected $theme;
	protected $contentParser;

	public function __construct($config, $session, $eventProvider, $routerProvider, $themeProvider, $contentParser)
	{
		$this->config = $config;
		$this->session = $session;
		$this->events = $eventProvider;
		$this->router = $routerProvider;
		$this->theme = $themeProvider;
		$this->contentParser = $contentParser;
	}

	public function init()
	{

	}

	protected function getGlobalTemplateData()
	{
		return [
			'base_url' => $this->config->get('app.base_url'),
			'logged_in' => $this->session->get('logged_in'),
		];
	}

}