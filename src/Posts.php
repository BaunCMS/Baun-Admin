<?php namespace BaunPlugin\Admin;

class Posts extends Base {

	protected $posts;

	public function init()
	{
		$this->events->addListener('baun.filesToPosts', [$this, 'getPosts']);
	}

	public function getPosts($event, $posts)
	{
		$this->posts = $posts;
	}

	public function setupRoutes()
	{
		$this->router->group(['before' => ['users', 'auth']], function(){
			$this->router->add('GET',  '/admin/posts', [$this, 'routePosts']);
			$this->router->add('GET',  '/admin/posts/create', [$this, 'routePostsCreate']);
			$this->router->add('POST', '/admin/posts/create', [$this, 'routePostPostsCreate']);
			$this->router->add('GET',  '/admin/posts/edit', [$this, 'routePostsEdit']);
			$this->router->add('POST', '/admin/posts/edit', [$this, 'routePostPostsEdit']);
			$this->router->add('GET',  '/admin/posts/delete', [$this, 'routePostsDelete']);
		});
	}

	public function routePosts()
	{
		$data = $this->getGlobalTemplateData();

		$page = isset($_GET['page']) && $_GET['page'] ? abs(intval($_GET['page'])) : 1;
		$offset = 0;
		if ($page > 1) {
			$offset = $page - 1;
		}

		$paginatedPosts = array_chunk($this->posts, 10);
		$total_posts = count($paginatedPosts);
		if (isset($paginatedPosts[$offset])) {
			$paginatedPosts = $paginatedPosts[$offset];
		} else {
			$paginatedPosts = [];
		}
		$data['posts'] = $paginatedPosts;
		$data['pagination'] = [
			'total_pages' => $total_posts,
			'current_page' => $page,
		];

		return $this->theme->render('posts', $data);
	}

	public function routePostsCreate()
	{
		$data = $this->getGlobalTemplateData();
		$data['type'] = 'post';
		$data['label'] = 'Post';
		$data['form_action'] = $this->config->get('app.base_url') . '/admin/posts/create';
		$data['blog_path_base'] = str_replace($this->config->get('app.content_path'), '', $this->config->get('baun.blog_path'));

		return $this->theme->render('create-' . $this->getEditorType(), $data);
	}

	public function routePostPostsCreate()
	{
		$data = $this->getGlobalTemplateData();

		$path = isset($_POST['path']) ? $_POST['path'] : null;
		$header = isset($_POST['header']) ? trim($_POST['header']) : null;
		$content = isset($_POST['content']) ? trim($_POST['content']) : null;

		if (!$path) {
			$data['error'] = 'A valid file path is required';
		}
		if (!is_writable($this->config->get('baun.blog_path'))) {
			$data['error'] = 'The blog folder is not writeable';
		}

		$path = strtolower(preg_replace('/(\.\.\/+)/', '', $path));
		$filename = basename($path);
		if (!$this->endsWith($filename, $this->config->get('app.content_extension'))) {
			$filename = $filename . $this->config->get('app.content_extension');
		}
		$path = dirname($this->config->get('baun.blog_path') . '/' . $path);
		if (file_exists($path . '/' . $filename)) {
			$data['error'] = 'A post already exists at this path';
		}
		if (isset($data['error'])) {
			return $this->theme->render('create-' . $this->getEditorType(), $data);
		}

		if (!is_dir($path)) {
			mkdir($path, 0777, true);
		}
		$output = implode("\n----\n", [$header, $content]);
		file_put_contents($path . '/' . $filename, $output);

		return header('Location: ' . $this->config->get('app.base_url') . '/admin/posts');
	}

	public function routePostsEdit()
	{
		$data = $this->getGlobalTemplateData();
		$file = $this->getFileFromQuerySting();

		$data['type'] = 'post';
		$data['label'] = 'Post';
		$data['form_action'] = $this->config->get('app.base_url') . '/admin/posts/edit?file=' . urlencode($file);
		$data['blog_path_base'] = str_replace($this->config->get('app.content_path'), '', $this->config->get('baun.blog_path'));
		$data['page'] = $this->findPage('path', $file, $this->posts);

		if (!$data['page']) {
			return header('Location: ' . $this->config->get('app.base_url') . '/admin/404');
		}
		$data['view_url'] = $this->config->get('app.base_url') . ($data['page']['route'] != '/' ? '/' . $data['page']['route'] : '');

		$input = file_get_contents($this->config->get('app.content_path') . $data['page']['path']);
		$args = explode('----', $input, 2);
		$data['path'] = str_replace($data['blog_path_base'] . '/', '', $file);
		$data['header'] = isset($args[0]) ? $args[0] : '';
		$data['content'] = isset($args[1]) ? $args[1] : '';

		$parsedInput = $this->contentParser->parse($input);
		$data['title'] = isset($parsedInput['info']['title']) ? $parsedInput['info']['title'] : '';
		$data['description'] = isset($parsedInput['info']['description']) ? $parsedInput['info']['description'] : '';

		return $this->theme->render('edit-' . $this->getEditorType(), $data);
	}

	public function routePostPostsEdit()
	{
		$data = $this->getGlobalTemplateData();

		$path = isset($_POST['path']) ? $_POST['path'] : null;
		$header = isset($_POST['header']) ? trim($_POST['header']) : null;
		$content = isset($_POST['content']) ? trim($_POST['content']) : null;

		if (!$path) {
			$data['error'] = 'A valid file path is required';
		}
		if (!is_writable($this->config->get('baun.blog_path'))) {
			$data['error'] = 'The blog folder is not writeable';
		}

		$path = strtolower(preg_replace('/(\.\.\/+)/', '', $path));
		$filename = basename($path);
		if (!$this->endsWith($filename, $this->config->get('app.content_extension'))) {
			$filename = $filename . $this->config->get('app.content_extension');
		}
		$path = dirname($this->config->get('baun.blog_path') . '/' . $path);
		if (isset($data['error'])) {
			return $this->theme->render('edit-' . $this->getEditorType(), $data);
		}

		$output = implode("\n----\n", [$header, $content]);
		file_put_contents($path . '/' . $filename, $output);

		return header('Location: ' . $this->config->get('app.base_url') . '/admin/posts');
	}

	public function routePostsDelete()
	{
		$file = $this->getFileFromQuerySting();
		$post = $this->findPage('path', $file, $this->posts);

		if (!$post) {
			return header('Location: ' . $this->config->get('app.base_url') . '/admin/404');
		}

		unlink($this->config->get('app.content_path') . $post['path']);
		$this->removeEmptySubFolders($this->config->get('baun.blog_path'));

		return header('Location: ' . $this->config->get('app.base_url') . '/admin/posts');
	}

}