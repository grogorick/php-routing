<?php namespace Grogorick\PhpRouting;

class Options
{
  /** Values: { 'json', 'text' } */
  const REQUEST_CONTENT_TYPE = 'request-content-type';
  /** Values: { true, false } */
  const RESPONSE_INCLUDE_REQUEST = 'response-include-request';
  /** Values: { true, false } */
  const IGNORE_HTTP_RESPONSE_STATUS_CODES = 'ignore-http-response-status-codes';
}

class Response
{
  const OK = 200;

  const BAD_REQUEST = 400;
  const NOT_FOUND = 404;
  const NOT_ALLOWED = 405;

  const INTERNAL_SERVER_ERROR = 500;
  const NOT_IMPLEMENTED = 501;
}


class Routing
{
  public $METHOD = null;
  public $DATA = null;
  public $REQUEST = null;

  public $HEADERS = [
    'Content-Type: application/json; charset=UTF-8',
    'Access-Control-Allow-Origin: *'
  ];
  public $OPTIONS = [
    Options::REQUEST_CONTENT_TYPE => 'json',
    Options::RESPONSE_INCLUDE_REQUEST => false,
    Options::IGNORE_HTTP_RESPONSE_STATUS_CODES => false
  ];

  public static $INST;
  public $prepared = false;
}
Routing::$INST = new Routing;


function prepare()
{
  if (Routing::$INST->prepared)
    return;
  Routing::$INST->prepared = true;

  Routing::$INST->REQUEST = preg_split('@/@', $_SERVER['PATH_INFO'] ?? $_GET['request'], -1, PREG_SPLIT_NO_EMPTY);
  Routing::$INST->METHOD = $_SERVER['REQUEST_METHOD'];

  Routing::$INST->DATA = file_get_contents('php://input');
  switch (Routing::$INST->METHOD) {
    case 'POST':
      if (!empty($_POST))
        Routing::$INST->DATA = $_POST;
      break;
    case 'GET':
      if (!empty($_GET))
        Routing::$INST->DATA = $_GET;
      break;
    // case 'PUT':
    // case 'PATCH':
    // case 'DELETE':
    // default:
    //   break;
  }

  if (Routing::$INST->OPTIONS[Options::REQUEST_CONTENT_TYPE] === 'json') {
    if (strpos(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') === false)
      respond('Request content-type must be application/json.', Response::BAD_REQUEST);
    Routing::$INST->DATA = json_decode(Routing::$INST->DATA, true);
  }
  else
    parse_str(Routing::$INST->DATA, Routing::$INST->DATA);
}


function Entity($actions)
{
  return array_merge_keep_first_values($actions, [
      /* C */'POST' => fn() => respond('Create route does not exist for this entity.', Response::NOT_IMPLEMENTED),
      /* R */'GET' => fn() => respond('List route does not exist for all instances of this entity.', Response::NOT_IMPLEMENTED),
      /* U */'PUT' => fn() => respond('Replace route does not exist for all instances of this entity.', Response::NOT_ALLOWED),
      /* U */'PATCH' => fn() => respond('Update route does not exist for all instances of this entity.', Response::NOT_ALLOWED),
      /* D */'DELETE' => fn() => respond('Delete route does not exist for all instances of this entity.', Response::NOT_ALLOWED)
    ]);
}

function Item($actions)
{
  return array_merge_keep_first_values($actions, [
      /* C */'POST' => fn() => respond('Create route does not exist for specific instances of this entity.', Response::NOT_ALLOWED),
      /* R */'GET' => fn() => respond('Get route does not exist for instances of this entity.', Response::NOT_IMPLEMENTED),
      /* U */'PUT' => fn() => respond('Replace route does not exist for instances of this entity.', Response::NOT_IMPLEMENTED),
      /* U */'PATCH' => fn() => respond('Update route does not exist for instances of this entity.', Response::NOT_IMPLEMENTED),
      /* D */'DELETE' => fn() => respond('Delete route does not exist for instances of this entity.', Response::NOT_IMPLEMENTED)
    ]);
}


function set_options($options)
{
  Routing::$INST->OPTIONS = array_merge(Routing::$INST->OPTIONS, $options);
}


function set_response_headers($headers)
{
  Routing::$INST->HEADERS = $headers;
}

function add_response_header($header)
{
  Routing::$INST->HEADERS[] = $header;
}


function respond($response, $code = Response::OK)
{
  if (!headers_sent($filename, $linenum)) {
    foreach (Routing::$INST->HEADERS as $header)
      header($header);

    if (!Routing::$INST->OPTIONS[Options::IGNORE_HTTP_RESPONSE_STATUS_CODES])
      http_response_code($code);
  }

  if (!is_null($response)) {
    $out = [ ((200 <= $code && $code <= 299) ? 'response' : 'error') => $response ];

    if (Routing::$INST->OPTIONS[Options::RESPONSE_INCLUDE_REQUEST])
      $out['request'] = implode('/', Routing::$INST->REQUEST) . ' : ' . Routing::$INST->METHOD;

    echo json_encode($out, JSON_UNESCAPED_SLASHES);
  }
  exit;
}


function route($routes)
{
  prepare();
  $current_request = current(Routing::$INST->REQUEST);

  if ($current_request === false) {
    foreach ($routes as $route => &$action) {
      if (preg_match('/^[A-Z]+$/', $route)) {
        if ($route === Routing::$INST->METHOD) {
          if (is_callable($action))
            call_user_func($action, Routing::$INST->DATA);
          else
            respond('Route configuration invalid.', Response::INTERNAL_SERVER_ERROR);
          exit;
        }
      }
    }
    respond('Method does not exist for this route.', Response::BAD_REQUEST);
  }

  foreach ($routes as $route => &$subroutes) {
    if ($route === $current_request) {
      next(Routing::$INST->REQUEST);
      route($subroutes);
      exit;
    }
    else if (str_starts_with($route, '/') && str_ends_with($route, '/') && preg_match($route, $current_request, $matches)) {
        $arg = $matches[0];
        next(Routing::$INST->REQUEST);
        route(call_user_func($subroutes, $arg));
        exit;
    }
  }
  respond('Route does not exist.', Response::NOT_FOUND);
}


function array_merge_keep_first_values($arr1, $arr2)
{
  foreach ($arr2 as $key2 => $val2)
    if (!array_key_exists($key2, $arr1))
      $arr1[$key2] = $val2;
  return $arr1;
}
