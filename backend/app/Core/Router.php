<?php
namespace App\Core;

class Router {
    private array $routes=[];

    public function add(string $method,string $path,callable $handler,array $middleware=[]): void {
        $this->routes[] = [$method,$path,$handler,$middleware];
    }
    public function get(string $p,callable $h,array $m=[]): void {$this->add('GET',$p,$h,$m);} 
    public function post(string $p,callable $h,array $m=[]): void {$this->add('POST',$p,$h,$m);} 
    public function put(string $p,callable $h,array $m=[]): void {$this->add('PUT',$p,$h,$m);} 
    public function patch(string $p,callable $h,array $m=[]): void {$this->add('PATCH',$p,$h,$m);} 
    public function delete(string $p,callable $h,array $m=[]): void {$this->add('DELETE',$p,$h,$m);}    

    public function dispatch(Request $request): void {
        foreach ($this->routes as [$m,$p,$h,$middlewares]) {
            if ($m===$request->method() && $p===$request->path()) {
                foreach ($middlewares as $mw) {
                    $result = $mw($request);
                    if ($result !== true) {
                        $status = is_array($result) ? (int)($result['status'] ?? 401) : 401;
                        $message = is_array($result) ? (string)($result['error'] ?? 'Unauthorized') : 'Unauthorized';
                        Response::json(['error' => $message], $status);
                        return;
                    }
                }
                $h($request);
                return;
            }
        }
        Response::json(['error'=>'Not Found'],404);
    }
}
