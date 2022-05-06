# Sobana
A non-blocking TCP networking library for PocketMine-MP

## Example
- <a href="https://github.com/AkmalFairuz/SobanaHttp">SobanaHttp</a> - Example Sobana HTTP server

## Creating TCP Server
- Creating Server
```php
$server = Sobana::createServer("0.0.0.0", 8080, MySession::class);
$server->start();
```
- Creating Session
```php
class MySession extends ServerSession{

    public function handlePacket(string $packet): void{
        $this->write("HTTP/1.1 200 OK\r\nContent-Length: 11\r\n\r\nHello World");
        $this->close();
    }
}
```
- Creating Encoder/Decoder<br/>
<a href="#example">See example plugin</a>