# Minimal Routing

## Features
- Define hierarchical routes in a simple associative array
- Regex parsing for route parameters
- Predefined http error response codes for invalid entity/item requests, e.g., `PUT /v1/accounts`

## Example
``` PHP
require('php-routing/routing.php');

...

function get_account($account_id) {
  $account_data = ...
  if ($account_data)
    Route\respond($account_data);
  else
    Route\respond(null, Route\RESPONSE_ERROR_NOT_FOUND);
}

...

Route\route([
  'v1' => [
    'accounts' => Route\Entity([
      'POST' => create_account,
      'GET' => fn() => Route\respond('list accounts'),
      '/\d+/' => fn($account_id) => Route\Item([
        'GET' => fn() => get_account($account_id),
        'PUT' => fn() => Route\respond('replace account ' . $account_id),
        'PATCH' => fn() => Route\respond('update account ' . $account_id),
        'DELETE' => fn() => Route\respond('delete account ' . $account_id)
      ])
    ]),
    'feed' => Route\Entity([
      'GET' => fn() => Route\respond('list feed')
    ])
  ]
]);
```
