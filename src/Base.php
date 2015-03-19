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
			'successes' => $this->session->getFlashBag()->get('success'),
		];
	}

	protected function getFileFromQuerySting()
	{
		$file = isset($_GET['file']) ? urldecode($_GET['file']) : null;
		$file = strtolower(preg_replace('/(\.\.\/+)/', '', $file));
		return $file;
	}

	protected function findFile($key, $value, $files)
	{
		foreach ($files as $fileKey => $file) {
			if ($file[$key] === $value) {
				return $file;
			}
		}
		return null;
	}

	protected function routeExists($path, $files)
	{
		if (file_exists($path)) {
			return true;
		}

		$folder = str_replace(rtrim($this->config->get('app.content_path'), '/'), '', dirname($path));
		$file = basename($path, $this->config->get('app.content_extension'));
		if (preg_match('/^\d+\-/', $file)) {
			list($order, $fileName) = explode('-', $file, 2);
			$file = $fileName;
		}

		$route = $file;
		if ($folder) {
			$route = ltrim($folder, '/') . '/' . $file;
		}

		return $this->findFile('route', $route, $files);
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

	protected function endsWith($haystack, $needle)
	{
		$length = strlen($needle);
		if ($length == 0) {
			return true;
		}
		return (substr($haystack, -$length) === $needle);
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

	protected function saveFile($data, $files, $existingFile = '')
	{
		if ($this->getEditorType() == 'advanced') {
			$path = isset($data['path']) ? $data['path'] : null;
			$header = isset($data['header']) ? trim($data['header']) : null;
			$content = isset($data['content']) ? trim($data['content']) : null;

			if (!$path) {
				$this->session->getFlashBag()->add('error', 'A valid file path is required');
			}
		} else {
			$title = isset($data['title']) ? filter_var($data['title'], FILTER_SANITIZE_STRING) : null;
			$description = isset($data['description']) ? filter_var($data['description'], FILTER_SANITIZE_STRING) : null;
			$order = isset($data['order']) ? filter_var($data['order'], FILTER_SANITIZE_NUMBER_INT) : null;
			$folder = isset($data['folder']) ? $data['folder'] : null;
			$new_folder = isset($data['new_folder']) ? $data['new_folder'] : null;
			$content = isset($data['content']) ? trim($data['content']) : null;

			$path = '';
			if ($slug = $this->slugify($title)) {
				$path = $folder;
				if ($folder == '_new_') {
					$path = trim($new_folder, '/');
				}
				$path .= '/';
				if ($order) {
					$path .= $order . '-';
				}
				$path .= $slug . $this->config->get('app.content_extension');
			} else {
				$this->session->getFlashBag()->add('error', 'A valid title is required');
			}

			$header = '';
			if ($title) {
				$header .= 'title: ' . $title . "\n";
			}
			if ($description) {
				$header .= 'description: ' . $description . "\n";
			}
		}

		if (!is_writable($this->config->get('app.content_path'))) {
			$this->session->getFlashBag()->add('error', 'The content folder is not writeable');
		}

		$path = strtolower(preg_replace('/(\.\.\/+)/', '', $path));
		$filename = basename($path);
		if (!$this->endsWith($filename, $this->config->get('app.content_extension'))) {
			$filename = $filename . $this->config->get('app.content_extension');
		}
		$pathBase = $path;
		$path = dirname($this->config->get('app.content_path') . $path);

		if (!$existingFile || ($existingFile && $existingFile != ltrim($pathBase, '/'))) {
			if ($this->routeExists($path . '/' . $filename, $files)) {
				$this->session->getFlashBag()->add('error', 'A page already exists at this path');
			}
		}

		if ($this->session->getFlashBag()->has('error')) {
			return false;
		}

		if (!is_dir($path)) {
			mkdir($path, 0777, true);
		}
		if ($existingFile && file_exists($this->config->get('app.content_path') . $existingFile)) {
			// Remove original incase file has moved
			unlink($this->config->get('app.content_path') . $existingFile);
		}

		$output = implode("\n----\n", [$header, $content]);
		file_put_contents($path . '/' . $filename, $output);

		return str_replace($this->config->get('app.content_path'), '', $path . '/' . $filename);
	}

}