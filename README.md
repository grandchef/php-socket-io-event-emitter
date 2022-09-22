PHP Socket Client 
=================
v.0.1.2

Fork from : ```https://github.com/psinetron/PHP_SocketIO_Client```


***install***


```
php composer require nextqs/php-socket-io-event-emitter
```

**1 - YII2 Framework**
   -------------

***web.php***
```
$config = [
    ...
    'components' => [
        ...,
        //socketioClient
        'socketioClient' => [
            'class' => 'nextqs\socketio\SocketIO',
        ],
        ...
    ],
]

```
***Use Component***
```
// Create socketio client
$objClient = new \Yii::$app->socketioClient("localhost", 8080);
// set query params
$objClient->setQueryParams([
      'param1' => 'value'
   ]);
// set namespace
$objClient->setNamespace('worker');
// send data
$success = $objClient->emit('msg', [
    'action' => 'test_action',
    'payload' => [
        'data' => [
            'key' => 'value
        ]
    ]
]);

return $success;
```

   **2 - TESTS**
   -------------

***Php***
```php

require_once '../src/SocketIO.php';

$client = new SocketIO('localhost', 9001);

$client->setNamespace('worker');

//connection handshake query( for auth - optional)
$client->setQueryParams([
    'token' => 'edihsudshuz',
    'id' => '8780',
    'cid' => '344',
    'cmp' => 2339
]);

$success = $client->emit('eventFromPhp', [
    'name' => 'Goku',
    'age' => '23',
    'address' => 'Sudbury, On, Canada'
]);

if(!$success)
{
    var_dump($client->getErrors());
}
else{
    var_dump("Success");
}

```

***Node Js***


```js

var app = require('http').createServer(handler)
   var io = require('socket.io')(app);
   var fs = require('fs');

   const nspWorker = io.of('/worker')
   
   app.listen(9001);
   
   function handler (req, res) {
       res.writeHead(200);
       res.end('Hello Word');
   }

   nspWorker.on('connection', function (socket) {
   
       console.log("New Connection with transport", socket.conn.transport.name);
   
       console.log('With handshake', socket.handshake);
   
   
       console.log('With query', socket.handshake.query);
   
       socket.on('eventFromPhp', function (data) {
           console.log('Data from Php', data, JSON.parse(data));
       });
   });
   
```
   
   **3 - API**
   -------------
***.```setMaxRetry(n)```***
```
$client->setMaxRetry(10);//default 5
```

***.```setRetryInterval(interval)```***
```
$client->setRetryInterval(100);// 100 ms, default 200
```


***.```setProtocol(protocol)```***
```
$client->setProtocol(SocketIO::NO_SECURE_PROTOCOLE);
$client->setProtocol(SocketIO::TLS_PROTOCOLE);
$client->setProtocol(SocketIO::SSL_PROTOCOLE);
```

***.```setHost(host)```***
```
$client->setHost('localhost');
```

***.```setPort(port)```***
```
$client->setPort(80);
```

***.```setPath(path)```***
```
$client->setPath('/socket.io/?EIO=4');
```

***.```setNamespace(namespace)```***
```
$client->setNamespace('worker');
```

