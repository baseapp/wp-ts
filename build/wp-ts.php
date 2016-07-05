<?php
define('PASSWORD', 'root');

/* No Editing Below this Line */

define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', false);
define('WP_TS',true);

define('TS_ABSURL',$_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'].dirname($_SERVER['REQUEST_URI']))."/";

$absdir = dirname(__FILE__);
$absdir = str_replace('\\','/',$absdir);
define('TS_ABSPATH', $absdir. '/');

if(is_file(TS_ABSPATH.'wp_ts_debug')){
    // Local Debug
    define('TS_REMOTE_URL', 'http://127.0.0.1/wp-ts/');
    define('TS_DEBUG',true);
    ini_set('display_errors',true);
    error_reporting(E_ALL);

} else {
    define('TS_REMOTE_URL', 'https://raw.githubusercontent.com/baseapp/wp-ts/master/');
    define('TS_DEBUG',false);
}

define('TS_WPINC', 'wp-includes/');

$dir = sha1(PASSWORD + TS_WPINC);

define('TS_PLUGIN_DIR', TS_ABSPATH . 'wp-content/uploads/wp-ts/' . $dir . '/');

if (!is_dir(TS_PLUGIN_DIR))
    mkdir(TS_PLUGIN_DIR, 0777, true);

session_start();
$idletime = 3000; //after 300 seconds the user gets logged out
if (isset($_SESSION['timestamp']) && ( time() - $_SESSION['timestamp'] > $idletime) ) {
    session_destroy();
    session_unset();
} else {
    $_SESSION['timestamp'] = time();
}



class Auth {

    protected static $key = 'najdhfuBNMxBHgYdg';

    public static function isLoggedIn()
    {
        self::startSession();
        if(isset($_SESSION['wptauthenticated']) && $_SESSION['wptauthenticated'])
            return true;
        else
            return false;
    }

    public static function logIn($pass)
    {
        if(PASSWORD===$pass)
        {
            self::startSession();
            $_SESSION['wptauthenticated'] = true;
            $_SESSION['fingerprint'] = md5( self::$key. $_SERVER['HTTP_USER_AGENT']. session_id());
            return true;
        } else {
            return false;
        }
    }

    public static function logOut()
    {
        self::startSession();
        unset($_SESSION['wptauthenticated']);
        unset($_SESSION['fingerprint']);
    }

    public static function startSession() {
        if (session_id() === '') {
            session_start();
            $_SESSION['timestamp']=time();
        }
    }
}



# (c) Chris O'Hara <cohara87@gmail.com> (MIT License)
# http://github.com/chriso/klein.php

$__routes = array();
$__namespace = null;

// Add a route callback
function respond($method, $route = '*', $callback = null)
{
    global $__routes, $__namespace;

    $args = func_get_args();
    $callback = array_pop($args);
    $route = array_pop($args);
    $method = array_pop($args);

    if (null === $route) {
        $route = '*';
    }

    // only consider a request to be matched when not using matchall
    $count_match = ($route !== '*');

    if ($__namespace && $route[0] === '@' || ($route[0] === '!' && $route[1] === '@')) {
        if ($route[0] === '!') {
            $negate = true;
            $route = substr($route, 2);
        } else {
            $negate = false;
            $route = substr($route, 1);
        }

        // regex anchored to front of string
        if ($route[0] === '^') {
            $route = substr($route, 1);
        } else {
            $route = '.*' . $route;
        }

        if ($negate) {
            $route = '@^' . $__namespace . '(?!' . $route . ')';
        } else {
            $route = '@^' . $__namespace . $route;
        }
    } elseif ($__namespace && ('*' === $route)) {
        // empty route with namespace is a match-all
        $route = '@^' . $__namespace . '(/|$)';
    } else {
        $route = $__namespace . $route;
    }

    $__routes[] = array($method, $route, $callback, $count_match);
    return $callback;
}

// Each route defined inside $routes will be in the $namespace
function with($namespace, $routes)
{
    global $__namespace;
    $previous = $__namespace;
    $__namespace .= $namespace;
    if (is_callable($routes)) {
        $routes();
    } else {
        require $routes;
    }
    $__namespace = $previous;
}

function startSession()
{
    if (session_id() === '') {
        session_start();
        $_SESSION['timestamp']=time();
    }
}

// Dispatch the request to the approriate route(s)
function dispatch($uri = null, $req_method = null, array $params = null, $capture = false)
{
    global $__routes;

    // Pass $request, $response, and a blank object for sharing scope through each callback
    $request  = new TsRequest;
    $response = new TsResponse;
    $app      = new TsApp;

    // Get/parse the request URI and method
    if (null === $uri) {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    }
    if (false !== strpos($uri, '?')) {
        $uri = strstr($uri, '?', true);
    }
    if (null === $req_method) {
        $req_method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

        // For legacy servers, override the HTTP method with the X-HTTP-Method-Override
        // header or _method parameter
        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $req_method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
        } elseif (isset($_REQUEST['_method'])) {
            $req_method = $_REQUEST['_method'];
        }
    }

    // Force request_order to be GP
    // http://www.mail-archive.com/internals@lists.php.net/msg33119.html
    $_REQUEST = array_merge($_GET, $_POST);
    if (null !== $params) {
        $_REQUEST = array_merge($_REQUEST, $params);
    }

    $matched = 0;
    $methods_matched = array();
    $apc = function_exists('apc_fetch');

    ob_start();

    foreach ($__routes as $handler) {
        list($method, $_route, $callback, $count_match) = $handler;

        $method_match = null;
        // Was a method specified? If so, check it against the current request method
        if (is_array($method)) {
            foreach ($method as $test) {
                if (strcasecmp($req_method, $test) === 0) {
                    $method_match = true;
                }
            }
            if (null === $method_match) {
                $method_match = false;
            }
        } elseif (null !== $method && strcasecmp($req_method, $method) !== 0) {
            $method_match = false;
        } elseif (null !== $method && strcasecmp($req_method, $method) === 0) {
            $method_match = true;
        }

       // If the method was matched or if it wasn't even passed (in the route callback)
        $possible_match = is_null($method_match) || $method_match;

        // ! is used to negate a match
        if (isset($_route[0]) && $_route[0] === '!') {
            $negate = true;
            $i = 1;
        } else {
            $negate = false;
            $i = 0;
        }

        // Check for a wildcard (match all)
        if ($_route === '*') {
            $match = true;

        // Easily handle 404's
        } elseif ($_route === '404' && !$matched && count($methods_matched) <= 0) {
            try {
                call_user_func($callback, $request, $response, $app, $matched, $methods_matched);
            } catch (Exception $e) {
                $response->error($e);
            }

            ++$matched;
            continue;

        // Easily handle 405's
        } elseif ($_route === '405' && !$matched && count($methods_matched) > 0) {
            try {
                call_user_func($callback, $request, $response, $app, $matched, $methods_matched);
            } catch (Exception $e) {
                $response->error($e);
            }

            ++$matched;
            continue;

        // @ is used to specify custom regex
        } elseif (isset($_route[$i]) && $_route[$i] === '@') {
            $match = preg_match('`' . substr($_route, $i + 1) . '`', $uri, $params);

        // Compiling and matching regular expressions is relatively
        // expensive, so try and match by a substring first
        } else {
            $route = null;
            $regex = false;
            $j = 0;
            $n = isset($_route[$i]) ? $_route[$i] : null;

            // Find the longest non-regex substring and match it against the URI
            while (true) {
                if (!isset($_route[$i])) {
                    break;
                } elseif (false === $regex) {
                    $c = $n;
                    $regex = $c === '[' || $c === '(' || $c === '.';
                    if (false === $regex && false !== isset($_route[$i+1])) {
                        $n = $_route[$i + 1];
                        $regex = $n === '?' || $n === '+' || $n === '*' || $n === '{';
                    }
                    if (false === $regex && $c !== '/' && (!isset($uri[$j]) || $c !== $uri[$j])) {
                        continue 2;
                    }
                    $j++;
                }
                $route .= $_route[$i++];
            }

            // Check if there's a cached regex string
            if (false !== $apc) {
                $regex = apc_fetch("route:$route");
                if (false === $regex) {
                    $regex = compile_route($route);
                    apc_store("route:$route", $regex);
                }
            } else {
                $regex = compile_route($route);
            }

            $match = preg_match($regex, $uri, $params);
        }

        if (isset($match) && $match ^ $negate) {
             // Keep track of possibly matched methods
             $methods_matched = array_merge($methods_matched, (array) $method);
             $methods_matched = array_filter($methods_matched);
             $methods_matched = array_unique($methods_matched);

            if ($possible_match) {
                if (null !== $params) {
                    $_REQUEST = array_merge($_REQUEST, $params);
                }
                try {
                    call_user_func($callback, $request, $response, $app, $matched, $methods_matched);
                } catch (Exception $e) {
                    $response->error($e);
                }
                if ($_route !== '*') {
                    $count_match && ++$matched;
                }
            }
        }
    }

    if (!$matched && count($methods_matched) > 0) {
        $response->code(405);
        $response->header('Allow', implode(', ', $methods_matched));
    } elseif (!$matched) {
        $response->code(404);
    }

    if ($capture) {
        return ob_get_clean();
    } elseif ($response->chunked) {
        $response->chunk();
    } else {
        ob_end_flush();
    }
}

// Compiles a route string to a regular expression
function compile_route($route)
{
    if (preg_match_all('`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`', $route, $matches, PREG_SET_ORDER)) {
        $match_types = array(
            'i'  => '[0-9]++',
            'a'  => '[0-9A-Za-z]++',
            'h'  => '[0-9A-Fa-f]++',
            '*'  => '.+?',
            '**' => '.++',
            ''   => '[^/]+?'
        );
        foreach ($matches as $match) {
            list($block, $pre, $type, $param, $optional) = $match;

            if (isset($match_types[$type])) {
                $type = $match_types[$type];
            }
            if ($pre === '.') {
                $pre = '\.';
            }
            // Older versions of PCRE require the 'P' in (?P<named>)
            $pattern = '(?:'
                     . ($pre !== '' ? $pre : null)
                     . '('
                     . ($param !== '' ? "?P<$param>" : null)
                     . $type
                     . '))'
                     . ($optional !== '' ? '?' : null);

            $route = str_replace($block, $pattern, $route);
        }
    }
    return "`^$route$`";
}

class TsRequest
{

    protected $_id = null;

    // HTTP headers helper
    static $_headers = null;

    protected $_body = null;

    // Returns all parameters (GET, POST, named) that match the mask
    public function params($mask = null)
    {
        $params = $_REQUEST;
        if (null !== $mask) {
            if (!is_array($mask)) {
                $mask = func_get_args();
            }
            $params = array_intersect_key($params, array_flip($mask));
            // Make sure each key in $mask has at least a null value
            foreach ($mask as $key) {
                if (!isset($params[$key])) {
                    $params[$key] = null;
                }
            }
        }
        return $params;
    }

    // Return a request parameter, or $default if it doesn't exist
    public function param($key, $default = null)
    {
        return isset($_REQUEST[$key]) && $_REQUEST[$key] !== '' ? $_REQUEST[$key] : $default;
    }

    public function __isset($param)
    {
        return isset($_REQUEST[$param]);
    }

    public function __get($param)
    {
        return isset($_REQUEST[$param]) ? $_REQUEST[$param] : null;
    }

    public function __set($param, $value)
    {
        $_REQUEST[$param] = $value;
    }

    public function __unset($param)
    {
        unset($_REQUEST[$param]);
    }

    // Is the request secure? If $required then redirect to the secure version of the URL
    public function isSecure($required = false)
    {
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'];
        if (!$secure && $required) {
            $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            self::$_headers->header('Location: ' . $url);
        }
        return $secure;
    }

