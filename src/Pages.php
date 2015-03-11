<?php namespace BaunPlugin\Admin;

class Pages extends Base {

	protected $pages;

	public function init()
	{
		$this->events->addListener('baun.filesToRoutes', [$this, 'getPages']);
	}

	public function getPages($event, $pages)
	{
		$this->pages = $pages;
	}

	public function setupRoutes()
	{
		$this->router->group(['before' => ['users', 'auth']], function(){
			$this->router->add('GET',  '/admin', [$this, 'routePages']);
			$this->router->add('GET',  '/admin/pages/create', [$this, 'routePagesCreate']);
			$this->router->add('POST', '/admin/pages/create', [$this, 'routePostPagesCreate']);
			$this->router->add('GET',  '/admin/pages/edit', [$this, 'routePagesEdit']);
			$this->router->add('POST', '/admin/pages/edit', [$this, 'routePostPagesEdit']);
			$this->router->add('GET',  '/admin/pages/delete', [$this, 'routePagesDelete']);
			$this->router->add('POST', '/admin/pages/delete', [$this, 'routePostPagesDelete']);
		});
	}

	public function routePages()
	{
		$data = $this->getGlobalTemplateData();
		$data['pages'] = $this->pages;
		return $this->theme->render('pages', $data);
	}

	public function routePagesCreate()
	{
		$data = $this->getGlobalTemplateData();
		$data['type'] = 'page';
		$data['label'] = 'Page';
		$data['form_action'] = $this->config->get('app.base_url') . '/admin/pages/create';

		return $this->theme->render('create', $data);
	}

	public function routePostPagesCreate()
	{
		$data = $this->getGlobalTemplateData();

		$path = isset($_POST['path']) ? $_POST['path'] : null;
		$header = isset($_POST['header']) ? $_POST['header'] : null;
		$content = isset($_POST['content']) ? $_POST['content'] : null;

		if (!$path) {
			$data['error'] = 'A valid file path is required';
		}
		if (!is_writable($this->config->get('app.content_path'))) {
			$data['error'] = 'The content folder is not writeable';
		}

		$filename = basename($path);
		if (!$this->endsWith($filename, $this->config->get('app.content_extension'))) {
			$filename = $filename . $this->config->get('app.content_extension');
		}
		$path = dirname($this->config->get('app.content_path') . $path);
		if (file_exists($path . '/' . $filename)) {
			$data['error'] = 'A page already exists at this path';
		}
		if (isset($data['error'])) {
			return $this->theme->render('create', $data);
		}

		if (!is_dir($path)) {
			mkdir($path, 0777, true);
		}
		$output = implode("\n----\n", [$header, $content]);
		file_put_contents($path . '/' . $filename, $output);

		return header('Location: ' . $this->config->get('app.base_url') . '/admin');
	}

	private function endsWith($haystack, $needle)
	{
		$length = strlen($needle);
		if ($length == 0) {
			return true;
		}
		return (substr($haystack, -$length) === $needle);
	}

}