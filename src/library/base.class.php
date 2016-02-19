<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Aws\Sdk;
use Aws\Credentials\CredentialProvider;

class Base{

	public static function getSdk(){
		return new Sdk([
			'region'  => 'ap-northeast-1',
			'version' => 'latest',
			'credentials' => CredentialProvider::env()
		]);
	}
}
