<?php

/**
 * PHP requests
 * 
 * User friendly HTTP requests library. Heavily inspired by Pythons requests package, and implemented as a wrapper around cURL.
 * 
 * @package php-requests
 * @author  jyggorath@github
 * @license MIT
 * @link    https://github.com/jyggorath/php-requests
 * @version GIT: $Id$ Working but incomplete, might never be finished.
 */




class Requests {


	private const do_verification	= false;
	private const cert_path			= '';


	/**
	 * Prepares and sends a GET or DELETE request
	 * 
	 * @param string $method          'GET' or 'DELETE'
	 * @param string $url             URL
	 * @param array  $headers         Custom request headers, if any (might be empty)
	 * @param bool   $allow_redirects Whether or not to follow 30x redirects
	 * @param bool   $verify          Whether or not to verify certificate for HTTPS requests
	 *                                Note: Won't allways apply, see Requests::curl_base
	 * 
	 * @return array Return value from Requests::curl_send
	 */
	private static function get_base(string $method, string $url, array $headers, bool $allow_redirects, bool $verify) : array {
		$headers = self::lowercase_headers($headers);
		$handle = self::curl_base($url, $headers, $allow_redirects, $verify);
		if ($method == 'DELETE') {
			curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'DELETE');
		}
		return self::curl_send($handle);
	}



	/**
	 * Prepares and sends a POST or PUT request
	 * 
	 * @param string $method          'POST' or 'PUT'
	 * @param string $url             URL
	 * @param mixed  $data            Data for POST body, usually array, but may also be null, or (almost) anything if sent as JSON
	 * @param array  $headers         Custom request headers, if any (might be empty)
	 * @param bool   $allow_redirects Whether or not to follow 30x redirects
	 * @param bool   $verify          Whether or not to verify certificate for HTTPS requests
	 *                                Note: Won't allways apply, see Requests::curl_base
	 * 
	 * @return array Return value from Requests::curl_send
	 */
	private static function post_base(string $method, string $url, $data, array $headers, bool $allow_redirects, bool $verify) : array {
		$headers = self::lowercase_headers($headers);
		if (!array_key_exists('content-type', $headers)) {
			// This might not be good in all cases (?)
			$headers['content-type'] = 'application/x-www-form-urlencoded; charset=utf-8';
		}
		$handle = self::curl_base($url, $headers, $allow_redirects, $verify);
		if ($method == 'PUT') {
			curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'PUT');
		}
		else {
			curl_setopt($handle, CURLOPT_POST, 1);
		}
		if ($headers['content-type'] == 'application/json') {
			curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($data));
		}
		else {
			curl_setopt($handle, CURLOPT_POSTFIELDS, self::collaps_arguments($data));
		}
		return self::curl_send($handle);
	}



	/**
	 * Create basic CurlHandle object which can be used with all requests
	 * 
	 * @param string $url             URL
	 * @param array  $headers         Request headers
	 * @param bool   $allow_redirects Used to set cURL option CURLOPT_FOLLOWLOCATION
	 * @param bool   $verify          Used to set cURL option CURLOPT_SSL_VERIFYPEER
	 *                                Note: Regardless of how this parameter is set, if the URL is http 
	 *                                      and not https, CURLOPT_SSL_VERIFYPEER will be set as false
	 *                                Note: Class constant do_verification overrides this parameter if 
	 *                                      set to false
	 * 
	 * @return CurlHandle
	 */
	private static function curl_base(string $url, array $headers, bool $allow_redirects, bool $verify) {
		if (!self::url_is_https($url)) {
			$verify = false;
		}
		$handle = curl_init($url);
		curl_setopt($handle, CURLOPT_HTTPHEADER,     self::collaps_headers($headers));
		curl_setopt($handle, CURLOPT_FOLLOWLOCATION, intval($allow_redirects));
		curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		if (self::do_verification) {
			curl_setopt($handle, CURLOPT_SSL_VERIFYPEER,	$verify);
			curl_setopt($handle, CURLOPT_CAINFO,			self::cert_path);
		}
		return $handle;
	}



	/**
	 * Sends a request using cURL and retrieves the response
	 * 
	 * Will fail with PHP notice if cURL request executes with error.
	 * 
	 * @param CurlHandle $handle CurlHandle with all options set, except CURLOPT_HEADERFUNCTION
	 * 
	 * @return array Associative array with response headers(associative array), HTTP status code(int), and response body(string)
	 */
	private static function curl_send($handle) : array {
		$headers = [];
		// https://stackoverflow.com/a/41135574/2306552
		curl_setopt($handle, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$headers) {
			// mb_strlen is not RFC compliant in this case (?)
			$length = strlen($header);
			$header = explode(':', $header, 2);
			if (count($header) < 2) {
				return $length;
			}
			$headers[strtolower(trim($header[0]))] = trim($header[1]);
			return $length;
		});
		$response_body = curl_exec($handle);
		if ($response_body === false) {
			trigger_error(curl_error($handle));
		}
		$status_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
		return [
			'headers'     => $headers,
			'status_code' => $status_code,
			'body'        => $response_body
		];
	}



	/**
	 * Converts keys in headers associative array to lowercase
	 * 
	 * @param array $headers HTTP headers as associative array
	 * 
	 * @return array The same as $headers, but with all array keys lowercase
	 */
	private static function lowercase_headers(array $headers) : array {
		return array_change_key_case($headers);
	}



	/**
	 * Convert associative array of arguments to URL encoded form format
	 * 
	 * @param array $arguments Associative array of arguments. Might also be non array do 
	 *                         to how the function is used.
	 * 
	 * @return string Arguments on format: key_1=value_1&key_n=value_n, URL encoded
	 *                If a non array was provided as parameter, will return empty string
	 */
	private static function collaps_arguments($arguments) : string {
		if (!is_array($arguments)) {
			return '';
		}
		$arguments_transformed = [];
		foreach ($arguments as $key => $value) {
			$arguments_transformed[] = urlencode($key) . '=' . urlencode($value);
		}
		return implode('&', $arguments_transformed);
	}



	/**
	 * Converts associative array of HTTP headers to regular array where each header is one string
	 * 
	 * @param array $headers HTTP headers as associative array
	 * 
	 * @return array Regular array where each index is a string like: 'key: value'
	 */
	private static function collaps_headers(array $headers) : array {
		$headers_transformed = [];
		foreach ($headers as $key => $value) {
			$headers_transformed[] = $key . ': ' . $value;
		}
		return $headers_transformed;
	}



	/**
	 * Check if the protocol part of the URL is https
	 * 
	 * @param string $url Full URL
	 * 
	 * @return bool If https:// -> returns true
	 */
	private static function url_is_https(string $url) : bool {
		$search_result = strpos($url, 'https');
		if ($search_result === 0) {
			return true;
		}
		return false;
	}



	/**
	 * Send a HTTP GET request
	 * 
	 * Made to be used similarly to requests.get in Python
	 * 
	 * @param string $url             URL
	 * @param array  $headers         Custom request headers
	 * @param bool   $allow_redirects Whether or not to follow 30x redirects
	 * @param bool   $verify          Whether or not to verify certificate for HTTPS requests
	 *                                Note: Won't allways apply, see Requests::curl_base
	 * 
	 * @return Response Response object
	 */
	public static function GET(string $url, array $headers = null, bool $allow_redirects = true, bool $verify = true) : Response {
		$use_headers = [];
		if ($headers !== null) {
			$use_headers = $headers;
		}
		$result = self::get_base('GET', $url, $use_headers, $allow_redirects, $verify);
		return new Response($result['headers'], $result['status_code'], $result['body'], $url);
	}



	/**
	 * Send a HTTP POST request
	 * 
	 * Made to be used similarly to requests.post in Python
	 * 
	 * @param string $url             URL (duh)
	 * @param mixed  $data            Data in POST body. Assoc. array if form, (almost) anything if JSON, null if nothing
	 * @param array  $headers         Custom request headers
	 * @param bool   $allow_redirects Whether or not to follow 30x redirects
	 * @param bool   $verify          Whether or not to verify certificate for HTTPS requests
	 *                                Note: Won't allways apply, see Requests::curl_base
	 * 
	 * @return Response Response object
	 */
	public static function POST(string $url, $data = null, array $headers = null, bool $allow_redirects = true, bool $verify = true) : Response {
		$use_headers = [];
		if ($headers !== null) {
			$use_headers = $headers;
		}
		$result = self::post_base('POST', $url, $data, $use_headers, $allow_redirects, $verify);
		return new Response($result['headers'], $result['status_code'], $result['body'], $url);
	}



	/**
	 * Send a HTTP PUT request
	 * 
	 * Made to be used similarly to requests.put in Python
	 * 
	 * @param string $url             URL (duh)
	 * @param mixed  $data            Data in POST body. Assoc. array if form, (almost) anything if JSON, null if nothing
	 * @param array  $headers         Custom request headers
	 * @param bool   $allow_redirects Whether or not to follow 30x redirects
	 * @param bool   $verify          Whether or not to verify certificate for HTTPS requests
	 *                                Note: Won't allways apply, see Requests::curl_base
	 * 
	 * @return Response Response object
	 */
	public static function PUT(string $url, $data = null, array $headers = null, bool $allow_redirects = true, bool $verify = true) : Response {
		$use_headers = [];
		if ($headers !== null) {
			$use_headers = $headers;
		}
		$result = self::post_base('PUT', $url, $data, $use_headers, $allow_redirects, $verify);
		return new Response($result['headers'], $result['status_code'], $result['body'], $url);
	}



	/**
	 * Send a HTTP DELETE request
	 * 
	 * Made to be used similarly to requests.delete in Python
	 * 
	 * @param string $url             URL
	 * @param array  $headers         Custom request headers
	 * @param bool   $allow_redirects Whether or not to follow 30x redirects
	 * @param bool   $verify          Whether or not to verify certificate for HTTPS requests
	 *                                Note: Won't allways apply, see Requests::curl_base
	 * 
	 * @return Response Response object
	 */
	public static function DELETE(string $url, array $headers = null, bool $allow_redirects = true, bool $verify = true) : Response {
		$use_headers = [];
		if ($headers !== null) {
			$use_headers = $headers;
		}
		$result = self::get_base('DELETE', $url, $use_headers, $allow_redirects, $verify);
		return new Response($result['headers'], $result['status_code'], $result['body'], $url);
	}


}





