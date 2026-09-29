<?php
namespace App\utils;
final class Router {
    private array $routes=[];
    public function add(string $method,string $pattern,callable $handler): void {$this->routes[]=[$method,$pattern,$handler];}
    public function get(string $p,callable $h):void{$this->add('GET',$p,$h);} public function post(string $p,callable $h):void{$this->add('POST',$p,$h);} public function put(string $p,callable $h):void{$this->add('PUT',$p,$h);} public function patch(string $p,callable $h):void{$this->add('PATCH',$p,$h);} public function delete(string $p,callable $h):void{$this->add('DELETE',$p,$h);}
    public function dispatch(string $method,string $path): never {
        foreach($this->routes as [$m,$pattern,$handler]){
            if($m!==$method)continue;
            $regex=preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#','(?P<$1>[^/]+)',$pattern);
            if(preg_match('#^'.$regex.'$#',$path,$matches)){
                $params=[];foreach($matches as $k=>$v)if(is_string($k))$params[$k]=$v;
                $result=$handler($params); if(is_array($result)) Response::json($result); Response::json([]);
            }
        }
        Response::json(['error'=>'Rota não encontrada.'],404);
    }
}
