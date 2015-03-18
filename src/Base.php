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
			'blog_path' => $this->config->get('baun.blog_path'),
			'current_uri' => $this->router->currentUri(),
		];
	}

	protected function endsWith($haystack, $needle)
	{
		$length = strlen($needle);
		if ($length == 0) {
			return true;
		}
		return (substr($haystack, -$length) === $needle);
	}

	protected function getFileFromQuerySting()
	{
		$file = isset($_GET['file']) ? urldecode($_GET['file']) : null;
		$file = strtolower(preg_replace('/(\.\.\/+)/', '', $file));
		return $file;
	}

	protected function findPage($key, $value, $pages)
	{
		foreach ($pages as $pageKey => $page) {
			if ($page[$key] === $value) {
				return $page;
			}
		}
		return null;
	}

	protected function removeEmptySubFolders($path)
	{
		$empty = true;
		foreach (glob($path . DIRECTORY_SEPARATOR . '*') as $file) {
			if (is_dir($file)) {
				if (!$this->removeEmptySubFolders($file)) $empty = false;
			} else {
				$empty = false;
			}
		}
		if ($empty) rmdir($path);
		return $empty;
	}

	protected function getEditorType()
	{
		if ($this->config->get('plugins-bauncms-baun-admin-admin.editor') == 'advanced') {
			return 'advanced';
		}

		return 'simple';
	}

	protected function slugify($text)
	{
		$text = preg_replace('~[^\\pL\d]+~u', '-', $text);
		$text = trim($text, '-');
		$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
		$text = strtolower($text);
		$text = preg_replace('~[^-\w]+~', '', $text);
		return $text;
	}

}