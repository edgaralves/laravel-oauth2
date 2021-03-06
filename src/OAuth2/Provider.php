<?php 

namespace OAuth2;

use OAuth2\Exception;
use OAuth2\Token;

/**
 * OAuth Provider
 *
 * @package    CodeIgniter/OAuth
 * @category   Provider
 * @author     Phil Sturgeon
 * @copyright  Phil Sturgeon
 * @license    http://philsturgeon.co.uk/code/dbad-license
 */

abstract class Provider {

	/**
	 * @var  string  provider name
	 */
	public $name;

	/**
	 * @var  string  uid key name
	 */
	public $uid_key = 'uid';

	/**
	 * @var  string  scope separator, most use "," but some like Google are spaces
	 */
	public $scope_seperator = ',';

	/**
	 * @var  string  additional request parameters to be used for remote requests
	 */
	public $callback = null;

	/**
	 * @var  string scope
	 */
	public $scope;

	/**
	 * @var  array  additional request parameters to be used for remote requests
	 */
	protected $params = array();

	/**
	 * @var array maps for providers that use non standard variable names
	 */
	protected $params_map = array();

	/**
	 * @var  string  the method to use when requesting tokens
	 */
	protected $method = 'GET';

	/**
	 * Overloads default class properties from the options.
	 *
	 * Any of the provider options can be set here, such as app_id or secret.
	 *
	 * @param   array   provider options
	 * @return  void
	 */
	public function __construct(array $options = array())
	{
		if ( ! $this->name)
		{
			// Attempt to guess the name from the class name
			$this->name = strtolower(get_class($this));
		}

		if (empty($options['id']))
		{
			throw new Exception('Required option not provided: id');
		}

		$this->client_id = $options['id'];
		
		// Set redirect uri
		$this->redirect_uri = \URL::to(\Request::path()); // '/'.ltrim(Laravel\URI::current(), '/');
			
		// Set options
		isset($options['callback']) and $this->callback = $options['callback'];
		isset($options['secret']) and $this->client_secret = $options['secret'];
		isset($options['scope']) and $this->scope = $options['scope'];
		isset($options['redirect_uri']) and $this->redirect_uri = $options['redirect_uri'];
	}

	/**
	 * Return the value of any protected class variable.
	 *
	 *     // Get the provider signature
	 *     $signature = $provider->signature;
	 *
	 * @param   string  variable name
	 * @return  mixed
	 */
	public function __get($key)
	{
		return $this->$key;
	}

	/**
	 * Returns the authorization URL for the provider.
	 *
	 *     $url = $provider->url_authorize();
	 *
	 * @return  string
	 */
	abstract public function url_authorize();

	/**
	 * Returns the access token endpoint for the provider.
	 *
	 *     $url = $provider->url_access_token();
	 *
	 * @return  string
	 */
	abstract public function url_access_token();

	/*
	* Get an authorization code from Facebook.  Redirects to Facebook, which this redirects back to the app using the redirect address you've set.
	*/
	public function authorize($options = array())
	{
		if(isset($options['state'])) {
			// This means you're using your own checking mechanism
			$state = $options['state'];
		}
		else {
			// This puts it in the session, but the check doesn't exist yet.
			$state = md5(uniqid(rand(), TRUE));
			\Session::put('state', $state);
		}

		$params = array(
			'client_id' 		=> $this->client_id,
			'redirect_uri' 		=> isset($options['redirect_uri']) ? $options['redirect_uri'] : $this->redirect_uri,
			'state' 			=> $state,
			'scope'				=> is_array($this->scope) ? implode($this->scope_seperator, $this->scope) : $this->scope,
			'response_type' 	=> 'code',
			'approval_prompt' 	=> isset($options['approval_prompt']) ? $options['approval_prompt'] : 'force' // - google force-recheck
		);

		// Searches for params that have a non standard index
		if ( ! empty($this->params_map)) {
			foreach ($this->params_map as $param => $mapped) {
				if (isset($params[$param])) {
					$params[$mapped] = $params[$param];
					unset($params[$param]);
				}
			}
		}

		$url = $this->url_authorize().'?'.http_build_query($params);
		return \Redirect::to($url);
	}

	/*
	* Get access to the API
	*
	* @param	string	The access code
	* @return	object	Success or failure along with the response details
	*/
	public function access($code, $options = array())
	{
		$params = array(
			'client_id' 	=> $this->client_id,
			'client_secret' => $this->client_secret,
			'grant_type' 	=> isset($options['grant_type']) ? $options['grant_type'] : 'authorization_code',
		);

		switch ($params['grant_type'])
		{
			case 'authorization_code':
				$params['code'] = $code;
				$params['redirect_uri'] = isset($options['redirect_uri']) ? $options['redirect_uri'] : $this->redirect_uri;
			break;

			case 'refresh_token':
				$params['refresh_token'] = $code;
			break;
		}

		$response = null;
		$url = $this->url_access_token();

		switch ($this->method)
		{
			case 'GET':
				// Need to switch to Request library, but need to test it on one that works
				$url .= '?'.http_build_query($params);
				$response = file_get_contents($url);

				parse_str($response, $return);
			break;

			case 'POST':
				$postdata = http_build_query($params);
				$opts = array(
					'http' => array(
						'method'  => 'POST',
						'header'  => 'Content-type: application/x-www-form-urlencoded',
						'content' => $postdata
					)
				);
				$_default_opts = stream_context_get_params(stream_context_get_default());
				$context = stream_context_create(array_merge_recursive($_default_opts['options'], $opts));
				$response = file_get_contents($url, false, $context);

				$return = json_decode($response, true);
			break;

			default:
				throw new \OutOfBoundsException("Method '{$this->method}' must be either GET or POST");
		}

		if (isset($return['error']))
		{
			throw new Exception($return);
		}

		switch ($params['grant_type'])
		{
			case 'authorization_code':
				return Token::factory('access', $return);
			break;

			case 'refresh_token':
				return Token::factory('access', $return);
			break;
		}

	}

}