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
		foreach ($this->pages as $key => $page) {
			$this->pages[$key]['updated'] = date('j M Y \- H:i', filemtime($this->config->get('app.content_path') . $page['path']));
		}
	}

	public function setupRoutes()
	{
		$this->router->group(['before' => ['users', 'auth']], function(){
			$this->router->add('GET',  '/admin', [$this, 'routePages']);
			$this->router->add('GET',  '/admin/pages', [$this, 'routePages']);
			$this->router->add('GET',  '/admin/pages/create', [$this, 'routePagesCreate']);
			$this->router->add('POST', '/admin/pages/create', [$this, 'routePostPagesCreate']);
			$this->router->add('GET',  '/admin/pages/edit', [$this, 'routePagesEdit']);
			$this->router->add('POST', '/admin/pages/edit', [$this, 'routePostPagesEdit']);
			$this->router->add('GET',  '/admin/pages/delete', [$this, 'routePagesDelete']);
		});
	}

	public function routePages()
	{
		$data = $this->getGlobalTemplateData();

		$page = isset($_GET['page']) && $_GET['page'] ? abs(intval($_GET['page'])) : 1;
		$offset = 0;
		if ($page > 1) {
			$offset = $page - 1;
		}

		$paginatedPages = array_chunk($this->pages, 10);
		$total_pages = count($paginatedPages);
		if (isset($paginatedPages[$offset])) {
			$paginatedPages = $paginatedPages[$offset];
		} else {
			$paginatedPages = [];
		}
		$data['pages'] = $paginatedPages;
		$data['pagination'] = [
			'total_pages' => $total_pages,
			'current_page' => $page,
		];

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
		$header = isset($_POST['header']) ? trim($_POST['header']) : null;
		$content = isset($_POST['content']) ? trim($_POST['content']) : null;

		if (!$path) {
			$data['error'] = 'A valid file path is required';
		}
		if (!is_writable($this->config->get('app.content_path'))) {
			$data['error'] = 'The content folder is not writeable';
		}

		$path = strtolower(preg_replace('/(\.\.\/+)/', '', $path));
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

	public function routePagesEdit()
	{
		$data = $this->getGlobalTemplateData();
		$data['type'] = 'page';
		$data['label'] = 'Page';
		$data['form_action'] = $this->config->get('app.base_url') . '/admin/pages/edit';

		$file = $this->getFileFromQuerySting();
		$data['page'] = $this->findPage('path', $file, $this->pages);

		if (!$data['page']) {
			return header('Location: ' . $this->config->get('app.base_url') . '/admin/404');
		}
		$data['view_url'] = $this->config->get('app.base_url') . ($data['page']['route'] != '/' ? '/' . $data['page']['route'] : '');

		$input = file_get_contents($this->config->get('app.content_path') . $data['page']['path']);
		$args = explode('----', $input, 2);
		$data['path'] = $file;
		$data['header'] = isset($args[0]) ? $args[0] : '';
		$data['content'] = isset($args[1]) ? $args[1] : '';

		return $this->theme->render('edit', $data);
	}

	public function routePostPagesEdit()
	{
		$data = $this->getGlobalTemplateData();

		$path = isset($_POST['path']) ? $_POST['path'] : null;
		$header = isset($_POST['header']) ? trim($_POST['header']) : null;
		$content = isset($_POST['content']) ? trim($_POST['content']) : null;

		if (!$path) {
			$data['error'] = 'A valid file path is required';
		}
		if (!is_writable($this->config->get('app.content_path'))) {
			$data['error'] = 'The content folder is not writeable';
		}

		$path = strtolower(preg_replace('/(\.\.\/+)/', '', $path));
		$filename = basename($path);
		if (!$this->endsWith($filename, $this->config->get('app.content_extension'))) {
			$filename = $filename . $this->config->get('app.content_extension');
		}
		$path = dirname($this->config->get('app.content_path') . $path);
		if (isset($data['error'])) {
			return $this->theme->render('edit', $data);
		}

		$output = implode("\n----\n", [$header, $content]);
		file_put_contents($path . '/' . $filename, $output);

		return header('Location: ' . $this->config->get('app.base_url') . '/admin');
	}

	public function routePagesDelete()
	{
		$file = $this->getFileFromQuerySting();
		$page = $this->findPage('path', $file, $this->pages);

		if (!$page) {
			return header('Location: ' . $this->config->get('app.base_url') . '/admin/404');
		}

		unlink($this->config->get('app.content_path') . $page['path']);
		$this->removeEmptySubFolders($this->config->get('app.content_path'));

		return header('Location: ' . $this->config->get('app.base_url') . '/admin');
	}

}