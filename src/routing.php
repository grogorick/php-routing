<?php namespace Grogorick\PhpRouting;

class Routing
{
  public $METHOD = null;
  public $DATA = null;
  public $REQUEST = null;

  public $HEADERS = [
    'Content-Type: application/json; charset=UTF-8'
  ];

  public static $INST = null;
}

if (is_null(Routing::$INST)) {
  Routing::$INST = new Routing;

  Routing::$INST->REQUEST = preg_split('@/@', $_SERVER['PATH_INFO'] ?? $_GET['request'], -1, PREG_SPLIT_NO_EMPTY);
  Routing::$INST->METHOD = $_SERVER['REQUEST_METHOD'];

  switch (Routing::$INST->METHOD) {
    case 'POST':
      Routing::$INST->DATA = $_POST;
    case 'GET':
      Routing::$INST->DATA = $_GET;
    // case 'PUT':
    // case 'PATCH':
    // case 'DELETE':
    default:
      parse_str(file_get_contents('php://input'), Routing::$INST->DATA);
      break;
  }
}


const RESPONSE_OK = 200;
const RESPONSE_OK_CREATED = 201;
const RESPONSE_OK_NO_CONTENT = 204;
const RESPONSE_BAD_REQUEST = 400;
const RESPONSE_ERROR_NOT_FOUND = 404;
const RESPONSE_ERROR_NOT_ALLOWED = 405;
const RESPONSE_ERROR_CONFLICT = 409;
const RESPONSE_NOT_IMPLEMENTED = 501;


function Entity($actions)
{
  return array_merge_keep_first_values($actions, [
      /* C */'POST' => fn() => respond(RESPONSE_NOT_IMPLEMENTED),
      /* R */'GET' => fn() => respond(RESPONSE_NOT_IMPLEMENTED),
      /* U */'PUT' => fn() => respond(RESPONSE_ERROR_NOT_ALLOWED),
      /* U */'PATCH' => fn() => respond(RESPONSE_ERROR_NOT_ALLOWED),
      /* D */'DELETE' => fn() => respond(RESPONSE_ERROR_NOT_ALLOWED)
    ]);
}

function Item($actions)
{
  return array_merge_keep_first_values($actions, [
      /* C */'POST' => fn() => respond(RESPONSE_ERROR_NOT_ALLOWED),
      /* R */'GET' => fn() => respond(RESPONSE_NOT_IMPLEMENTED),
      /* U */'PUT' => fn() => respond(RESPONSE_NOT_IMPLEMENTED),
      /* U */'PATCH' => fn() => respond(RESPONSE_NOT_IMPLEMENTED),
      /* D */'DELETE' => fn() => respond(RESPONSE_NOT_IMPLEMENTED)
    ]);
}


function set_response_headers($headers)
{
  Routing::$INST->HEADERS = $headers;
}

function add_response_header($header)
{
  Routing::$INST->HEADERS[] = $header;
}


function respond($response, $code = RESPONSE_OK)
{
  foreach (Routing::$INST->HEADERS as $header)
    header($header);

  http_response_code($code);

  if (!is_null($response))
    echo json_encode([
      'request' => [Routing::$INST->METHOD => Routing::$INST->REQUEST],
      'response' => $response
    ]);
  exit;
}


function route($routes)
{
  $current_request = current(Routing::$INST->REQUEST);

  if ($current_request === false) {
    foreach ($routes as $route => &$action) {
      if (preg_match('/^[A-Z]+$/', $route)) {
        if ($route === Routing::$INST->METHOD) {
          $action(Routing::$INST->DATA);
          exit;
        }
      }
    }
    // method not defined for this route
    respond(null, RESPONSE_BAD_REQUEST);
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
        route($subroutes($arg));
        exit;
    }
  }
  // route not defined
  respond(null, RESPONSE_BAD_REQUEST);
}


function array_merge_keep_first_values($arr1, $arr2)
{
  foreach ($arr2 as $key2 => $val2)
    if (!array_key_exists($key2, $arr1))
      $arr1[$key2] = $val2;
  return $arr1;
}