    // Gets a request header
    public function header($key, $default = null)
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return isset($_SERVER[$key]) ? $_SERVER[$key] : $default;
    }

    // Gets a request cookie
    public function cookie($key, $default = null)
    {
        return isset($_COOKIE[$key]) ? $_COOKIE[$key] : $default;
    }

    // Gets the request method, or checks it against $is - e.g. method('post') => true
    public function method($is = null)
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        if (null !== $is) {
            return strcasecmp($method, $is) === 0;
        }
        return $method;
    }

    // Start a validator chain for the specified parameter
    public function validate($param, $err = null)
    {
        return new TsValidator($this->param($param), $err);
    }

    // Gets a unique ID for the request
    public function id()
    {
        if (null === $this->_id) {
            $this->_id = sha1(mt_rand() . microtime(true) . mt_rand());
        }
        return $this->_id;
    }

    // Gets a session variable associated with the request
    public function session($key, $default = null)
    {
        startSession();
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }

    // Gets the request IP address
    public function ip()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    }

    // Gets the request user agent
    public function userAgent()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
    }

    // Gets the request URI
    public function uri()
    {
        return isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    }

    // Gets the request body
    public function body()
    {
        if (null === $this->_body) {
            $this->_body = @file_get_contents('php://input');
        }
        return $this->_body;
    }
}

class TsResponse extends StdClass
{

    public $chunked = false;
    protected $_errorCallbacks = array();
    protected $_layout = null;
    protected $_view = null;
    protected $_code = 200;
    public $data;

    static $_headers = null;

    public function __construct()
    {
        $this->data = new JsonOutput();
    }

    // Enable response chunking. See: http://bit.ly/hg3gHb
    public function chunk($str = null)
    {
        if (false === $this->chunked) {
            $this->chunked = true;
            self::$_headers->header('Transfer-encoding: chunked');
            flush();
        }
        if (null !== $str) {
            printf("%x\r\n", strlen($str));
            echo "$str\r\n";
            flush();
        } elseif (($ob_length = ob_get_length()) > 0) {
            printf("%x\r\n", $ob_length);
            ob_flush();
            echo "\r\n";
            flush();
        }
    }

    // Sets a response header
    public function header($key, $value = null)
    {
        self::$_headers->header($key, $value);
    }

    // Sets a response cookie
    public function cookie(
        $key,
        $value = '',
        $expiry = null,
        $path = '/',
        $domain = null,
        $secure = false,
        $httponly = false
    ) {
        if (null === $expiry) {
            $expiry = time() + (3600 * 24 * 30);
        }
        return setcookie($key, $value, $expiry, $path, $domain, $secure, $httponly);
    }

    // Stores a flash message of $type
    public function flash($msg, $type = 'info', $params = null)
    {
        startSession();
        if (is_array($type)) {
            $params = $type;
            $type = 'info';
        }
        if (!isset($_SESSION['__flashes'])) {
            $_SESSION['__flashes'] = array($type => array());
        } elseif (!isset($_SESSION['__flashes'][$type])) {
            $_SESSION['__flashes'][$type] = array();
        }
        $_SESSION['__flashes'][$type][] = $this->markdown($msg, $params);
    }

    // Support basic markdown syntax
    public function markdown($str, $args = null)
    {
        $args = func_get_args();
        $md = array(
            '/\[([^\]]++)\]\(([^\)]++)\)/' => '<a href="$2">$1</a>',
            '/\*\*([^\*]++)\*\*/'          => '<strong>$1</strong>',
            '/\*([^\*]++)\*/'              => '<em>$1</em>'
        );
        $str = array_shift($args);
        if (is_array($args[0])) {
            $args = $args[0];
        }
        foreach ($args as &$arg) {
            $arg = htmlentities($arg, ENT_QUOTES);
        }
        return vsprintf(preg_replace(array_keys($md), $md, $str), $args);
    }

    // Tell the browser not to cache the response
    public function noCache()
    {
        $this->header("Pragma: no-cache");
        $this->header('Cache-Control: no-store, no-cache');
    }

