<?php namespace BaunPlugin\Admin;

class License {

	protected $license_key;
	protected $theme;

	public function __construct($license_key, $themeProvider)
	{
		$this->license_key = $license_key;
		$this->theme = $themeProvider;
	}

	public function validate_license()
	{
		if (!$this->license_key) {
			$data['error'] = 'Missing license key in config/plugins/bauncms/baun-admin/admin.php';
			$this->theme->render('license-error', $data);
			exit;
		}

		$cache_file = BASE_PATH . 'cache/baun-admin-license.json';
		if (file_exists($cache_file) && (filemtime($cache_file) + 21600 >= time())) {
			$response = file_get_contents($cache_file);
		} else {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, 'http://license.bauncms.com/verify.php?license=' . $this->license_key);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($ch);
			curl_close($ch);
		}

		$data = json_decode($response);
		if ($data->license != 'valid') {
			$templateData['error'] = $data->message;
			$this->theme->render('license-error', $templateData);
			exit;
		} else {
			file_put_contents($cache_file, json_encode($data));
		}
	}

}