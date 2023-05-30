# GrandChef Socket.IO Client
Biblioteca de conexão com socket.io

```
php composer require grandchef/php-socket-io-event-emitter
```

## Exemplo
```
$client = new SocketIO('beta.grandchef.com.br', 443, '/ws/?EIO=4');
$client->connect();
$client->emit('sync', ['source' => 'order']);
$client->close();
```