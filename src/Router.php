<?php

namespace s22h;

/**
* A request routing class.
* 
* @author s22h <o_o@trollsoft.net>
* @since 1.0
*/
class Router {
	const Get = 'GET';
	const Post = 'POST';
	const Put = 'PUT';
	const Delete = 'DELETE';
	const Head = 'HEAD';

	protected $routes = array();
	protected $headRequest = false;

	/**
	* Set a new route for a GET request.
	*
	* @param $uri The request URI that should trigger the callback.
	* @param $callback A callable function or a string containing Class.method to be instantiated.
	* 
	* @see Router::addRoute()
	*/
	public function get($uri, $callback) {
		$this->addRoute(static::Get, $uri, $callback);
	}
		
	/**
	* Set a new route for a POST request.
	*
	* @param $uri The request URI that should trigger the callback.
	* @param $callback A callable function or a string containing Class.method to be instantiated.
	* 
	* @see Router::addRoute()
	*/
	public function post($uri, $callback) {
		$this->addRoute(static::Post, $uri, $callback);
	}

	/**
	* Set a new route for the given request method.
	* 
	* @param $method The request method this route should apply for.
	* @param $uri The request URI that should trigger the callback.
	* @param $callback A callable function or a string containing Class.method to be instantiated.
	* 
	* @see Router::get()
	* @see Router::post()
	*/
	public function addRoute($method, $uri, $callback) {
		if (!is_array($uri)) {
			$uri = [$uri];
		}

		$method = strtoupper($method);
		
		foreach ($uri as $u) {
			if (!array_key_exists($method, $this->routes)) {
				$this->routes[$method] = array();
			}

			$this->routes[$method][$u] = $callback;
		}
	}

	/**
	* Run the router with the default path ($_SERVER['REQUEST_URI']).
	*
	* @see Router::runUri()
	*/
	public function run() {
		$base = str_replace(DIRECTORY_SEPARATOR, '/', dirname($_SERVER['SCRIPT_NAME']));
		$path = $_SERVER['REQUEST_URI'];

		if ($base != '.' && $base != '/') {
			$path = substr($path, strlen($base));
		}

		return $this->runUri($path);
	}
		
	/**
	* Run the router with the given URI.
	* 
	* @param $uri The URI that should be matched against the routes.
	* @see Router::run()
	*/
	public function runUri($uri) {
		$method = $_SERVER['REQUEST_METHOD'];

		if ($method == static::Head) {
			$this->headRequest = true;
			$method = static::Get;
		}

		if (!array_key_exists($method, $this->routes)) {
			http_response_code(405);
			echo '<p>"' . $method . '" is not an accepted request method.</p>';
			echo '<p>Status code: 405</p>';

			return false;
		}

		$routes = $this->routes[$method];
		$len = strlen($uri);
		$pos = 0;
		$response = null;

		foreach ($routes as $route => $callback) {
			if (strpos($route, '<') === false) {
				if ($uri == $route) {
					$response = $this->invokeCallback($method, $uri, $callback);
					break;
				}
			}
			else {
				$valid = true;
				$args = [];
				$pattern = preg_quote($route, '/');
				$pattern = preg_replace('/\\\<\\\!(.+?)\\\>/', '(.+?)', $pattern);
				$pattern = preg_replace('/\\\<(.+?)\\\>/', '([^\/]+?)', $pattern);

				if (!preg_match('/^' . $pattern . '$/', $uri, $matches)) {
					$valid = false;
					continue;
				}

				// remove complete pattern
				array_shift($matches);
				preg_match_all("/\<!?(.+?)\>/", $route, $keys);

				foreach ($matches as $idx => $match) {
					$args[$keys[1][$idx]] = $match;
				}

				if ($valid) {
					$response = $this->invokeCallback($method, $uri, $callback, $args);
					break;
				}
			}
		}

		if (($response instanceof \Psr\Http\Message\ResponseInterface) == false) {
			http_response_code(404);
			echo '<p>The page you are looking for could not be found.</p>';
			echo '<p>Status code: 404</p>';

			return false;
		}

		echo $response->getBody();

		return true;
	}

	protected function invokeCallback($method, $uri, $callback, $args = []) {
		$request = new \GuzzleHttp\Psr7\Request($method, $uri);
		$response = new \GuzzleHttp\Psr7\Response;

		if (!is_callable($callback)) {
			if (is_array($callback)) {
				return $callback[0]->{$callback[1]}($request, $response, $args);
			}

			$parts = explode('.', $callback);
			$class = $parts[0];
			$method = $parts[1];

			if (!class_exists($class)) {
				http_response_code(404);
				echo '<code>' . $class . '</code> does not exist';

				return false;
			}

			$callback = new $class;

			if (!method_exists($callback, $method)) {
				http_response_code(404);
				echo '<code>' . $class . '::' . $method . '</code> does not exist';

				return false;
			}

			return $callback->{$method}($request, $response, $args);
		}

		return $callback($request, $response, $args);
	}
}

