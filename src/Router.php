<?php

namespace s22h;

class Router {
	protected $routes = array();
	protected $headRequest = false;

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

	public function run() {
		if (array_key_exists('q', $_GET)) {
			$path = $_GET['q'];
		}
		else {
			$path = '/';
		}

		$path = preg_replace('#//+#', '/', $path);

		return $this->runPath($path);
	}

	public function runPath($path) {
		$method = $_SERVER['REQUEST_METHOD'];

		if ($method == 'HEAD') {
			$this->headRequest = true;
			$method = 'GET';
		}

		if (!array_key_exists($method, $this->routes)) {
			http_response_code(405);
			echo '<p>"' . $method . '" is not an accepted request method.</p>';
			echo '<p>Status code: 405</p>';
			return false;
		}

		$paths = $this->routes[$method];
		$len = strlen($path);
		$pos = 0;

		foreach ($paths as $route => $callback) {
			if (strpos($route, '<') === false) {
				if ($path == $route) {
					return $this->invokeCallback($callback, $route);
				}
			}
			else {
				$valid = true;
				$params = [];

				$pattern = preg_quote($route, '/');
				$pattern = preg_replace('/\\\<\\\!(.+?)\\\>/', '(.+?)', $pattern);
				$pattern = preg_replace('/\\\<(.+?)\\\>/', '([^\/]+?)', $pattern);

				if (!preg_match('/^' . $pattern . '$/', $path, $matches)) {
					$valid = false;
					continue;
				}

				// remove complete pattern
				array_shift($matches);

				preg_match_all("/\<!?(.+?)\>/", $route, $keys);

				foreach ($matches as $idx => $match) {
					$params[$keys[1][$idx]] = $match;
				}

				if ($valid) {
					return $this->invokeCallback($callback, $route, $params);
				}
			}
		}

		http_response_code(404);
		echo '<p>The page you are looking for could not be found.</p>';
		echo '<p>Status code: 404</p>';

		return false;
	}

	protected function invokeCallback($callback, $route, $params = []) {
		if (!is_callable($callback)) {
			if (is_array($callback)) {
				return $callback[0]->{$callback[1]}($route, $params);
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

			return $callback->{$method}($route, $params);
		}

		return $callback($route, $params);
    }
}