    // Sends a file
    public function file($path, $filename = null, $mimetype = null)
    {
        $this->discard();
        $this->noCache();
        set_time_limit(1200);
        if (null === $filename) {
            $filename = basename($path);
        }
        if (null === $mimetype) {
            $mimetype = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);
        }
        $this->header('Content-type: ' . $mimetype);
        $this->header('Content-length: ' . filesize($path));
        $this->header('Content-Disposition: attachment; filename="'.$filename.'"');
        readfile($path);
    }

    // Sends an object as json or jsonp by providing the padding prefix
    public function json($object, $jsonp_prefix = null)
    {
        $this->discard(true);
        $this->noCache();
        set_time_limit(1200);
        $json = json_encode($object);
        if (null !== $jsonp_prefix) {
            $this->header('Content-Type: text/javascript'); // should ideally be application/json-p once adopted
            echo "$jsonp_prefix($json);";
        } else {
            $this->header('Content-Type: application/json');
            echo $json;
        }
    }
    // Send Form data in json form
    public function sendDataJson()
    {
        $this->discard(true);
        $this->noCache();
        set_time_limit(1200);
        $this->data->breadcrumb = getBreadcrumbs($_POST['link']);
        $this->data->flash = $this->flashes();
        $json = json_encode($this->data);
        $this->header('Content-Type: application/json');
        echo $json;
    }

    // Sends a HTTP response code
    public function code($code = null)
    {
        if (null !== $code) {
            $this->_code = $code;

            // Do we have the PHP 5.4 "http_response_code" function?
            if (function_exists('http_response_code')) {
                // Have PHP automatically create our HTTP Status header from our code
                http_response_code($code);
            } else {
                // Manually create the HTTP Status header
                $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
                $this->header("$protocol $code");
            }
        }
        return $this->_code;
    }

    // Redirects the request to another URL
    public function redirect($url, $code = 302, $exit_after_redirect = true)
    {
        $this->code($code);
        $this->header("Location: $url");
        if ($exit_after_redirect) {
            exit;
        }
    }

    // Redirects the request to the current URL
    public function refresh()
    {
        $this->redirect(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/');
    }

    // Redirects the request back to the referrer
    public function back()
    {
        /*if (isset($_SERVER['HTTP_REFERER'])) {
            $this->redirect($_SERVER['HTTP_REFERER']);
        }
        $this->refresh();*/
        $this->discard();
        $link = $_POST['link'];
        //unset($_POST);
        //unset($_GET);
        $_GET = array();
        $_POST = array();
        $_POST['link'] = $link;
        dispatch($link);
    }

    // Sets response properties/helpers
    public function set($key, $value = null)
    {
        if (!is_array($key)) {
            return $this->$key = $value;
        }
        foreach ($key as $k => $value) {
            $this->$k = $value;
        }
    }

    // Adds to or modifies the current query string
    public function query($key, $value = null)
    {
        $query = array();
        if (isset($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $query);
        }
        if (is_array($key)) {
            $query = array_merge($query, $key);
        } else {
            $query[$key] = $value;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        if (strpos($request_uri, '?') !== false) {
            $request_uri = strstr($request_uri, '?', true);
        }
        return $request_uri . (!empty($query) ? '?' . http_build_query($query) : null);
    }

    // Set the view layout
    public function layout($layout)
    {
        $this->_layout = $layout;
    }

    // Renders the current view
    public function yieldView()
    {
        require $this->_view;
    }

    // Renders a view + optional layout
    public function render($view, array $data = array())
    {
        $original_view = $this->_view;

        if (!empty($data)) {
            $this->set($data);
        }
        $this->_view = $view;
        if (null === $this->_layout) {
            $this->yieldView();
        } else {
            require $this->_layout;
        }
        if (false !== $this->chunked) {
            $this->chunk();
        }

        // restore state for parent render()
        $this->_view = $original_view;
    }

    // Renders a view without a layout
    public function partial($view, array $data = array())
    {
        $layout = $this->_layout;
        $this->_layout = null;
        $this->render($view, $data);
        $this->_layout = $layout;
    }

    // Sets a session variable
    public function session($key, $value = null)
    {
        startSession();
        return $_SESSION[$key] = $value;
    }

    // Adds an error callback to the stack of error handlers
    public function onError($callback)
    {
        $this->_errorCallbacks[] = $callback;
    }

    // Routes an exception through the error callbacks
    public function error(Exception $err)
    {
        $type = get_class($err);
        $msg = $err->getMessage();

        if (count($this->_errorCallbacks) > 0) {
            foreach (array_reverse($this->_errorCallbacks) as $callback) {
                if (is_callable($callback)) {
                    if ($callback($this, $msg, $type, $err)) {
                        return;
                    }
                } else {
                    $this->flash($err);
                    $this->redirect($callback);
                }
            }
        } else {
            $this->code(500);
            throw new ErrorException($err);
        }
    }

    // Returns an escaped request paramater
    public function param($param, $default = null)
    {
        return isset($_REQUEST[$param]) ?  htmlentities($_REQUEST[$param], ENT_QUOTES) : $default;
    }

    // Returns and clears all flashes of optional $type
    public function flashes($type = null)
    {
        startSession();
        if (!isset($_SESSION['__flashes'])) {
            return array();
        }
        if (null === $type) {
            $flashes = $_SESSION['__flashes'];
            unset($_SESSION['__flashes']);
        } elseif (null !== $type) {
            $flashes = array();
            if (isset($_SESSION['__flashes'][$type])) {
                $flashes = $_SESSION['__flashes'][$type];
                unset($_SESSION['__flashes'][$type]);
            }
        }
        return $flashes;
    }

    // Escapes a string
    public function escape($str)
    {
        return htmlentities($str, ENT_QUOTES);
    }

    // Discards the current output buffer and restarts it if passed a true boolean
    public function discard($restart_buffer = false)
    {
        $cleaned = ob_end_clean();

        if ($restart_buffer) {
            ob_start();
        }

        return $cleaned;
    }

    // Flushes the current output buffer
    public function flush()
    {
        ob_end_flush();
    }

    // Return the current output buffer as a string
    public function buffer()
    {
        return ob_get_contents();
    }

    // Dump a variable
    public function dump($obj)
    {
        if (is_array($obj) || is_object($obj)) {
            $obj = print_r($obj, true);
        }
        echo '<pre>' .  htmlentities($obj, ENT_QUOTES) . "</pre><br />\n";
    }

    // Allow callbacks to be assigned as properties and called like normal methods
    public function __call($method, $args)
    {
        if (!isset($this->$method) || !is_callable($this->$method)) {
            throw new ErrorException("Unknown method $method()");
        }
        $callback = $this->$method;
        switch (count($args)) {
            case 1:
                return $callback($args[0]);
            case 2:
                return $callback($args[0], $args[1]);
            case 3:
                return $callback($args[0], $args[1], $args[2]);
            case 4:
                return $callback($args[0], $args[1], $args[2], $args[3]);
            default:
                return call_user_func_array($callback, $args);
        }
    }
}

function addValidator($method, $callback)
{
    TsValidator::$_methods[strtolower($method)] = $callback;
}

class ValidatorException extends Exception
{
}

class TsValidator
{

    public static $_methods = array();

    protected $_str = null;
    protected $_err = null;

    // Sets up the validator chain with the string and optional error message
    public function __construct($str, $err = null)
    {
        $this->_str = $str;
        $this->_err = $err;
        if (empty(static::$_defaultAdded)) {
            static::addDefault();
        }
    }

    // Adds default validators on first use. See README for usage details
    public static function addDefault()
    {
        static::$_methods['null'] = function ($str) {
            return $str === null || $str === '';
        };
        static::$_methods['len'] = function ($str, $min, $max = null) {
            $len = strlen($str);
            return null === $max ? $len === $min : $len >= $min && $len <= $max;
        };
        static::$_methods['int'] = function ($str) {
            return (string)$str === ((string)(int)$str);
        };
        static::$_methods['float'] = function ($str) {
            return (string)$str === ((string)(float)$str);
        };
        static::$_methods['email'] = function ($str) {
            return filter_var($str, FILTER_VALIDATE_EMAIL) !== false;
        };
        static::$_methods['url'] = function ($str) {
            return filter_var($str, FILTER_VALIDATE_URL) !== false;
        };
        static::$_methods['ip'] = function ($str) {
            return filter_var($str, FILTER_VALIDATE_IP) !== false;
        };
        static::$_methods['alnum'] = function ($str) {
            return ctype_alnum($str);
        };
        static::$_methods['alpha'] = function ($str) {
            return ctype_alpha($str);
        };
        static::$_methods['contains'] = function ($str, $needle) {
            return strpos($str, $needle) !== false;
        };
        static::$_methods['regex'] = function ($str, $pattern) {
            return preg_match($pattern, $str);
        };
        static::$_methods['chars'] = function ($str, $chars) {
            return preg_match("`^[$chars]++$`i", $str);
        };
    }

    public function __call($method, $args)
    {
        $reverse = false;
        $validator = $method;
        $method_substr = substr($method, 0, 2);

        if ($method_substr === 'is') {       // is<$validator>()
            $validator = substr($method, 2);
        } elseif ($method_substr === 'no') { // not<$validator>()
            $validator = substr($method, 3);
            $reverse = true;
        }
        $validator = strtolower($validator);

        if (!$validator || !isset(static::$_methods[$validator])) {
            throw new ErrorException("Unknown method $method()");
        }
        $validator = static::$_methods[$validator];
        array_unshift($args, $this->_str);

        switch (count($args)) {
            case 1:
                $result = $validator($args[0]);
                break;
            case 2:
                $result = $validator($args[0], $args[1]);
                break;
            case 3:
                $result = $validator($args[0], $args[1], $args[2]);
                break;
            case 4:
                $result = $validator($args[0], $args[1], $args[2], $args[3]);
                break;
            default:
                $result = call_user_func_array($validator, $args);
                break;
        }

        $result = (bool)($result ^ $reverse);
        if (false === $this->_err) {
            return $result;
        } elseif (false === $result) {
            throw new ValidatorException($this->_err);
        }
        return $this;
    }
}

class TsApp
{

    protected $services = array();

    // Check for a lazy service
    public function __get($name)
    {
        if (!isset($this->services[$name])) {
            throw new InvalidArgumentException("Unknown service $name");
        }
        $service = $this->services[$name];
        return $service();
    }

    // Call a class property like a method
    public function __call($method, $args)
    {
        if (!isset($this->$method) || !is_callable($this->$method)) {
            throw new ErrorException("Unknown method $method()");
        }
        return call_user_func_array($this->$method, $args);
    }

    // Register a lazy service
    public function register($name, $closure)
    {
        if (isset($this->services[$name])) {
            throw new Exception("A service is already registered under $name");
        }
        $this->services[$name] = function () use ($closure) {
            static $instance;
            if (null === $instance) {
                $instance = $closure();
            }
            return $instance;
        };
    }
}

class _Headers
{
    public function header($key, $value = null)
    {
        header($this->_header($key, $value));
    }

    /**
     * Output an HTTP header. If $value is null, $key is
     * assume to be the HTTP response code, and the ":"
     * separator will be omitted.
     */
    public function _header($key, $value = null)
    {
        if (null === $value) {
            return $key;
        }

        $key = str_replace(' ', '-', ucwords(str_replace('-', ' ', $key)));
        return "$key: $value";
    }
}

TsRequest::$_headers = TsResponse::$_headers = new _Headers;


/**
 * Core functions for WordPress Troubleshooter
 */

    respond('POST', '/login', 'login');
    respond(array('POST','GET'), '/home/[:sublevel]?', 'home');
    respond('POST', '/logout', 'logout');
    respond('POST', '/quick-search', 'quick_search');

/**
 * Login to the troubleshooter
 * @param $request
 * @param $response
 */
function login(TsRequest $request, TsResponse $response)
{
    if(Auth::isLoggedIn())
        home($request, $response);
    if ($request->password) {
        if (Auth::logIn($request->password)) {
            $response->flash("Logged in", "success");
            /*if(isset($request->backlink)){
                $response->discard(true);
                dispatch($request->backlink);
            } else*/
                home($request, $response);
        } else {
            $response->flash("Wrong password !!!", 'danger');
            $response->code(401);
        }
    } else {
        $response->flash("Please login first!!!", "danger");
        $response->data->title = "Home";
        $response->data->simpleData = "Please enter the password to access the troubleshooter.<br>
                             The password is given at the begaining of the script.";
        $response->data->form = true;
        $response->data->formData = array(
            array('name'  => 'link', 'type'  => 'hidden', 'value' => '/login'),
            array('name'  => 'password', 'label' => 'Password', 'type'  => 'password', 'value' => ''),
            array('name'  => 'submit', 'type'  => 'submit', 'value' => 'Login')
        );
        $response->sendDataJson();
    }
}

/**
 * Logout from troubleshooter
 * @param $request
 * @param $response
 */
function logout(TsRequest $request, TsResponse $response)
{
    Auth::logOut();
    $response->flash("Logged Out !!!");
    $response->data->title = "Log Out";
    $response->data->form = true;
    $response->data->formData = array(
        array('name'  => 'link', 'type'  => 'hidden', 'value' => '/home' ),
        array('name'  => 'submit', 'type'  => 'submit', 'value' => 'Home' )
    );
    $response->sendDataJson();
}

/**
 * Shows the home level and sub-level menu.
 * @param $request
 * @param $response
 */
function home (TsRequest $request, TsResponse $response)
{
    global $options;
    if(isset($request->sublevel))
    {
        $response->data->title = $options[$request->sublevel]['label'];
        $response->data->simpleData = $options[$request->sublevel]['label'];
        $options = $options[$request->sublevel]['plugins'];
        array_walk($options, function(&$v, $k){
            $v = ['type'=> 'radio', 'name'=>'link', 'value'=>$v['link_main'], 'label'=>$v['label']];
        });
    }else{
        $response->data->title = "Home";
        $response->data->simpleData = "Welcome to <strong>WordPress TroubleShooter</strong>. Select a troubleshoot action. ";
        array_walk($options, function(&$v, $k){
            $v = ['type'=> 'radio', 'name'=>'link', 'value'=>'/home/'.$k, 'label'=>$v['label']];
        });
    }
    $options = array_values($options);
    //$options[] = ['name'  => 'link', 'type'  => 'radio','value' => '/logout', 'label'=>'Logout'];
    $options[] = ['name'  => 'submit', 'type'  => 'submit','value' => 'Continue'];
    $response->data->form = true;
    $response->data->formData = $options;
    $response->sendDataJson();
}

function downloadPlugin($path, $name = false) {

    if(!$name) {
        $name = str_replace(TS_PLUGIN_DIR,"",$path);
    }

    if(!is_dir(dirname($path))) {
        mkdir(dirname($path),777,true);
    }

    $source = TS_REMOTE_URL."plugins/".$name;

    $http = new Http();
    $http->execute($source);

    if(!$http->error) {
        file_put_contents($path, $http->result);
    } else {
        echo $http->result;
        echo "\n------------";
        echo "\nError : ".$http->error. "[".$http->status."]";
        echo "\nSource : ".$source;
        die();
    }
}

function quick_search(TsRequest $request, TsResponse $response)
{
    global $options;
    $links=array();
    foreach($options as $name => $details){
        $links[] = ['link' =>'/home/'.$name, 'label' => $details['label']];
        foreach($details['plugins'] as $k => $v){
            $links[] = ['link' =>$v['link_main'], 'label' => $v['label']];
        }
    }
    $outlinks = array();
    foreach($links as $link){
        if (false === stripos( strtolower($link['label']), $request->str))
              continue;
            else {
                $link['label'] = str_ireplace($request->str, "<strong>".$request->str."</strong>", $link['label']);
                $outlinks[] = $link;
            }
    }
    $response->json($outlinks);
}

function getBreadcrumbs($link)
{
    global $options;
    $list = array();
    $trim_link = trim($link, '/');
    $link_arr = explode('/', $trim_link);
    if ( ! empty($link_arr)) {
        $list[] = ['link'=>'/home', 'label'=> 'Home'];
        foreach($options as $level_name=>$level) {
            if (sizeof($link_arr >=2) && $link_arr[0] !== 'home') {
                if ($link_arr[0] === $level_name)
                   $list[] = ['link'=>'/home/'.$level_name, 'label'=> $level['label']];
                if (isset($level['plugins'])) {
                    foreach ($level['plugins'] as $file_name => $file) {
                        if (in_array($link, $file['links_all']) && $link !== $file['link_main']) {
                            $list[] = ['link' => $file['link_main'], 'label' => $file['label']];
                        }
                    }
                }
            }

        }
    }
    return $list;
}

function delTree($dir) {
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}

/*register_shutdown_function(function(){
    if(defined('TS_PLUGIN_DIR'))
        delTree(TS_PLUGIN_DIR);
});*/



/**
 * Troubleshooter Error API.
 *
 * Contains the TsError class and the is_ts_error() function.
 *
 */

/**
 * Modified WordPress Error class.
 *
 * @package WordPress
 * @since 2.1.0
 */
class TsError {
    /**
     * Stores the list of errors.
     *
     * @since 2.1.0
     * @var array
     */
    public static $errors = array();

    /**
     * Initialize the error.
     *
     * @since 2.1.0
     *
     * @param string $message Error message
     */
    public function __construct( $message = '') {
        self::$errors[] = $message;
    }

    /**
     * Retrieve all error messages or error messages matching code.
     *
     * @since 2.1.0
     *
     * @return array Error strings on success, or empty array on failure (if using code parameter).
     */
    public function get_error_messages() {
        // Return all messages if no code specified.
        return self::$errors;

    }

    /**
     * Add an error or append additional message to an existing error.
     * Also add the error message in session flash to show.
     *
     * @since 2.1.0
     * @access public
     *
     * @param string $message Error message.
     */
    public static function add($message) {
        self::$errors[] = $message;
        startSession();
        if (!isset($_SESSION['__flashes'])) {
            $_SESSION['__flashes'] = array('danger' => array());
        } elseif (!isset($_SESSION['__flashes']['danger'])) {
            $_SESSION['__flashes']['danger'] = array();
        }
        $_SESSION['__flashes']['danger'][] = $message;
    }

    /**
     * Removes errors.
     *
     * This function removes all error messages.
     *
     * @since 4.1.0
     */
    public static function remove() {
        self::$errors = array();
    }
}

/**
 * Check whether variable is a Troubleshooter Error.
 *
 * Returns true if $thing is an object of the TsError class.
 *
 * @since 2.1.0
 *
 * @param mixed $thing Check if unknown variable is a TsError object.
 * @return bool True, if TsError. False, if not TsError.
 */
function is_ts_error( $thing ) {
    return ( $thing instanceof TsError );
}


/**
 * Created by PhpStorm.
 * User: gopal
 * Date: 5/5/16
 * Time: 2:34 PM
 */
class JsonOutput 
{
    public $title = 'Welcome!!!';

    public $flash;

    public $form = false;

    public $formData = null;

    public $simpleData;

    public $table = false;

    public $tableData = null;

    public $tableColumns = null;

    public $breadcrumb = null;

}

//error_reporting(0);
/**
 * HTTP Class
 *
 * This is a wrapper HTTP class that uses either cURL or fsockopen to 
 * harvest resources from web. This can be used with scripts that need 
 * a way to communicate with various APIs who support REST.
 *
 * @author      Md Emran Hasan <phpfour@gmail.com>
 * @package     HTTP Library
 * @copyright   2007-2008 Md Emran Hasan
 * @link        http://www.phpfour.com/lib/http
 * @since       Version 0.1
 */

class Http
{
    /**
     * Contains the target URL
     *
     * @var string
     */
    var $target;
    
    /**
     * Contains the target host
     *
     * @var string
     */
    var $host;
    
    /**
     * Contains the target port
     *
     * @var integer
     */
    var $port;
    
    /**
     * Contains the target path
     *
     * @var string
     */
    var $path;
    
    /**
     * Contains the target schema
     *
     * @var string
     */
    var $schema;
    
    /**
     * Contains the http method (GET or POST)
     *
     * @var string
     */
    var $method;
	
	/**
     * Contains the special stuff e.g. MADVERTISE
     *
     * @var string
     */
    var $special;
	
	/**
     * Contains the InMobi Site ID
     *
     * @var string
     */
    var $inmobisiteid;
    
    /**
     * Contains the parameters for request
     *
     * @var array
     */
    var $params;
    
    /**
     * Contains the cookies for request
     *
     * @var array
     */
    var $cookies;
    
    /**
     * Contains the cookies retrieved from response
     *
     * @var array
     */
    var $_cookies;
    
    /**
     * Number of seconds to timeout
     *
     * @var integer
     */
    var $timeout;
    
    /**
     * Whether to use cURL or not
     *
     * @var boolean
     */
    var $useCurl;
    
    /**
     * Contains the referrer URL
     *
     * @var string
     */
    var $referrer;
    
    /**
     * Contains the User agent string
     *
     * @var string
     */
    var $userAgent;
    
    /**
     * Contains the cookie path (to be used with cURL)
     *
     * @var string
     */
    var $cookiePath;
    
    /**
     * Whether to use cookie at all
     *
     * @var boolean
     */
    var $useCookie;
    
    /**
     * Whether to store cookie for subsequent requests
     *
     * @var boolean
     */
    var $saveCookie;
    
    /**
     * Contains the Username (for authentication)
     *
     * @var string
     */
    var $username;
    
    /**
     * Contains the Password (for authentication)
     *
     * @var string
     */
    var $password;
    
    /**
     * Contains the fetched web source
     *
     * @var string
     */
    var $result;
    
    /**
     * Contains the last headers 
     *
     * @var string
     */
    var $headers;
    
    /**
     * Contains the last call's http status code
     *
     * @var string
     */
    var $status;
    
    /**
     * Whether to follow http redirect or not
     *
     * @var boolean
     */
    var $redirect;
    
    /**
     * The maximum number of redirect to follow
     *
     * @var integer
     */
    var $maxRedirect;
    
    /**
     * The current number of redirects
     *
     * @var integer
     */
    var $curRedirect;
    
    /**
     * Contains any error occurred
     *
     * @var string
     */
    var $error;
    
    /**
     * Store the next token
     *
     * @var string
     */
    var $nextToken;
    
    /**
     * Whether to keep debug messages
     *
     * @var boolean
     */
    var $debug;
    
    /**
     * Stores the debug messages
     *
     * @var array
     * @todo will keep debug messages
     */
    var $debugMsg;
    
    /**
     * Constructor for initializing the class with default values.
     * 
     * @return void  
     */
    function Http()
    {
        $this->clear();    
    }
    
    /**
     * Initialize preferences
     * 
     * This function will take an associative array of config values and 
     * will initialize the class variables using them. 
     * 
     * Example use:
     * 
     * <pre>
     * $httpConfig['method']     = 'GET';
     * $httpConfig['target']     = 'http://www.somedomain.com/index.html';
     * $httpConfig['referrer']   = 'http://www.somedomain.com';
     * $httpConfig['user_agent'] = 'My Crawler';
     * $httpConfig['timeout']    = '30';
     * $httpConfig['params']     = array('var1' => 'testvalue', 'var2' => 'somevalue');
     * 
     * $http = new Http();
     * $http->initialize($httpConfig);
     * </pre>
     *
     * @param array Config values as associative array
     * @return void
     */    
    function initialize($config = array())
    {
        $this->clear();
        foreach ($config as $key => $val)
        {
            if (isset($this->$key))
            {
                $method = 'set' . ucfirst(str_replace('_', '', $key));
                
                if (method_exists($this, $method))
                {
                    $this->$method($val);
                }
                else
                {
                    $this->$key = $val;
                }            
            }
        }
    }
    
    /**
     * Clear Everything
     * 
     * Clears all the properties of the class and sets the object to
     * the beginning state. Very handy if you are doing subsequent calls 
     * with different data.
     *
     * @return void
     */
    function clear()
    {
        // Set the request defaults
        $this->host         = '';
        $this->port         = 0;
        $this->path         = '';
        $this->target       = '';
        $this->method       = 'GET';
		$this->special       = '';
		$this->inmobisiteid       = '';
        $this->schema       = 'http';
        $this->params       = array();
        $this->headers      = array();
        $this->cookies      = array();
        $this->_cookies     = array();
        
        // Set the config details        
        $this->debug        = FALSE;
        $this->error        = '';
        $this->status       = 0;
        $this->timeout      = '25';
        $this->useCurl      = TRUE;
        $this->referrer     = '';
        $this->username     = '';
        $this->password     = '';
        $this->redirect     = TRUE;
        
        // Set the cookie and agent defaults
        $this->nextToken    = '';
        $this->useCookie    = TRUE;
        $this->saveCookie   = TRUE;
        $this->maxRedirect  = 3;
        $this->cookiePath   = 'cookie.txt';
        $this->userAgent    = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.9';
    }
    
    /**
     * Set target URL
     *
     * @param string URL of target resource
     * @return void
     */
    function setTarget($url)
    {
        if ($url)
        {
            $this->target = $url;
        }   
    }
    
    /**
     * Set http method
     *
     * @param string HTTP method to use (GET or POST)
     * @return void
     */
    function setMethod($method)
    {
        if ($method == 'GET' || $method == 'POST')
        {
            $this->method = $method;
        }   
    }
    
    /**
     * Set referrer URL
     *
     * @param string URL of referrer page
     * @return void
     */
    function setReferrer($referrer)
    {
        if ($referrer)
        {
            $this->referrer = $referrer;
        }   
    }
	
	/**
     * Set referrer URL
     *
     * @param string URL of referrer page
     * @return void
     */
    function setSpecial($special)
    {
        if ($special)
        {
            $this->special = $special;
        }   
    }
	
	/**
     * Set referrer URL
     *
     * @param string URL of referrer page
     * @return void
     */
    function setInmobisiteid($inmobisiteid)
    {
        if ($inmobisiteid)
        {
            $this->inmobisiteid = $inmobisiteid;
        }   
    }
    
    /**
     * Set User agent string
     *
     * @param string Full user agent string
     * @return void
     */
    function setUseragent($agent)
    {
        if ($agent)
        {
            $this->userAgent = $agent;
        }   
    }
    
    /**
     * Set timeout of execution
     *
     * @param integer Timeout delay in seconds
     * @return void
     */
    function setTimeout($seconds)
    {
        if ($seconds > 0)
        {
            $this->timeout = $seconds;
        }   
    }
    
    /**
     * Set cookie path (cURL only)
     *
     * @param string File location of cookiejar
     * @return void
     */
    function setCookiepath($path)
    {
        if ($path)
        {
            $this->cookiePath = $path;
        }   
    }
    
    /**
     * Set request parameters
     *
     * @param array All the parameters for GET or POST
     * @return void
     */
    function setParams($dataArray)
    {
        if (is_array($dataArray))
        {
            $this->params = array_merge($this->params, $dataArray);
        }   
    }
    
    /**
     * Set basic http authentication realm
     *
     * @param string Username for authentication
     * @param string Password for authentication
     * @return void
     */
    function setAuth($username, $password)
    {
        if (!empty($username) && !empty($password))
        {
            $this->username = $username;
            $this->password = $password;
        }
    }
    
    /**
     * Set maximum number of redirection to follow
     *
     * @param integer Maximum number of redirects
     * @return void
     */
    function setMaxredirect($value)
    {
        if (!empty($value))
        {
            $this->maxRedirect = $value;
        }
    }
    
    /**
     * Add request parameters
     *
     * @param string Name of the parameter
     * @param string Value of the parameter
     * @return void
     */
    function addParam($name, $value)
    {
        if (!empty($name) && !empty($value))
        {
            $this->params[$name] = $value;
        }   
    }
    
    /**
     * Add a cookie to the request
     *
     * @param string Name of cookie
     * @param string Value of cookie
     * @return void
     */
    function addCookie($name, $value)
    {
        if (!empty($name) && !empty($value))
        {
            $this->cookies[$name] = $value;
        }   
    }
    
    /**
     * Whether to use cURL or not
     *
     * @param boolean Whether to use cURL or not
     * @return void
     */
    function useCurl($value = TRUE)
    {
        if (is_bool($value))
        {
            $this->useCurl = $value;
        }   
    }
    
    /**
     * Whether to use cookies or not
     *
     * @param boolean Whether to use cookies or not
     * @return void
     */
    function useCookie($value = TRUE)
    {
        if (is_bool($value))
        {
            $this->useCookie = $value;
        }   
    }
    
    /**
     * Whether to save persistent cookies in subsequent calls
     *
     * @param boolean Whether to save persistent cookies or not
     * @return void
     */
    function saveCookie($value = TRUE)
    {
        if (is_bool($value))
        {
            $this->saveCookie = $value;
        }   
    }
    
    /**
     * Whether to follow HTTP redirects
     *
     * @param boolean Whether to follow HTTP redirects or not
     * @return void
     */
    function followRedirects($value = TRUE)
    {
        if (is_bool($value))
        {
            $this->redirect = $value;
        }   
    }
    
    /**
     * Get execution result body
     *
     * @return string output of execution
     */
    function getResult()
    {
        return $this->result;
    }
    
    /**
     * Get execution result headers
     *
     * @return array last headers of execution
     */
    function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Get execution status code
     *
     * @return integer last http status code
     */
    function getStatus()
    {
        return $this->status;
    }
        
    /**
     * Get last execution error
     *
     * @return string last error message (if any)
     */
    function getError()
    {
        return $this->error;
    }

    /**
     * Execute a HTTP request
     * 
     * Executes the http fetch using all the set properties. Intellegently
     * switch to fsockopen if cURL is not present. And be smart to follow
     * redirects (if asked so).
     * 
     * @param string URL of the target page (optional)
     * @param string URL of the referrer page (optional)
     * @param string The http method (GET or POST) (optional)
     * @param array Parameter array for GET or POST (optional)
     * @return string Response body of the target page
     */    
    function execute($target = '', $referrer = '', $method = '', $data = array())
    {
        // Populate the properties
        $this->target = ($target) ? $target : $this->target;
        $this->method = ($method) ? $method : $this->method;
        
        $this->referrer = ($referrer) ? $referrer : $this->referrer;
        
        // Add the new params
        if (is_array($data) && count($data) > 0) 
        {
            $this->params = array_merge($this->params, $data);
        }
        
        // Process data, if presented
        if(is_array($this->params) && count($this->params) > 0)
        {
            // Get a blank slate
            $tempString = array();
            
            // Convert data array into a query string (ie animal=dog&sport=baseball)
            foreach ($this->params as $key => $value) 
            {
                if(strlen(trim($value))>0)
                {
                    $tempString[] = $key . "=" . urlencode($value);
                }
            }
            
            $queryString = join('&', $tempString);
        }
        
        // If cURL is not installed, we'll force fscokopen
        $this->useCurl = $this->useCurl && in_array('curl', get_loaded_extensions());
        
        // GET method configuration
        if($this->method == 'GET')
        {
            if(isset($queryString))
            {
                $this->target = $this->target . "?" . $queryString;
            }
        }
        
        // Parse target URL
        $urlParsed = parse_url($this->target);
        
        // Handle SSL connection request
        if ($urlParsed['scheme'] == 'https')
        {
            $this->host = 'ssl://' . $urlParsed['host'];
            $this->port = ($this->port != 0) ? $this->port : 443;
        }
        else
        {
            $this->host = $urlParsed['host'];
            $this->port = ($this->port != 0) ? $this->port : 80;
        }
        
        // Finalize the target path
        $this->path   = (isset($urlParsed['path']) ? $urlParsed['path'] : '/') . (isset($urlParsed['query']) ? '?' . $urlParsed['query'] : '');
        $this->schema = $urlParsed['scheme'];
        
        // Pass the requred cookies
        $this->_passCookies();
        
        // Process cookies, if requested
        if(is_array($this->cookies) && count($this->cookies) > 0)
        {
            // Get a blank slate
            $tempString   = array();
            
            // Convert cookiesa array into a query string (ie animal=dog&sport=baseball)
            foreach ($this->cookies as $key => $value) 
            {
                if(strlen(trim($value)) > 0)
                {
                    $tempString[] = $key . "=" . urlencode($value);
                }
            }
            
            $cookieString = join('&', $tempString);
        }
        
        // Do we need to use cURL
        if ($this->useCurl)
        {
            // Initialize PHP cURL handle
            $ch = curl_init();
    
            // GET method configuration
            if($this->method == 'GET')
            {
                curl_setopt ($ch, CURLOPT_HTTPGET, TRUE); 
                curl_setopt ($ch, CURLOPT_POST, FALSE); 
            }
            // POST method configuration
            else
            {
                if(isset($queryString))
                {
                    curl_setopt ($ch, CURLOPT_POSTFIELDS, $queryString);
                }
                
                curl_setopt ($ch, CURLOPT_POST, TRUE); 
                curl_setopt ($ch, CURLOPT_HTTPGET, FALSE); 
            }
            
            // Basic Authentication configuration
            if ($this->username && $this->password)
            {
                curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
            }
            
            // Custom cookie configuration
            if($this->useCookie && isset($cookieString))
            {
                curl_setopt ($ch, CURLOPT_COOKIE, $cookieString);
            }
            
            curl_setopt($ch, CURLOPT_HEADER,         TRUE);                 // No need of headers
            curl_setopt($ch, CURLOPT_NOBODY,         FALSE);                // Return body
                
            curl_setopt($ch, CURLOPT_COOKIEJAR,      $this->cookiePath);    // Cookie management.
            curl_setopt($ch, CURLOPT_TIMEOUT,        $this->timeout);       // Timeout
			 if($this->special == 'MADVERTISE')
            {
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/xml'));
			}
			 if($this->special == 'INMOBI')
            {
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded', 'X-mKhoj-SiteId: '.$this->inmobisiteid.''));
			}
            curl_setopt($ch, CURLOPT_USERAGENT,      $this->userAgent);     // Webbot name
            curl_setopt($ch, CURLOPT_URL,            $this->target);        // Target site
            curl_setopt($ch, CURLOPT_REFERER,        $this->referrer);      // Referer value
            
            curl_setopt($ch, CURLOPT_VERBOSE,        FALSE);                // Minimize logs
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);                // No certificate
            @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->redirect);      // Follow redirects
            curl_setopt($ch, CURLOPT_MAXREDIRS,      $this->maxRedirect);   // Limit redirections to four
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);                 // Return in string
            
            // Get the target contents
            $content = curl_exec($ch);
            $contentArray = explode("\r\n\r\n", $content,2);
            
            // Get the request info 
            $status  = curl_getinfo($ch);
            
            // Store the contents
            $this->result = $contentArray[1];

            // Parse the headers
            $this->_parseHeaders($contentArray[0]);
                        
            // Store the error (is any)
            $this->_setError(curl_error($ch));
            
            // Close PHP cURL handle
            curl_close($ch);
        }
        else
        {
            // Get a file pointer
            $filePointer = fsockopen($this->host, $this->port, $errorNumber, $errorString, $this->timeout);
       
            // We have an error if pointer is not there
            if (!$filePointer)
            {
                $this->_setError('Failed opening http socket connection: ' . $errorString . ' (' . $errorNumber . ')');
                return FALSE;
            }

            // Set http headers with host, user-agent and content type
            $requestHeader  = $this->method . " " . $this->path . "  HTTP/1.1\r\n";
            $requestHeader .= "Host: " . $urlParsed['host'] . "\r\n";
            $requestHeader .= "User-Agent: " . $this->userAgent . "\r\n";
            $requestHeader .= "Content-Type: application/x-www-form-urlencoded\r\n";
            
            // Specify the custom cookies
            if ($this->useCookie && $cookieString != '')
            {
                $requestHeader.= "Cookie: " . $cookieString . "\r\n";
            }

            // POST method configuration
            if ($this->method == "POST")
            {
                $requestHeader.= "Content-Length: " . strlen($queryString) . "\r\n";
            }
            
            // Specify the referrer
            if ($this->referrer != '')
            {
                $requestHeader.= "Referer: " . $this->referrer . "\r\n";
            }
            
            // Specify http authentication (basic)
            if ($this->username && $this->password)
            {
                $requestHeader.= "Authorization: Basic " . base64_encode($this->username . ':' . $this->password) . "\r\n";
            }
       
            $requestHeader.= "Connection: close\r\n\r\n";
       
            // POST method configuration
            if ($this->method == "POST")
            {
                $requestHeader .= $queryString;
            }           

            // We're ready to launch
            fwrite($filePointer, $requestHeader);
       
            // Clean the slate
            $responseHeader = '';
            $responseContent = '';

            // 3...2...1...Launch !
            do
            {
                $responseHeader .= fread($filePointer, 1);
            }
            while (!preg_match('/\\r\\n\\r\\n$/', $responseHeader));
            
            // Parse the headers
            $this->_parseHeaders($responseHeader);
            
            // Do we have a 301/302 redirect ?
            if (($this->status == '301' || $this->status == '302') && $this->redirect == TRUE)
            {
                if ($this->curRedirect < $this->maxRedirect)
                {
                    // Let's find out the new redirect URL
                    $newUrlParsed = parse_url($this->headers['location']);
                    
                    if ($newUrlParsed['host'])
                    {
                        $newTarget = $this->headers['location'];    
                    }
                    else
                    {
                        $newTarget = $this->schema . '://' . $this->host . '/' . $this->headers['location'];
                    }
                    
                    // Reset some of the properties
                    $this->port   = 0;
                    $this->status = 0;
                    $this->params = array();
                    $this->method = 'GET';
                    $this->referrer = $this->target;
                    
                    // Increase the redirect counter
                    $this->curRedirect++;
                    
                    // Let's go, go, go !
                    $this->result = $this->execute($newTarget);
                }
                else
                {
                    $this->_setError('Too many redirects.');
                    return FALSE;
                }
            }
            else
            {
                // Nope...so lets get the rest of the contents (non-chunked)
                if ($this->headers['transfer-encoding'] != 'chunked')
                {
                    while (!feof($filePointer))
                    {
                        $responseContent .= fgets($filePointer, 128);
                    }
                }
                else
                {
                    // Get the contents (chunked)
                    while ($chunkLength = hexdec(fgets($filePointer)))
                    {
                        $responseContentChunk = '';
                        $readLength = 0;
                       
                        while ($readLength < $chunkLength)
                        {
                            $responseContentChunk .= fread($filePointer, $chunkLength - $readLength);
                            $readLength = strlen($responseContentChunk);
                        }

                        $responseContent .= $responseContentChunk;
                        fgets($filePointer);  
                    }
                }
                
                // Store the target contents
                $this->result = chop($responseContent);
            }
        }
        
        // There it is! We have it!! Return to base !!!
        return $this->result;
    }
    
    /**
     * Parse Headers (internal)
     * 
     * Parse the response headers and store them for finding the resposne 
     * status, redirection location, cookies, etc. 
     *
     * @param string Raw header response
     * @return void
     * @access private
     */
    function _parseHeaders($responseHeader)
    {
        // Break up the headers
        $headers = explode("\r\n", $responseHeader);

        // Clear the header array
        $this->_clearHeaders();
        
        // Get resposne status
        if($this->status == 0)
        {
            // Oooops !
            if(!preg_match("#^http/[0-9]+\\.[0-9]+[ \t]+([0-9]+)[ \t]*(.*)\$#i", $headers[0], $matches))
            {
                $this->_setError('Unexpected HTTP response status');
                return FALSE;
            }
            
            // Gotcha!
            $this->status = $matches[1];
            array_shift($headers);
        }
        
        // Prepare all the other headers
        foreach ($headers as $header)
        {
            // Get name and value
            $headerName  = strtolower($this->_tokenize($header, ':'));
            $headerValue = trim(chop($this->_tokenize("\r\n")));
            
            // If its already there, then add as an array. Otherwise, just keep there
            if(isset($this->headers[$headerName]))
            {
                if(gettype($this->headers[$headerName]) == "string")
                {
                    $this->headers[$headerName] = array($this->headers[$headerName]);
                }
                    
                $this->headers[$headerName][] = $headerValue;
            }
            else
            {
                $this->headers[$headerName] = $headerValue;
            }
        }
            
        // Save cookies if asked 
        if ($this->saveCookie && isset($this->headers['set-cookie']))
        {
            $this->_parseCookie();
        }
    }
    
    /**
     * Clear the headers array (internal)
     *
     * @return void
     * @access private
     */
    function _clearHeaders()
    {
        $this->headers = array();
    }
    
    /**
     * Parse Cookies (internal)
     * 
     * Parse the set-cookie headers from response and add them for inclusion.
     *
     * @return void
     * @access private
     */
    function _parseCookie()
    {
        // Get the cookie header as array
        if(gettype($this->headers['set-cookie']) == "array")
        {
            $cookieHeaders = $this->headers['set-cookie'];
        }
        else
        {
            $cookieHeaders = array($this->headers['set-cookie']);
        }

        // Loop through the cookies
        for ($cookie = 0; $cookie < count($cookieHeaders); $cookie++)
        {
            $cookieName  = trim($this->_tokenize($cookieHeaders[$cookie], "="));
            $cookieValue = $this->_tokenize(";");
            
            $urlParsed   = parse_url($this->target);
            
            $domain      = $urlParsed['host'];
            $secure      = '0';
            
            $path        = "/";
            $expires     = "";
            
            while(($name = trim(urldecode($this->_tokenize("=")))) != "")
            {
                $value = urldecode($this->_tokenize(";"));
                
                switch($name)
                {
                    case "path"     : $path     = $value; break;
                    case "domain"   : $domain   = $value; break;
                    case "secure"   : $secure   = ($value != '') ? '1' : '0'; break;
                }
            }
            
            $this->_setCookie($cookieName, $cookieValue, $expires, $path , $domain, $secure);
        }
    }
    
    /**
     * Set cookie (internal)
     * 
     * Populate the internal _cookies array for future inclusion in 
     * subsequent requests. This actually validates and then populates 
     * the object properties with a dimensional entry for cookie.
     *
     * @param string Cookie name
     * @param string Cookie value
     * @param string Cookie expire date
     * @param string Cookie path
     * @param string Cookie domain
     * @param string Cookie security (0 = non-secure, 1 = secure)
     * @return void
     * @access private
     */
    function _setCookie($name, $value, $expires = "" , $path = "/" , $domain = "" , $secure = 0)
    {
        if(strlen($name) == 0)
        {
            return($this->_setError("No valid cookie name was specified."));
        }

        if(strlen($path) == 0 || strcmp($path[0], "/"))
        {
            return($this->_setError("$path is not a valid path for setting cookie $name."));
        }
            
        if($domain == "" || !strpos($domain, ".", $domain[0] == "." ? 1 : 0))
        {
            return($this->_setError("$domain is not a valid domain for setting cookie $name."));
        }
        
        $domain = strtolower($domain);
        
        if(!strcmp($domain[0], "."))
        {
            $domain = substr($domain, 1);
        }
            
        $name  = $this->_encodeCookie($name, true);
        $value = $this->_encodeCookie($value, false);
        
        $secure = intval($secure);
        
        $this->_cookies[] = array( "name"      =>  $name,
                                   "value"     =>  $value,
                                   "domain"    =>  $domain,
                                   "path"      =>  $path,
                                   "expires"   =>  $expires,
                                   "secure"    =>  $secure
                                 );
    }
    
    /**
     * Encode cookie name/value (internal)
     *
     * @param string Value of cookie to encode
     * @param string Name of cookie to encode
     * @return string encoded string
     * @access private
     */
    function _encodeCookie($value, $name)
    {
        return($name ? str_replace("=", "%25", $value) : str_replace(";", "%3B", $value));
    }
    
    /**
     * Pass Cookies (internal)
     * 
     * Get the cookies which are valid for the current request. Checks 
     * domain and path to decide the return.
     *
     * @return void
     * @access private
     */
    function _passCookies()
    {
        if (is_array($this->_cookies) && count($this->_cookies) > 0)
        {
            $urlParsed = parse_url($this->target);
            $tempCookies = array();
            
            foreach($this->_cookies as $cookie)
            {
                if ($this->_domainMatch($urlParsed['host'], $cookie['domain']) && (0 === strpos($urlParsed['path'], $cookie['path']))
                    && (empty($cookie['secure']) || $urlParsed['protocol'] == 'https')) 
                {
                    $tempCookies[$cookie['name']][strlen($cookie['path'])] = $cookie['value'];
                }
            }
            
            // cookies with longer paths go first
            foreach ($tempCookies as $name => $values) 
            {
                krsort($values);
                foreach ($values as $value) 
                {
                    $this->addCookie($name, $value);
                }
            }
        }
    }
    
    /**
    * Checks if cookie domain matches a request host (internal)
    * 
    * Cookie domain can begin with a dot, it also must contain at least
    * two dots.
    * 
    * @param string Request host
    * @param string Cookie domain
    * @return bool Match success
     * @access private
    */
    function _domainMatch($requestHost, $cookieDomain)
    {
        if ('.' != $cookieDomain{0}) 
        {
            return $requestHost == $cookieDomain;
        } 
        elseif (substr_count($cookieDomain, '.') < 2) 
        {
            return false;
        } 
        else 
        {
            return substr('.'. $requestHost, - strlen($cookieDomain)) == $cookieDomain;
        }
    }
    
    /**
     * Tokenize String (internal)
     * 
     * Tokenize string for various internal usage. Omit the second parameter 
     * to tokenize the previous string that was provided in the prior call to 
     * the function.
     *
     * @param string The string to tokenize
     * @param string The seperator to use
     * @return string Tokenized string
     * @access private
     */
    function _tokenize($string, $separator = '')
    {
        if(!strcmp($separator, ''))
        {
            $separator = $string;
            $string = $this->nextToken;
        }
        
        for($character = 0; $character < strlen($separator); $character++)
        {
            if(gettype($position = strpos($string, $separator[$character])) == "integer")
            {
                $found = (isset($found) ? min($found, $position) : $position);
            }
        }
        
        if(isset($found))
        {
            $this->nextToken = substr($string, $found + 1);
            return(substr($string, 0, $found));
        }
        else
        {
            $this->nextToken = '';
            return($string);
        }
    }
    
    /**
     * Set error message (internal)
     *
     * @param string Error message
     * @return string Error message
     * @access private
     */
    function _setError($error)
    {
        if ($error != '')
        {
            $this->error = $error;
            return $error;
        }
    }
}




