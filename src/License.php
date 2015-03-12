<?php namespace BaunPlugin\Admin;

class License {

	protected $license_key;

	public function __construct($license_key)
	{
		$this->license_key = $license_key;
		if (!$this->license_key) {
			die('Error: Missing license key in <code>config/plugins/bauncms/baun-admin/admin.php</code>');
		}

		$this->validate_license();
	}

	private function validate_license()
	{

	}

}