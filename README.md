# PHP requests
User friendly HTTP requests library. Heavily inspired by Pythons requests package, and implemented as a wrapper around cURL.

I am aware of [Requests for PHP](https://github.com/WordPress/Requests), which is an excellent library. I made this instead however both as a learning project, and also because I wanted a single-file library which can be included in an existing project with maximum ease, regardless (almost) of environment, and without the need for composer.

Because it's made to fulfil my own needs in my own projects, this library is both "completed" and "incomplete" at the same time, and will quite likely never be "properly" completed (unless the requirements of my personal projects change).

## Currently implemented:
* GET
* POST
* PUT
* DELETE
* Response object
* Proxy (atleast SOCKS5)
* Handling of redirects (autofollow, or not (but save info about permanent/not permanent, and next (location)))
* Works with response content in both binary and text

## Current limitations
* Certificate verification will not work out of the box in a default PHP setup.

All of these are on the todo list, but I don't consider it necessary to implement them as of now:
* No support for basic authentication (can be done manually by setting request headers)
* No support for cookies (can be done manually by setting request headers)
* Specifying cert not implemented

Currently the members `text` and `content` of the Response object will have the same content. In future versions, `text` will most likely be removed.

The reason why both of these exists, is due to how the response objects in Pythons requests package functions. There, the `text` member contains a string, readily decoded with the proper encoding method, while the `content` member contains the raw binary data, usually just an undecoded string, but in case a request was sent for a binary file (like an image) the `content` member will contain the raw file data as the `bytes` file type.

In PHP, this is unnecessary, due to how string encoding and bytes as strings are handled. Both `text` and `content` may be used with no additional checks regardless if the file requested is text or binary.

## Usage
Put requests.php into your project, and include/use/require it where you wish. Call the public Requests functions (`Requests::GET`, `Requests::POST`, `Requests::PUT`, `Requests::DELETE`) to use.

If you wish to verify certificates when making HTTPS requests, change this line at the very top in the Requests class:
```php
private const do_verification = false;
```
to
```php
private const do_verification = true;
```
and also add a path to the certificate to use on the line beneath:
```php
private const cert_path = '/path/to/cacert.pem';
```
This will likely require you to install certificates for PHP to use. [Relevant stackoverflow](https://stackoverflow.com/questions/28858351/php-ssl-certificate-error-unable-to-get-local-issuer-certificate).

### Functions and parameters
The `Requests` class contain these four public static functions:
```php
/**
 * Send a HTTP GET request
 * 
 * @param string $url             URL
 * @param array  $headers         Custom request headers
 * @param array  $proxies         Proxy server to use, format: ['http' => '<server URI>']
 * @param bool   $allow_redirects Whether or not to follow 30x redirects
 * @param bool   $verify          Whether or not to verify certificate for HTTPS requests
 *                                Note: Won't allways apply, see Requests::curl_base
 * 
 * @return Response Response object
 */
public static function GET(string $url, array $headers = null, array $proxies = null, bool $allow_redirects = true, bool $verify = true) : Response
```
```php
/**
 * Send a HTTP POST request
 * 
 * @param string $url             URL
 * @param mixed  $data            Data in POST body. Assoc. array if form, (almost) anything if JSON, null if nothing
 * @param array  $headers         Custom request headers
 * @param array  $proxies         Proxy server to use, format: ['http' => '<server URI>']
 * @param bool   $allow_redirects Whether or not to follow 30x redirects
 * @param bool   $verify          Whether or not to verify certificate for HTTPS requests
 *                                Note: Won't allways apply, see Requests::curl_base
 * 
 * @return Response Response object
 */
public static function POST(string $url, $data = null, array $headers = null, array $proxies = null, bool $allow_redirects = true, bool $verify = true) : Response
```
```php
/**
 * Send a HTTP PUT request
 * 
 * @param string $url             URL
 * @param mixed  $data            Data in POST body. Assoc. array if form, (almost) anything if JSON, null if nothing
 * @param array  $headers         Custom request headers
 * @param array  $proxies         Proxy server to use, format: ['http' => '<server URI>']
 * @param bool   $allow_redirects Whether or not to follow 30x redirects
 * @param bool   $verify          Whether or not to verify certificate for HTTPS requests
 *                                Note: Won't allways apply, see Requests::curl_base
 * 
 * @return Response Response object
 */
public static function PUT(string $url, $data = null, array $headers = null, array $proxies = null, bool $allow_redirects = true, bool $verify = true) : Response
```
```php
/**
 * Send a HTTP DELETE request
 * 
 * @param string $url             URL
 * @param array  $headers         Custom request headers
 * @param array  $proxies         Proxy server to use, format: ['http' => '<server URI>']
 * @param bool   $allow_redirects Whether or not to follow 30x redirects
 * @param bool   $verify          Whether or not to verify certificate for HTTPS requests
 *                                Note: Won't allways apply, see Requests::curl_base
 * 
 * @return Response Response object
 */
public static function DELETE(string $url, array $headers = null, array $proxies = null, bool $allow_redirects = true, bool $verify = true) : Response
```
The `Response` class contain these members and this one public method:
```php
class Response {
    public    $headers;                // The response headers as an associative array
    public    $url;                    // The URL the request was sent for
    public    $status_code;            // HTTP status code
    public    $ok;                     // false if status is a 40x or 50x code, true otherwise
    public    $text;                   // Response body
    public    $content;                // Same as text
    public    $is_redirect;            // true if 30x redirect, false otherwise
    public    $is_permanent_redirect;  // true if HTTP 301 or 308, false otherwise
    public    $next;                   // If redirect, will contain the value of Location header as a string, null otherwise
```
```php
/**
 * Interpret and decode the response body as JSON
 * 
 * @throws JsonException If the response body is not valid JSON
 * 
 * @return mixed JSON
 */
public function json()
```

### Examples
```php
// Simple GET request
$response = Requests::GET('https://github.com/');
```
```php
// GET request with custom headers
$response = Requests::GET('https://github.com/', [
    'Accept'      => 'application/json',
    'User-Agent'  => 'A potato'
]);
```
```php
// Get response content as JSON
$response = Requests::GET('https://github.com/manifest.json');
var_dump($response->json());
/*
object(stdClass)#2 (5) {
  ["name"]=>
  string(6) "GitHub"
  ["short_name"]=>
  string(6) "GitHub"
  ["icons"]=>
  array(11) {
  ... [CUT]
*/
```
```php
// POST request with data as form
$response = Requests::POST('https://github.com/', [
    'foo'      => 'bar',
    'theword'  => 'a bird'
]);
```
```php
// POST request with data as form and custom headers
$response = Requests::POST('https://github.com/', [
    'foo'      => 'bar',
    'theword'  => 'a bird'
], [
    'Accept'      => 'application/json',
    'User-Agent'  => 'A potato'
]);
```
```php
// PUT request with data as JSON
// (all you can do with Requests::POST, can also be done with Requests::PUT, and vica versa)
$response = Requests::PUT('https://github.com/', [
    [
        'foo'      => 'bar',
        'theword'  => 'a bird',
        'thecake'  => false,
        'array'    => [
            'a', 'b', 'c', 1, 2, 3, null, true, false, 3.14, []
        ]
    ]
], [
    'Content-Type' => 'application/json'
]);
/*
Request body will look like this:
[{"foo":"bar","theword":"a bird","thecake":false,"array":["a","b","c",1,2,3,null,true,false,3.14,[]]}]
*/
```
```php
// Basic DELETE request
$response = Requests::DELETE('https://github.com/');
```
```php
// GET request through local SOCKS5 proxy
$response = Requests::GET('https://github.com/', null, ['http' => 'socks5://localhost:8080']);
```
```php
$response = Requests::GET('https://github.com/');

$response->status_code;
// 200

$response->ok;
// true

$response->is_redirect;
// false

$response->text;
$response->content;
// HTML/page text
```
```php
$response = Requests::GET('https://wikipedia.org/', null, false);

$response->status_code;
// 301

$response->is_redirect;
// true

$response->is_permanent_redirect;
// true

$response->next;
// 'https://www.wikipedia.org'
```
```
// var_dump of a response object
object(Response)#1 (9) {
  ["headers"]=>
  array(18) {
    ["content-length"]=>
    string(1) "0"
    ["location"]=>
    string(19) "https://github.com/"
    ["server"]=>
    string(10) "GitHub.com"
    ["date"]=>
    string(29) "Sat, 10 Apr 2021 16:37:36 GMT"
    ["content-type"]=>
    string(24) "text/html; charset=utf-8"
    ["vary"]=>
    string(66) "X-PJAX, Accept-Language, Accept-Encoding, Accept, X-Requested-With"
    ["etag"]=>
    string(36) "W/"[CUT]""
    ["cache-control"]=>
    string(35) "max-age=0, private, must-revalidate"
    ["strict-transport-security"]=>
    string(44) "max-age=31536000; includeSubdomains; preload"
    ["x-frame-options"]=>
    string(4) "deny"
    ["x-content-type-options"]=>
    string(7) "nosniff"
    ["x-xss-protection"]=>
    string(1) "0"
    ["referrer-policy"]=>
    string(57) "origin-when-cross-origin, strict-origin-when-cross-origin"
    ["expect-ct"]=>
    string(76) "max-age=2592000, report-uri="https://api.github.com/_private/browser/errors""
    ["content-security-policy"]=>
    string(1087) "default-src 'none'; base-uri 'self'; block-all-mixed-content; connect-src 'self' ... [CUT]"
    ["set-cookie"]=>
    string(110) "logged_in=no; Path=/; Domain=github.com; Expires=Sun, 10 Apr 2022 16:37:44 GMT; HttpOnly; Secure; SameSite=Lax"
    ["accept-ranges"]=>
    string(5) "bytes"
    ["x-github-request-id"]=>
    string(34) "[CUT]"
  }
  ["url"]=>
  string(17) "https://github.com"
  ["status_code"]=>
  int(200)
  ["ok"]=>
  bool(true)
  ["text"]=>
  string(212535) "

<!DOCTYPE html>
<html lang="en"  class="html-fluid">
  <head>
    ... [CUT]
"
  ["content"]=>
  string(212535) "

<!DOCTYPE html>
<html lang="en"  class="html-fluid">
  <head>
    ... [CUT]
"
  ["is_redirect"]=>
  bool(false)
  ["is_permanent_redirect"]=>
  bool(false)
  ["next"]=>
  NULL
}
```