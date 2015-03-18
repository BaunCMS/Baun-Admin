<?php namespace BaunPlugin\Admin;

use Baun\Plugin;
use BaunPlugin\Admin\Pages;
use Symfony\Component\HttpFoundation\Session\Session;

class Admin extends Plugin {

	protected $session;
	protected $license_key;
	protected $users;

	private $license;
	private $adminPages;
	private $adminPosts;

	public function init()
	{
		$this->session = new Session();
		$this->session->start();

		$this->license_key = $this->config->get('plugins-bauncms-baun-admin-admin.license_key');
		$this->users = $this->config->get('plugins-bauncms-baun-admin-admin.users');

		$this->license = new License($this->license_key, $this->theme);

		$this->adminPages = new Pages(
			$this->config,
			$this->session,
			$this->events,
			$this->router,
			$this->theme,
			$this->contentParser
		);
		$this->adminPages->init();

		$this->theme->addPath(dirname(__DIR__) . '/templates');
		$this->events->addListener('baun.getFiles', [$this, 'setupPosts']);
		$this->events->addListener('baun.afterSetupRoutes', [$this, 'setupRoutes']);
	}

	public function setupPosts()
	{
		if ($this->config->get('baun.blog_path')) {
			$this->adminPosts = new Posts(
				$this->config,
				$this->session,
				$this->events,
				$this->router,
				$this->theme,
				$this->contentParser
			);
			$this->adminPosts->init();
		}
	}

	public function setupRoutes()
	{
		$this->router->filter('users', function(){
			if (empty($this->users)) {
				header('Location: ' . $this->config->get('app.base_url') . '/admin/create-user');
				return false;
			}
		});
		$this->router->filter('auth', function(){
			$this->license->validate_license();

			if (!$this->session->get('logged_in')) {
				header('Location: ' . $this->config->get('app.base_url') . '/admin/login');
				return false;
			}
		});

		$this->router->add('GET',  '/admin/create-user', [$this, 'routeCreateUser']);
		$this->router->add('POST', '/admin/create-user', [$this, 'routePostCreateUser']);

		$this->router->group(['before' => ['users']], function(){
			$this->router->add('GET',  '/admin/login', [$this, 'routeLogin']);
			$this->router->add('POST', '/admin/login', [$this, 'routePostLogin']);
		});

		$this->router->group(['before' => ['users', 'auth']], function(){
			$this->router->add('GET', '/admin/logout', [$this, 'routeLogout']);
			$this->router->add('GET', '/admin/users', [$this, 'routeUsers']);
		});

		$this->adminPages->setupRoutes();
		if (isset($this->adminPosts)) {
			$this->adminPosts->setupRoutes();
		}
	}

	public function routeCreateUser()
	{
		$data = $this->getGlobalTemplateData();
		return $this->theme->render('create-user', $data);
	}

	public function routePostCreateUser()
	{
		$data = $this->getGlobalTemplateData();

		$email = isset($_POST['email']) ? $_POST['email'] : null;
		$password = isset($_POST['password']) ? $_POST['password'] : null;

		if (!$email || !$password) {
			$data['error'] = 'Both an email address and password are required';
			return $this->theme->render('create-user', $data);
		}

		$data['user'] = [
			'email' => $email,
			'password' => password_hash($password, PASSWORD_BCRYPT)
		];

		return $this->theme->render('created-user', $data);
	}

	public function routeLogin()
	{
		$data = $this->getGlobalTemplateData();
		return $this->theme->render('login', $data);
	}

	public function routePostLogin()
	{
		$data = $this->getGlobalTemplateData();

		$email = isset($_POST['email']) ? $_POST['email'] : null;
		$password = isset($_POST['password']) ? $_POST['password'] : null;

		if (!$email || !$password) {
			$data['error'] = 'Both an email address and password are required';
		}
		if (!isset($this->users[$email])) {
			$data['error'] = 'No user exists for this email address';
		}
		if (!isset($this->users[$email]['password'])) {
			$data['error'] = 'This user has no password set';
		}
		if (!password_verify($password, $this->users[$email]['password'])) {
			$data['error'] = 'Invalid password';
		}
		if (isset($data['error'])) {
			return $this->theme->render('login', $data);
		}

		$this->session->set('logged_in', true);

		return header('Location: ' . $this->config->get('app.base_url') . '/admin');
	}

	public function routeLogout()
	{
		$this->session->clear();
		return header('Location: ' . $this->config->get('app.base_url') . '/admin/login');
	}

	public function routeUsers()
	{
		$data = $this->getGlobalTemplateData();
		$data['users'] = $this->users;
		return $this->theme->render('users', $data);
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

}