if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' && isset($_POST['link'])) {

    if (!file_exists(TS_PLUGIN_DIR . 'plugins.json') || TS_DEBUG) {
        downloadPlugin(TS_PLUGIN_DIR . 'plugins.json');
    }
    $options_file = file_get_contents(TS_PLUGIN_DIR . 'plugins.json');

    global $options;
    $options = json_decode($options_file, true);
    respond(function (TsRequest $request, TsResponse $response, TsApp $app) {
        $response->onError(function ($response, $err_msg) {
            $response->flash($err_msg, 'danger');
            $response->back();
        });
        $app->register('db', function () {
            $db_details = array();
            $configPath = TS_ABSPATH . 'wp-config.php';
            if (is_file($configPath)) {
                $c = file_get_contents($configPath);
                if ($c) {
                    preg_match('/define.*DB_NAME.*\'(.*)\'/', $c, $m);
                    $db_details['name'] = $m[1];

                    preg_match('/define.*DB_USER.*\'(.*)\'/', $c, $m);
                    $db_details['user'] = $m[1];

                    preg_match('/define.*DB_PASSWORD.*\'(.*)\'/', $c, $m);
                    $db_details['pass'] = $m[1];

                    preg_match('/define.*DB_HOST.*\'(.*)\'/', $c, $m);
                    $db_details['host'] = $m[1];
                    preg_match('/\$table_prefix.*\'(.*)\'/', $c, $m);
                    $db_details['prefix'] = $m[1];

                } else {

                }
            } else {

            }
            /*$db = new DB($db_details['user'], $db_details['pass'], $db_details['name'], $db_details['host']);
            $db->set_prefix($db_details['prefix']);*/
            $db = new stdClass();
            $db->info = $db_details;
            $db->link = mysqli_connect($db_details['host'],$db_details['user'],$db_details['pass'],$db_details['name']);
            return $db;
        });
    });

    foreach ($options as $level_name => $level) {
        if(isset($level['plugins'])) {
            foreach ($level['plugins'] as $file_name => $file) {
                if (in_array($_POST['link'], $file['links_all'])) {
                    if (!file_exists(TS_PLUGIN_DIR . $level_name . '/' . $file_name . '.php') || TS_DEBUG) {
                        downloadPlugin(TS_PLUGIN_DIR . $level_name . '/' . $file_name . '.php');
                    }
                    require TS_PLUGIN_DIR . $level_name . '/' . $file_name . '.php';
                }
            }
        }
    }

    if (Auth::isLoggedIn()) {
        dispatch($_POST['link']);

        // wordpress include
        if (function_exists('afterWordPress') && defined('INCLUDE_WORDPRESS')) {
            ob_start(null, 0, PHP_OUTPUT_HANDLER_CLEANABLE);
            //declare(ticks = 1);
            /*register_tick_function(function(){
                $fp = fopen('/work/backnew.txt', 'a');
                fwrite($fp, debug_backtrace()[2]['file'] . "\n");
                fclose($fp);
            });*/
            //register_tick_function(array($p3Profiler, 'ts_tick_handler'));
            require TS_ABSPATH . 'index.php';
            ob_end_clean();
            ob_clean();
            afterWordPress();
            //http_response_code(200);
        }

    } else {
        $_POST['backlink'] = $_POST['link'];
        dispatch('/login');
    }

} elseif (isset($_GET['ts_plugin'])) {

    if (Auth::isLoggedIn()) {
        if (!file_exists(TS_PLUGIN_DIR . $_GET['ts_plugin'] . '.php') || TS_DEBUG) {
            downloadPlugin(TS_PLUGIN_DIR . $_GET['ts_plugin'] . '.php', $_GET['ts_plugin'].".php");
        }
        require TS_PLUGIN_DIR . $_GET['ts_plugin'] . '.php';
    }
} else {
    $layout = "PCFET0NUWVBFIGh0bWw+DQo8aHRtbCBsYW5nPSJlbiI+DQo8aGVhZD4NCiAgICA8bWV0YSBjaGFyc2V0PSJ1dGYtOCI+DQogICAgPG1ldGEgaHR0cC1lcXVpdj0iWC1VQS1Db21wYXRpYmxlIiBjb250ZW50PSJJRT1lZGdlIj4NCiAgICA8bWV0YSBuYW1lPSJ2aWV3cG9ydCIgY29udGVudD0id2lkdGg9ZGV2aWNlLXdpZHRoLCBpbml0aWFsLXNjYWxlPTEiPg0KICAgIDx0aXRsZT5Xb3JkcHJlc3MgVHJvdWJsZXNob290ZXI8L3RpdGxlPg0KICAgIDxsaW5rIHJlbD0ic3R5bGVzaGVldCIgaHJlZj0iaHR0cHM6Ly9tYXhjZG4uYm9vdHN0cmFwY2RuLmNvbS9ib290c3RyYXAvMy4zLjYvY3NzL2Jvb3RzdHJhcC5taW4uY3NzIiBpbnRlZ3JpdHk9InNoYTM4NC0xcThtVEpPQVN4OGoxQXUrYTVXRFZuUGkybGtGZnd3RUFhOGhERGRqWmxwTGVneGhqVk1FMWZnaldQR21renM3IiBjcm9zc29yaWdpbj0iYW5vbnltb3VzIj4NCiAgICA8bGluayByZWw9InN0eWxlc2hlZXQiIHR5cGU9InRleHQvY3NzIiBocmVmPSIvL2Nkbi5kYXRhdGFibGVzLm5ldC8xLjEwLjExL2Nzcy9qcXVlcnkuZGF0YVRhYmxlcy5jc3MiPg0KICAgIDwhLS0gT3B0aW9uYWwgdGhlbWUgLS0+DQogICAgPGxpbmsgcmVsPSJzdHlsZXNoZWV0IiBocmVmPSJodHRwczovL21heGNkbi5ib290c3RyYXBjZG4uY29tL2Jvb3RzdHJhcC8zLjMuNi9jc3MvYm9vdHN0cmFwLXRoZW1lLm1pbi5jc3MiIGludGVncml0eT0ic2hhMzg0LWZMVzJOMDFsTXFqYWtCa3gzbC9NOUVhaHV3cFNmZU52VjYzSjVlem4zdVp6YXBUMHU3RVlzWE1qUVYrMEVuNXIiIGNyb3Nzb3JpZ2luPSJhbm9ueW1vdXMiPg0KDQogICAgPCEtLVtpZiBsdCBJRSA5XT4NCiAgICA8c2NyaXB0IHNyYz0iaHR0cHM6Ly9vc3MubWF4Y2RuLmNvbS9odG1sNXNoaXYvMy43LjIvaHRtbDVzaGl2Lm1pbi5qcyI+PC9zY3JpcHQ+DQogICAgPHNjcmlwdCBzcmM9Imh0dHBzOi8vb3NzLm1heGNkbi5jb20vcmVzcG9uZC8xLjQuMi9yZXNwb25kLm1pbi5qcyI+PC9zY3JpcHQ+DQogICAgPCFbZW5kaWZdLS0+DQo8L2hlYWQ+DQo8Ym9keT4NCjxkaXYgY2xhc3M9ImNvbnRhaW5lciIgc3R5bGU9Im1hcmdpbi10b3A6MzBweCI+DQogICAgPGRpdiBjbGFzcz0iY29sLW1kLTEyIiBzdHlsZT0iZmxvYXQ6bm9uZTsgbWFyZ2luOiAwIGF1dG87Ij4NCiAgICAgICAgPGRpdiBjbGFzcz0icGFuZWwgcGFuZWwtZGVmYXVsdCI+DQogICAgICAgICAgICA8ZGl2IGNsYXNzPSJwYW5lbC1oZWFkaW5nIj48c3BhbiBjbGFzcz0icGFuZWwtdGl0bGUiPjxzdHJvbmcgaWQ9InRpdGxlIj5XZWxjb21lIHRvIFdvcmRQcmVzcyBUcm91YmxlU2hvb3Rlcjwvc3Ryb25nPjwvc3Bhbj4NCiAgICAgICAgICAgICAgICAgICAgPHNwYW4gY2xhc3M9InB1bGwtcmlnaHQiIGlkPSJzZWFyY2gtYm94Ij4NCiAgICAgICAgICAgICAgICAgICAgPGlucHV0IHR5cGU9InRleHQiIGlkPSJxdWljay1zZWFyY2giPg0KICAgICAgICAgICAgICAgICAgICA8L3NwYW4+DQogICAgICAgICAgICAgICAgPGJyPg0KICAgICAgICAgICAgPC9kaXY+DQogICAgICAgICAgICA8ZGl2Pg0KICAgICAgICAgICAgICAgIDxvbCBjbGFzcz0iYnJlYWRjcnVtYiIgc3R5bGU9ImZvbnQtc2l6ZToxMnB4OyI+DQogICAgICAgICAgICAgICAgPC9vbD4NCiAgICAgICAgICAgICAgICA8dWwgY2xhc3M9Imxpc3QtZ3JvdXAgdGV4dC1pbmZvIiBzdHlsZT0iIiBpZD0icXVpY2stbGlua3MiPg0KICAgICAgICAgICAgICAgIDwvdWw+DQogICAgICAgICAgICA8L2Rpdj4NCiAgICAgICAgICAgIDxpbWcgc3JjPSJ3cC1hZG1pbi9pbWFnZXMvbG9hZGluZy5naWYiIHN0eWxlPSJtYXJnaW4tbGVmdDogNTAlOyBkaXNwbGF5OiBub25lOyIgaWQ9ImxvYWRpbmciPg0KICAgICAgICAgICAgPGRpdiBjbGFzcz0icGFuZWwtYm9keSI+DQogICAgICAgICAgICAgICAgPGRpdiBpZD0ic2ltcGxlZGF0YSI+DQogICAgICAgICAgICAgICAgPC9kaXY+DQogICAgICAgICAgICAgICAgPGRpdiBpZD0iZm9ybUJvZHkiPg0KICAgICAgICAgICAgICAgICAgICA8Zm9ybT48aW5wdXQgdHlwZT0iaGlkZGVuIiB2YWx1ZT0iL2hvbWUiIG5hbWU9ImxpbmsiPg0KICAgICAgICAgICAgICAgICAgICAgICAgPGlucHV0IHR5cGU9InN1Ym1pdCIgdmFsdWU9IkxldFwncyBTdGFydCIgY2xhc3M9ImJ0biBidG4tcHJpbWFyeSI+DQogICAgICAgICAgICAgICAgICAgIDwvZm9ybT48L2Rpdj4NCiAgICAgICAgICAgIDwvZGl2Pg0KICAgICAgICA8L2Rpdj4NCiAgICA8L2Rpdj4NCiAgICA8ZGl2IGNsYXNzPSJtb2RhbCBmYWRlIiBpZD0ibXlNb2RhbCIgdGFiaW5kZXg9Ii0xIiByb2xlPSJkaWFsb2ciIGFyaWEtbGFiZWxsZWRieT0ibXlNb2RhbExhYmVsIj4NCiAgICAgICAgPGRpdiBjbGFzcz0ibW9kYWwtZGlhbG9nIiByb2xlPSJkb2N1bWVudCI+DQogICAgICAgICAgICA8ZGl2IGNsYXNzPSJtb2RhbC1jb250ZW50Ij4NCiAgICAgICAgICAgICAgICA8ZGl2IGNsYXNzPSJtb2RhbC1oZWFkZXIiPg0KICAgICAgICAgICAgICAgICAgICA8YnV0dG9uIHR5cGU9ImJ1dHRvbiIgY2xhc3M9ImNsb3NlIiBkYXRhLWRpc21pc3M9Im1vZGFsIiBhcmlhLWxhYmVsPSJDbG9zZSI+PHNwYW4gYXJpYS1oaWRkZW49InRydWUiPiZ0aW1lczs8L3NwYW4+PC9idXR0b24+DQogICAgICAgICAgICAgICAgICAgIDxoNCBjbGFzcz0ibW9kYWwtdGl0bGUiIGlkPSJteU1vZGFsTGFiZWwiPjwvaDQ+DQogICAgICAgICAgICAgICAgPC9kaXY+DQogICAgICAgICAgICAgICAgPGRpdiBjbGFzcz0ibW9kYWwtYm9keSI+DQoNCiAgICAgICAgICAgICAgICA8L2Rpdj4NCiAgICAgICAgICAgICAgICA8ZGl2IGNsYXNzPSJtb2RhbC1mb290ZXIiPg0KICAgICAgICAgICAgICAgICAgICA8YnV0dG9uIHR5cGU9ImJ1dHRvbiIgY2xhc3M9ImJ0biBidG4tZGVmYXVsdCIgZGF0YS1kaXNtaXNzPSJtb2RhbCI+Q2xvc2U8L2J1dHRvbj4NCiAgICAgICAgICAgICAgICA8L2Rpdj4NCiAgICAgICAgICAgIDwvZGl2Pg0KICAgICAgICA8L2Rpdj4NCiAgICA8L2Rpdj4NCjwvZGl2Pg0KDQo8IS0tIGpRdWVyeSAobmVjZXNzYXJ5IGZvciBCb290c3RyYXBcJ3MgSmF2YVNjcmlwdCBwbHVnaW5zKSAtLT4NCjxzY3JpcHQgc3JjPSJodHRwczovL2FqYXguZ29vZ2xlYXBpcy5jb20vYWpheC9saWJzL2pxdWVyeS8xLjExLjMvanF1ZXJ5Lm1pbi5qcyI+PC9zY3JpcHQ+DQo8IS0tIEluY2x1ZGUgYWxsIGNvbXBpbGVkIHBsdWdpbnMgKGJlbG93KSwgb3IgaW5jbHVkZSBpbmRpdmlkdWFsIGZpbGVzIGFzIG5lZWRlZCAtLT4NCjxzY3JpcHQgc3JjPSJodHRwczovL21heGNkbi5ib290c3RyYXBjZG4uY29tL2Jvb3RzdHJhcC8zLjMuNi9qcy9ib290c3RyYXAubWluLmpzIiBpbnRlZ3JpdHk9InNoYTM4NC0wbVNiSkRFSGlhbGZtdUJCUVA2QTRRcnBycTVPVmZXMzdQUlIzajVFTHF4c3MxeVZxT3RuZXBuSFZQOWFKN3hTIiBjcm9zc29yaWdpbj0iYW5vbnltb3VzIj48L3NjcmlwdD4NCjxzY3JpcHQgdHlwZT0idGV4dC9qYXZhc2NyaXB0IiBjaGFyc2V0PSJ1dGY4IiBzcmM9Ii8vY2RuLmRhdGF0YWJsZXMubmV0LzEuMTAuMTEvanMvanF1ZXJ5LmRhdGFUYWJsZXMuanMiPjwvc2NyaXB0Pg0KDQo8c3R5bGUgdHlwZT0idGV4dC9jc3MiPg0KDQouYnJlYWRjcnVtYiBhew0KICAgIGN1cnNvcjogcG9pbnRlcjsNCn0NCg0KPC9zdHlsZT4NCg0KDQo8c2NyaXB0IHR5cGU9InRleHQvamF2YXNjcmlwdCI+DQogICAgJChmdW5jdGlvbigpIHsNCiAgICAgICAgZnVuY3Rpb24gcHJvY2Vzc0RhdGEoZGF0YSl7DQogICAgICAgICAgICAkKCIjbG9hZGluZyIpLmhpZGUoKTsNCiAgICAgICAgICAgICQoIiN0aXRsZSIpLmh0bWwoZGF0YS50aXRsZSk7DQogICAgICAgICAgICB2YXIgZm9ybUJvZHkgPSAkKCIjZm9ybUJvZHkiKTsNCiAgICAgICAgICAgIGZvcm1Cb2R5Lmh0bWwoJycpOw0KICAgICAgICAgICAgaWYoZGF0YS5mbGFzaCl7DQogICAgICAgICAgICAgICAgaWYoZGF0YS5mbGFzaC5kYW5nZXIpDQogICAgICAgICAgICAgICAgICAgIGZvcm1Cb2R5LmFwcGVuZChwcmludEFsZXJ0KCdkYW5nZXInLCBkYXRhLmZsYXNoLmRhbmdlcikpOw0KICAgICAgICAgICAgICAgIGlmKGRhdGEuZmxhc2guaW5mbykNCiAgICAgICAgICAgICAgICAgICAgZm9ybUJvZHkuYXBwZW5kKHByaW50QWxlcnQoJ2luZm8nLCBkYXRhLmZsYXNoLmluZm8pKTsNCiAgICAgICAgICAgICAgICBpZihkYXRhLmZsYXNoLnN1Y2Nlc3MpDQogICAgICAgICAgICAgICAgICAgIGZvcm1Cb2R5LmFwcGVuZChwcmludEFsZXJ0KCdzdWNjZXNzJywgZGF0YS5mbGFzaC5zdWNjZXNzKSk7DQogICAgICAgICAgICB9DQogICAgICAgICAgICBpZihkYXRhLnNpbXBsZURhdGEpew0KICAgICAgICAgICAgICAgICQoIiNzaW1wbGVkYXRhIikuaHRtbCgiIik7DQogICAgICAgICAgICAgICAgJCgiI3NpbXBsZWRhdGEiKS5hcHBlbmQoZGF0YS5zaW1wbGVEYXRhKyc8YnI+Jyk7DQogICAgICAgICAgICB9DQogICAgICAgICAgICAkYnJlYWRjcnVtYiA9ICQoIi5icmVhZGNydW1iIik7DQogICAgICAgICAgICBpZihkYXRhLmJyZWFkY3J1bWIpIHsNCiAgICAgICAgICAgICAgICAkYnJlYWRjcnVtYi5odG1sKCIiKTsNCiAgICAgICAgICAgICAgICBmb3IgKHZhciBpbmRleCA9IDA7IGluZGV4IDwgZGF0YS5icmVhZGNydW1iLmxlbmd0aDsgKytpbmRleCkgew0KICAgICAgICAgICAgICAgICAgICAkYnJlYWRjcnVtYi5hcHBlbmQoJzxsaT48YSBpZD0iJyArIGRhdGEuYnJlYWRjcnVtYltpbmRleF0ubGluayArICciPicgKyBkYXRhLmJyZWFkY3J1bWJbaW5kZXhdLmxhYmVsKTsNCiAgICAgICAgICAgICAgICB9DQogICAgICAgICAgICAgICAgJGJyZWFkY3J1bWIuYXBwZW5kKCc8bGkgY2xhc3M9ImFjdGl2ZSI+JyArIGRhdGEudGl0bGUpOw0KICAgICAgICAgICAgfQ0KICAgICAgICAgICAgaWYoZGF0YS5mb3JtKXsNCiAgICAgICAgICAgICAgICAvL2Zvcm1Cb2R5LmFwcGVuZCgnPGZvcm0vPicpOw0KICAgICAgICAgICAgICAgICRmb3JtID0gJCgnPGZvcm0gaWQ9IiNmb3JtIiBtZXRob2Q9InBvc3QiPjwvZm9ybT4nKTsNCiAgICAgICAgICAgICAgICBmb3IgKHZhciBpbmRleCA9IDA7IGluZGV4IDwgZGF0YS5mb3JtRGF0YS5sZW5ndGg7ICsraW5kZXgpIHsNCiAgICAgICAgICAgICAgICAgICAgdmFyIGZpZWxkID0gZGF0YS5mb3JtRGF0YVtpbmRleF07DQogICAgICAgICAgICAgICAgICAgIC8vZm9ybUJvZHkuYXBwZW5kKCc8ZGl2IGNsYXNzPSJmb3JtLWdyb3VwIj4nKTsNCiAgICAgICAgICAgICAgICAgICAgaWYoZmllbGQudHlwZT09InJhZGlvIikNCiAgICAgICAgICAgICAgICAgICAgew0KICAgICAgICAgICAgICAgICAgICAgICAgJGZvcm1FbGVtZW50ID0gJCgnPGRpdiBjbGFzcz0icmFkaW8iPicpOw0KICAgICAgICAgICAgICAgICAgICAgICAgJGZvcm1FbGVtZW50LmFwcGVuZCgnPGxhYmVsPjxpbnB1dCB0eXBlPSInK2ZpZWxkLnR5cGUrJyIgbmFtZT0iJytmaWVsZC5uYW1lKyciIHZhbHVlPSInK2ZpZWxkLnZhbHVlKyciPicrZmllbGQubGFiZWwrJzwvbGFiZWw+Jyk7DQogICAgICAgICAgICAgICAgICAgICAgICAkZm9ybS5hcHBlbmQoJGZvcm1FbGVtZW50KTsNCiAgICAgICAgICAgICAgICAgICAgfSBlbHNlIHsNCiAgICAgICAgICAgICAgICAgICAgICAgICRmb3JtRWxlbWVudCA9ICQoJzxkaXYgY2xhc3M9ImZvcm0tZ3JvdXAiPicpOw0KICAgICAgICAgICAgICAgICAgICAgICAgaWYoZmllbGQubGFiZWwpDQogICAgICAgICAgICAgICAgICAgICAgICAgICAgJGZvcm1FbGVtZW50LmFwcGVuZCgnPGxhYmVsIGZvcj0iJytmaWVsZC5uYW1lKyciPicrZmllbGQubGFiZWwrJzwvbGFiZWw+Jyk7DQogICAgICAgICAgICAgICAgICAgICAgICAkZm9ybUVsZW1lbnQuYXBwZW5kKCc8aW5wdXQgY2xhc3M9ImZvcm0tY29udHJvbCIgdHlwZT0iJytmaWVsZC50eXBlKyciIG5hbWU9IicrZmllbGQubmFtZSsnIiB2YWx1ZT0iJytmaWVsZC52YWx1ZSsnIj48L2Rpdj4nKTsNCiAgICAgICAgICAgICAgICAgICAgICAgICRmb3JtLmFwcGVuZCgkZm9ybUVsZW1lbnQpOw0KICAgICAgICAgICAgICAgICAgICB9DQogICAgICAgICAgICAgICAgfQ0KICAgICAgICAgICAgICAgIC8vZm9ybUJvZHkuYXBwZW5kKCc8L2Zvcm0+Jyk7DQogICAgICAgICAgICAgICAgZm9ybUJvZHkuYXBwZW5kKCRmb3JtKTsNCiAgICAgICAgICAgIH0NCiAgICAgICAgICAgIGlmKGRhdGEudGFibGUpew0KICAgICAgICAgICAgICAgIGZvcm1Cb2R5LmFwcGVuZCgnPHRhYmxlIGlkPSJkYXRhVGFibGUiIGNsYXNzPSJkaXNwbGF5IiBzdHlsZT0iZm9udC1zaXplOiAxMnB4OyI+PC90YWJsZT4nKTsNCg0KICAgICAgICAgICAgICAgIHRhYmxlT3JkZXIgPSBbWzAsImFzYyJdXQ0KDQogICAgICAgICAgICAgICAgaWYoZGF0YS5oYXNPd25Qcm9wZXJ0eSgidGFibGVPcmRlciIpKXsNCiAgICAgICAgICAgICAgICAgICAgdGFibGVPcmRlciA9IGRhdGEudGFibGVPcmRlcjsNCiAgICAgICAgICAgICAgICB9DQoNCiAgICAgICAgICAgICAgICBpZihkYXRhLmhhc093blByb3BlcnR5KCd0YWJsZUZvcm1hdHMnKSkgew0KICAgICAgICAgICAgICAgICAgICBmb3IoIHZhciBpPTA7aTxkYXRhLnRhYmxlRm9ybWF0cy5sZW5ndGg7aSsrKSB7DQoNCiAgICAgICAgICAgICAgICAgICAgICAgIHN3aXRjaChkYXRhLnRhYmxlRm9ybWF0c1tpXS50eXBlKSB7DQogICAgICAgICAgICAgICAgICAgICAgICAgICAgY2FzZSAnZGF0ZSc6DQogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGRhdGEudGFibGVDb2x1bW5zW2ldWydyZW5kZXInXSA9IHJlbmRlckRhdGU7DQogICAgICAgICAgICAgICAgICAgICAgICAgICAgYnJlYWs7DQogICAgICAgICAgICAgICAgICAgICAgICAgICAgY2FzZSAnc2l6ZSc6DQogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGRhdGEudGFibGVDb2x1bW5zW2ldWydyZW5kZXInXSA9IHJlbmRlclNpemU7DQogICAgICAgICAgICAgICAgICAgICAgICAgICAgYnJlYWs7DQogICAgICAgICAgICAgICAgICAgICAgICB9DQogICAgICAgICAgICAgICAgICAgIH0NCg0KICAgICAgICAgICAgICAgIH0NCiAgICAgICAgICAgICAgICBEVCA9ICQoJyNkYXRhVGFibGUnKS5EYXRhVGFibGUoIHsNCiAgICAgICAgICAgICAgICAgICAgZGF0YTogZGF0YS50YWJsZURhdGEsDQogICAgICAgICAgICAgICAgICAgIGNvbHVtbnM6IGRhdGEudGFibGVDb2x1bW5zLA0KICAgICAgICAgICAgICAgICAgICBvcmRlcjogdGFibGVPcmRlcg0KICAgICAgICAgICAgICAgIH0gKTsNCiAgICAgICAgICAgIH0NCiAgICAgICAgfQ0KDQogICAgICAgIGZ1bmN0aW9uIHJlbmRlclNpemUoYnl0ZXMpIHsNCiAgICAgICAgICAgIHNpID0gdHJ1ZTsNCiAgICAgICAgICAgIHZhciB0aHJlc2ggPSBzaSA/IDEwMDAgOiAxMDI0Ow0KICAgICAgICAgICAgaWYoTWF0aC5hYnMoYnl0ZXMpIDwgdGhyZXNoKSB7DQogICAgICAgICAgICAgICAgcmV0dXJuIGJ5dGVzICsgJyBCJzsNCiAgICAgICAgICAgIH0NCiAgICAgICAgICAgIHZhciB1bml0cyA9IHNpDQogICAgICAgICAgICAgICAgPyBbJ2tCJywnTUInLCdHQicsJ1RCJywnUEInLCdFQicsJ1pCJywnWUInXQ0KICAgICAgICAgICAgICAgIDogWydLaUInLCdNaUInLCdHaUInLCdUaUInLCdQaUInLCdFaUInLCdaaUInLCdZaUInXTsNCiAgICAgICAgICAgIHZhciB1ID0gLTE7DQogICAgICAgICAgICBkbyB7DQogICAgICAgICAgICAgICAgYnl0ZXMgLz0gdGhyZXNoOw0KICAgICAgICAgICAgICAgICsrdTsNCiAgICAgICAgICAgIH0gd2hpbGUoTWF0aC5hYnMoYnl0ZXMpID49IHRocmVzaCAmJiB1IDwgdW5pdHMubGVuZ3RoIC0gMSk7DQogICAgICAgICAgICByZXR1cm4gYnl0ZXMudG9GaXhlZCgxKSsnICcrdW5pdHNbdV07DQogICAgICAgIH0NCg0KICAgICAgICBmdW5jdGlvbiByZW5kZXJEYXRlKGRhdGEpew0KICAgICAgICAgICAgdmFyIGRhdGUgPSBuZXcgRGF0ZShkYXRhKjEwMDApOw0KICAgICAgICAgICAgcmV0dXJuIGRhdGUuZ2V0TW9udGgoKSArIDEgKyAiLyIgKyBkYXRlLmdldERhdGUoKSArICIvIiArIGRhdGUuZ2V0RnVsbFllYXIoKSArICIgIiArICBkYXRlLmdldEhvdXJzKCkgKyAiOiIgKyBkYXRlLmdldE1pbnV0ZXMoKSArICI6IiArIGRhdGUuZ2V0U2Vjb25kcygpOw0KICAgICAgICB9DQoNCiAgICAgICAgZnVuY3Rpb24gcHJpbnRBbGVydCh0eXBlLCBtc2cpew0KICAgICAgICAgICAgcmV0dXJuICc8ZGl2IGNsYXNzPSJhbGVydCBhbGVydC0nK3R5cGUrJyBhbGVydC1kaXNtaXNzaWJsZSIgcm9sZT0iYWxlcnQiPicNCiAgICAgICAgICAgICAgICArJzxidXR0b24gdHlwZT0iYnV0dG9uIiBjbGFzcz0iY2xvc2UiIGRhdGEtZGlzbWlzcz0iYWxlcnQiIGFyaWEtbGFiZWw9IkNsb3NlIj48c3BhbiBhcmlhLWhpZGRlbj0idHJ1ZSI+JnRpbWVzOzwvc3Bhbj48L2J1dHRvbj4nDQogICAgICAgICAgICAgICAgK21zZysnPC9kaXY+JzsNCiAgICAgICAgfQ0KDQogICAgICAgIGZ1bmN0aW9uIG1ha2VyZXF1ZXN0KGZvcm1kYXRhKXsNCiAgICAgICAgICAgICQoIiNsb2FkaW5nIikuc2hvdygpOw0KICAgICAgICAgICAgJC5wb3N0KCAiIiwgZm9ybWRhdGEsIGZ1bmN0aW9uKGRhdGEsIHN0YXR1cywgeGhyKSB7DQogICAgICAgICAgICAgICAgcHJvY2Vzc0RhdGEoZGF0YSk7DQogICAgICAgICAgICB9KS5mYWlsKGZ1bmN0aW9uKHhocikgew0KICAgICAgICAgICAgICAgICQoIiNsb2FkaW5nIikuaGlkZSgpOw0KICAgICAgICAgICAgICAgIGlmKHhoci5zdGF0dXMgPT0gNDAxKSB7DQogICAgICAgICAgICAgICAgICAgIG1ha2VyZXF1ZXN0KHtsaW5rOiAiL2xvZ2luIn0pOw0KICAgICAgICAgICAgICAgIH0NCiAgICAgICAgICAgIH0pOw0KICAgICAgICB9DQogICAgICAgICQoIiNmb3JtQm9keSIpLm9uKCJzdWJtaXQiLCAiZm9ybSIsIGZ1bmN0aW9uKGUpew0KICAgICAgICAgICAgZS5wcmV2ZW50RGVmYXVsdCgpOw0KICAgICAgICAgICAgdmFyIHN0ciA9ICQoICJmb3JtIiApLnNlcmlhbGl6ZSgpOw0KICAgICAgICAgICAgbWFrZXJlcXVlc3Qoc3RyKTsNCiAgICAgICAgfSk7DQogICAgICAgICQoIi5icmVhZGNydW1iIikub24oImNsaWNrIiwgImEiLCBmdW5jdGlvbihlKXsNCiAgICAgICAgICAgIGUucHJldmVudERlZmF1bHQoKTsNCiAgICAgICAgICAgIG1ha2VyZXF1ZXN0KHtsaW5rIDogJCh0aGlzKS5hdHRyKCJpZCIpIH0pOw0KICAgICAgICB9KTsNCg0KICAgICAgICBmdW5jdGlvbiBzaG93TXlNb2RlbCh0aXRsZSwgZGF0YSl7DQogICAgICAgICAgICAkKCIjbXlNb2RhbExhYmVsIikuaHRtbCh0aXRsZSk7DQogICAgICAgICAgICAkKCIubW9kYWwtYm9keSIpLmh0bWwoZGF0YSk7DQogICAgICAgICAgICAkKCcjbXlNb2RhbCcpLm1vZGFsKCdzaG93Jyk7DQogICAgICAgIH0NCg0KICAgICAgICAkKCIjcXVpY2stc2VhcmNoIikub24oImtleXVwIiwgZnVuY3Rpb24oKXsNCiAgICAgICAgICAgIHZhciBzZWFyY2ggPSAkKCIjcXVpY2stc2VhcmNoIikudmFsKCk7DQogICAgICAgICAgICBpZihzZWFyY2gubGVuZ3RoPDIpDQogICAgICAgICAgICAgICAgJCgiI3F1aWNrLWxpbmtzIikuaHRtbCgiIik7DQogICAgICAgICAgICBlbHNlew0KICAgICAgICAgICAgICAgICQucG9zdCggIiIsIHsgbGluazogIi9xdWljay1zZWFyY2giLCBzdHIgOiBzZWFyY2ggfSApDQogICAgICAgICAgICAgICAgICAgIC5kb25lKGZ1bmN0aW9uKGRhdGEpew0KICAgICAgICAgICAgICAgICAgICAgICAgJCgiI3F1aWNrLWxpbmtzIikuaHRtbCgiIik7DQogICAgICAgICAgICAgICAgICAgICAgICBmb3IgKHZhciBpbmRleCA9IDA7IGluZGV4IDwgZGF0YS5sZW5ndGg7ICsraW5kZXgpIHsNCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAkKCIjcXVpY2stbGlua3MiKS5hcHBlbmQoIjxsaSBjbGFzcz1cImxpc3QtZ3JvdXAtaXRlbSBxdWljay1saW5rLWl0ZW1cIiBpZD0nIitkYXRhW2luZGV4XS5saW5rDQogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICsiJz4iICsgZGF0YVtpbmRleF0ubGFiZWwpOw0KICAgICAgICAgICAgICAgICAgICAgICAgfQ0KICAgICAgICAgICAgICAgICAgICB9KTsNCiAgICAgICAgICAgIH0NCg0KICAgICAgICB9KTsNCg0KICAgICAgICAkKCIjcXVpY2stbGlua3MiKS5vbigiY2xpY2siLCAiLnF1aWNrLWxpbmstaXRlbSIsIGZ1bmN0aW9uKGUpew0KICAgICAgICAgICAgbWFrZXJlcXVlc3Qoe2xpbmsgOiAkKHRoaXMpLmF0dHIoImlkIikgfSk7DQogICAgICAgICAgICAkKCIjcXVpY2stbGlua3MiKS5odG1sKCIiKTsNCiAgICAgICAgICAgICQoIiNxdWljay1zZWFyY2giKS52YWwoIiIpOw0KICAgICAgICB9KTsNCg0KICAgICAgICBtYWtlcmVxdWVzdCh7bGluazoiL2hvbWUifSk7DQoNCiAgICB9KTsNCjwvc2NyaXB0Pg0KPC9ib2R5Pg0KPC9odG1sPg==";
    echo base64_decode($layout);

}