class Response {


	public	$headers;
	public	$url;
	public	$status_code;
	public	$ok;
	public	$text;
	public	$content;
	public	$is_redirect;
	public	$is_permanent_redirect;
	public	$next;


	/**
	 * Constructor
	 * 
	 * @param array  $headers     Response headers as an associative array
	 * @param int    $status_code HTTP status code
	 * @param string $body        Response body
	 * @param string $url         The URL the request was sent for
	 */
	public function __construct(array $headers, int $status_code, string $body, string $url) {
		$this->headers               = $headers;
		$this->url                   = $url;
		$this->status_code           = $status_code;
		$this->text                  = $body;
		$this->content               = $body;
		$this->is_redirect           = false;
		$this->is_permanent_redirect = false;
		$this->next                  = null;
		if ($this->status_code >= 400 && $this->status_code <= 600) {
			$this->ok = false;
		}
		else {
			$this->ok = true;
		}
		if ($this->status_code == 301 || $this->status_code == 302 || $this->status_code == 303 || $this->status_code == 307 || $this->status_code == 308) {
			$this->is_redirect = true;
			$this->next = $this->headers['location'];
			if ($this->status_code == 301 || $this->status_code == 308) {
				$this->is_permanent_redirect = true;
			}
		}
	}



	/**
	 * Interpret and decode the response body as JSON
	 * 
	 * @throws JsonException If the response body is not valid JSON
	 * 
	 * @return mixed JSON
	 */
	public function json() {
		return json_decode($this->text, false, 512, JSON_THROW_ON_ERROR);
	}



	/**
	 * TODO	// Not very important for my current use
	 */
	// public function close() {
	// 	
	// }


}

?>