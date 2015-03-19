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
		$this->router->group(['before' => ['csrf', 'users', 'auth']], function(){
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
		$data['folders'] = $this->getFolders();
		$data['errors'] = $this->session->getFlashBag()->get('error');

		return $this->theme->render('create-' . $this->getEditorType(), $data);
	}

	public function routePostPagesCreate()
	{
		if ($this->saveFile($_POST, $this->pages) === false) {
			return header('Location: ' . $this->config->get('app.base_url') . '/admin/pages/create');
		}

		$this->session->getFlashBag()->add('success', 'Page created');
		return header('Location: ' . $this->config->get('app.base_url') . '/admin');
	}

	public function routePagesEdit()
	{
		$data = $this->getGlobalTemplateData();
		$file = $this->getFileFromQuerySting();

		$data['type'] = 'page';
		$data['label'] = 'Page';
		$data['form_action'] = $this->config->get('app.base_url') . '/admin/pages/edit?file=' . urlencode($file);
		$data['folders'] = $this->getFolders();
		$data['errors'] = $this->session->getFlashBag()->get('error');

		$page = $this->findFile('path', $file, $this->pages);
		if (!$page) {
			return header('Location: ' . $this->config->get('app.base_url') . '/admin/404');
		}

		$pageContents = file_get_contents($this->config->get('app.content_path') . $page['path']);
		$args = explode('----', $pageContents, 2);
		$data['path'] = $file;
		$data['header'] = isset($args[0]) ? $args[0] : '';
		$data['content'] = isset($args[1]) ? $args[1] : '';

		$parsedInput = $this->contentParser->parse($pageContents);
		$data['title'] = isset($parsedInput['info']['title']) ? $parsedInput['info']['title'] : '';
		$data['description'] = isset($parsedInput['info']['description']) ? $parsedInput['info']['description'] : '';
		$data['current_folder'] = (dirname($page['path']) == '.' ? '/' : dirname($page['path']));
		if (preg_match('/^\d+\-/', basename($page['path']))) {
			list($order, $path) = explode('-', basename($page['path']), 2);
			$data['order'] = $order;
		}

		$data['view_url'] = $this->config->get('app.base_url') . ($page['route'] != '/' ? '/' . $page['route'] : '');

		return $this->theme->render('edit-' . $this->getEditorType(), $data);
	}

	public function routePostPagesEdit()
	{
		$file = $this->getFileFromQuerySting();

		$page = $this->findFile('path', $file, $this->pages);
		if (!$page) {
			return header('Location: ' . $this->config->get('app.base_url') . '/admin/404');
		}

		if (($savedFile = $this->saveFile($_POST, $this->pages, $file)) === false) {
			return header('Location: ' . $this->config->get('app.base_url') . '/admin/pages/edit?file=' . urlencode($file));
		}

		$this->session->getFlashBag()->add('success', 'Page saved');
		return header('Location: ' . $this->config->get('app.base_url') . '/admin/pages/edit?file=' . urlencode($savedFile));
	}

	public function routePagesDelete()
	{
		$file = $this->getFileFromQuerySting();
		$page = $this->findFile('path', $file, $this->pages);

		if (!$page) {
			return header('Location: ' . $this->config->get('app.base_url') . '/admin/404');
		}

		unlink($this->config->get('app.content_path') . $page['path']);
		$this->removeEmptySubFolders($this->config->get('app.content_path'));

		$this->session->getFlashBag()->add('success', 'Page deleted');
		return header('Location: ' . $this->config->get('app.base_url') . '/admin');
	}

	private function getFolders()
	{
		$folders = [];

		foreach ($this->pages as $page) {
			$folder = dirname($page['path']);
			if ($folder != '.') {
				$folders[$folder] = $folder;
			}
		}

		return $folders;
	}

}