<?php namespace BaunPlugin\Admin;

class Posts extends Base {

	protected $posts;
	private $blog_path_base;

	public function init()
	{
		$this->events->addListener('baun.filesToPosts', [$this, 'getPosts']);
	}

	public function getPosts($event, $posts)
	{
		$this->posts = $posts;
		$this->blog_path_base = str_replace($this->config->get('app.content_path'), '', $this->config->get('baun.blog_path'));
	}

	public function setupRoutes()
	{
		$this->router->group(['before' => ['csrf', 'users', 'auth']], function(){
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
		$data['blog_path_base'] = $this->blog_path_base;
		$data['errors'] = $this->session->getFlashBag()->get('error');

		return $this->theme->render('create-' . $this->getEditorType(), $data);
	}

	public function routePostPostsCreate()
	{
		$post = $_POST;
		$post['folder'] = $this->blog_path_base;
		if ($this->getEditorType() == 'advanced') {
			$post['path'] = $this->blog_path_base . '/' . $post['path'];
		} else {
			if (strtotime($post['order'])) {
				$post['order'] = date('Ymd', strtotime($post['order']));
			} else {
				$post['order'] = date('Ymd');
			}
		}

		if ($this->saveFile($post, $this->posts) === false) {
			return header('Location: ' . $this->config->get('app.base_url') . '/admin/posts/create');
		}

		$this->session->getFlashBag()->add('success', 'Post created');
		return header('Location: ' . $this->config->get('app.base_url') . '/admin/posts');
	}

	public function routePostsEdit()
	{
		$data = $this->getGlobalTemplateData();
		$file = $this->getFileFromQuerySting();

		$data['type'] = 'post';
		$data['label'] = 'Post';
		$data['form_action'] = $this->config->get('app.base_url') . '/admin/posts/edit?file=' . urlencode($file);
		$data['blog_path_base'] = $this->blog_path_base;
		$post = $this->findFile('path', $file, $this->posts);

		if (!$post) {
			return header('Location: ' . $this->config->get('app.base_url') . '/admin/404');
		}

		$input = file_get_contents($this->config->get('app.content_path') . $post['path']);
		$args = explode('----', $input, 2);
		$data['path'] = str_replace($data['blog_path_base'] . '/', '', $file);
		$data['header'] = isset($args[0]) ? $args[0] : '';
		$data['content'] = isset($args[1]) ? $args[1] : '';

		$parsedInput = $this->contentParser->parse($input);
		$data['title'] = isset($parsedInput['info']['title']) ? $parsedInput['info']['title'] : '';
		$data['description'] = isset($parsedInput['info']['description']) ? $parsedInput['info']['description'] : '';
		if (preg_match('/^\d+\-/', basename($post['path']))) {
			list($order, $path) = explode('-', basename($post['path']), 2);
			$data['order'] = $order;
		}

		$data['view_url'] = $this->config->get('app.base_url') . ($post['route'] != '/' ? '/' . $post['route'] : '');

		return $this->theme->render('edit-' . $this->getEditorType(), $data);
	}

	public function routePostPostsEdit()
	{
		$file = $this->getFileFromQuerySting();

		$post = $this->findFile('path', $file, $this->posts);
		if (!$post) {
			return header('Location: ' . $this->config->get('app.base_url') . '/admin/404');
		}

		$post = $_POST;
		$post['folder'] = $this->blog_path_base;
		if ($this->getEditorType() == 'advanced') {
			$post['path'] = $this->blog_path_base . '/' . $post['path'];
		} else {
			if (strtotime($post['order'])) {
				$post['order'] = date('Ymd', strtotime($post['order']));
			} else {
				$post['order'] = date('Ymd');
			}
		}

		if (($savedFile = $this->saveFile($post, $this->posts, $file)) === false) {
			return header('Location: ' . $this->config->get('app.base_url') . '/admin/posts/edit?file=' . urlencode($file));
		}

		$this->session->getFlashBag()->add('success', 'Post saved');
		return header('Location: ' . $this->config->get('app.base_url') . '/admin/posts/edit?file=' . urlencode($savedFile));
	}

	public function routePostsDelete()
	{
		$file = $this->getFileFromQuerySting();
		$post = $this->findFile('path', $file, $this->posts);

		if (!$post) {
			return header('Location: ' . $this->config->get('app.base_url') . '/admin/404');
		}

		unlink($this->config->get('app.content_path') . $post['path']);
		$this->removeEmptySubFolders($this->config->get('baun.blog_path'));

		$this->session->getFlashBag()->add('success', 'Post deleted');
		return header('Location: ' . $this->config->get('app.base_url') . '/admin/posts');
	}

}