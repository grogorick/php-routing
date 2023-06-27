# Minimal PHP Routing

Set callbacks for hierarchical routes in a simple associative array structure.

## Setup
```bash
composer require grogorick/php-routing
```


## Example
```PHP
# api.php

require_once 'vendor/autoload.php';
use Grogorick\PhpRouting as R;

...

function get_feed($GET_data) {
  $feed_data = ...
  if ($feed_data)
    R\respond($feed_data);
  else
    R\respond(null, R\Response::ERROR_NOT_FOUND);
}

...

R\route([
  'v1' => [
    'accounts' => R\Entity(
    [
      'POST' => create_account,
      'GET' => fn() => R\respond('list accounts'),
      '/\d+/' => fn($account_id) => R\Item(
      [
        /* POST => automatic http response code 405 (not allowed) */
        'GET' => fn() => R\respond('get account ' . $account_id),
        'PUT' => fn() => R\respond('replace account ' . $account_id),
        'PATCH' => fn() => R\respond('update account ' . $account_id),
        'DELETE' => fn() => R\respond('delete account ' . $account_id)
      ]),
      ...
    ]),
    'feed' =>
    [
      'GET' => fn($GET_data) => get_feed($GET_data),
    ],
    ...
  ]
]);
```


## Reference
> `route($routes)`  
  Main function to parse the current request URL and call the corresponding callback function.  
  **$routes** (associative array)  
  — route literal string => subroute array  
  — route parameter regex => closure with parsed parameter as agument, returning a subroute array  
  — request method (POST/GET/PUT/PATCH/DELETE) => callback function

> `respond($response, $code = 200)`  
  Helper function to output the retrieved response as JSON-encoded string, and set an optional status code.
  Stops execution afterwards.  
  **$response** (any) — JSON-serializable response object  
  **$code** (int) — HTTP response status code

> `Entity($subroutes)`  
  `Item($subroutes)`  
  Wrapper for subroutes array to generate recommended response status codes for undefined request methods.  
  **$subroutes** — see *route($routes)*

> `set_response_headers($headers)`  
  `add_response_header($header)`  
  Replace/add headers that are automatically applied when using *respond(...)*.  
  **$headers** (array) — headers to replace all default/previously set headers  
  **$header** (string) — header to add
>
> **Default headers:**  
  "Content-Type: application/json; charset=UTF-8"  
  "Access-Control-Allow-Origin: *"

> `set_options($options)`  
  Set options available in *\Options*.  
  **$options** (associative array) — options to set, using values from *\Options* as array keys


## Server Configuration
### Additional Request Methods
By default, most Apache configurations only allow *GET* and *POST* requests. Add the following to allow further methods (*PUT, PATCH, DELETE, HEAD, OPTIONS*).
```apacheconf
# .htaccess
<Limit GET POST PUT PATCH DELETE HEAD OPTIONS>
    Require all granted
</Limit>
Header always set Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS"
```
For this to work, the `httpd.conf` should include
```apacheconf
<VirtualHost ...>
    <Directory ...>
        ...
        AllowOverride All
        ...
```


### URL Syntax
#### Short
https<span>://</span>your-api.domain **/v1/accounts/42**
```apacheconf
# .htaccess
<IfModule mod_rewrite.c>
    RewriteEngine on
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ /api.php/$1 [L,QSA]
</IfModule>
```

#### Medium
https<span>://</span>your-api.domain **/api.php/v1/accounts/42**  
*(pre-configured on most systems)*
```apacheconf
# httpd.conf
<VirtualHost ...>
    ...
    AcceptPathInfo On
    ...
```
or
```apacheconf
# .htaccess
AcceptPathInfo On
```

#### Long
https<span>://</span>your-api.domain **/api.php?request=/v1/accounts/42**  
*(vanilla PHP)*
