<?php

namespace OAuth2\Provider;

use OAuth2\Provider;
use OAuth2\Token_Access;

/*
 * RunKeeper API credentials: http://runkeeper.com/partner/applications/view
 * RunKeeper API docs: http://runkeeper.com/developer/healthgraph/registration-authorization
 */


/**
 * RunKeeper OAuth Provider
 *
 * @package    laravel-oauth2
 * @category   Provider
 * @author     Andreas Creten
 */

class Meocloud extends Provider {
	/**
	* @var  string  provider name
	*/
	public $name = 'meocloud';

	/**
	 * @var  string  the method to use when requesting tokens
	 */
	protected $method = 'POST';

	/**
	 * Returns the authorization URL for the provider.
	 *
	 * @return  string
	 */
	public function url_authorize()
	{
		return 'https://meocloud.pt/oauth2/authorize';
	}

	/**
	* Returns the access token endpoint for the provider.
	*
	* @return  string
	*/
	public function url_access_token()
	{
		return 'https://meocloud.pt/oauth2/token';
	}

	public function url_api_endpoint()
	{
		return 'https://api-content.meocloud.pt';
	}

	public function get_token($config)
	{
		// TODO Validation of expires date
		if (isset($config['expires']) && $config['expires'] < 1) {
			$token = $this->access($config['refresh_token'], array('grant_type' => 'refresh_token'));
		} else {
			$token = \OAuth2\Token::factory('access', $config);
		}

		return $token;
	}

	public function upload_file(Token_Access $token, $fileName, $uploadedFilePath)
	{
		if (!file_exists($uploadedFilePath)) {
			throw new \Exception("Ficheiro enviado não existe");
		}

        $requestEndpoint = $this->url_api_endpoint() . "/1/Files/meocloud/{$fileName}?".http_build_query(array(
			'access_token' => $token->access_token,
			'overwrite'	=> 'false'
		));

		$opts = array(
			'http' => array(
				'method'  => 'POST',
				'header'  => 'Content-type: application/x-www-form-urlencoded',
				'content' => file_get_contents($uploadedFilePath)
			)
		);
		$context = stream_context_create($opts);
		$response = file_get_contents($requestEndpoint, false, $context);

		$return = json_decode($response, true);

        return $return;
	}
}
