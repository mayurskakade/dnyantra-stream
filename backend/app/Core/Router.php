<?php
namespace App\Core;

class Router {
    private array $routes=[];
    public function add(string $method,string $path,callable $handler): void { $this->routes[] = [$method,$path,$handler]; }
    public function get(string $p,callable $h): void {$this->add('GET',$p,$h);} public function post(string $p,callable $h): void {$this->add('POST',$p,$h);} public function put(string $p,callable $h): void {$this->add('PUT',$p,$h);} public function patch(string $p,callable $h): void {$this->add('PATCH',$p,$h);} public function delete(string $p,callable $h): void {$this->add('DELETE',$p,$h);}    
    public function dispatch(Request $request): void {
        foreach ($this->routes as [$m,$p,$h]) { if ($m===$request->method() && $p===$request->path()) { $h($request); return; } }
        Response::json(['error'=>'Not Found'],404);
    }
}
