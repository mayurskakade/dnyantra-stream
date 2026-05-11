<?php
namespace App\Core;

use App\Exceptions\AuthorizationException;
use App\Exceptions\NotFoundException;

class Router {
    private array $routes=[];

    public function add(string $method,string $path,callable $handler,array $middleware=[]): void {
        [$pattern, $paramNames] = $this->compilePattern($path);
        $this->routes[] = [$method,$path,$pattern,$paramNames,$handler,$middleware];
    }
    public function get(string $p,callable $h,array $m=[]): void {$this->add('GET',$p,$h,$m);} 
    public function post(string $p,callable $h,array $m=[]): void {$this->add('POST',$p,$h,$m);} 
    public function put(string $p,callable $h,array $m=[]): void {$this->add('PUT',$p,$h,$m);} 
    public function patch(string $p,callable $h,array $m=[]): void {$this->add('PATCH',$p,$h,$m);} 
    public function delete(string $p,callable $h,array $m=[]): void {$this->add('DELETE',$p,$h,$m);}    

    public function dispatch(Request $request): void {
        foreach ($this->routes as [$m,$p,$pattern,$paramNames,$h,$middlewares]) {
            if ($m===$request->method() && preg_match($pattern, $request->path(), $matches)) {
                $routeParams = [];
                foreach ($paramNames as $index => $name) {
                    $routeParams[$name] = $matches[$index + 1] ?? null;
                }
                $request->setAttribute('route.params', $routeParams);

                foreach ($middlewares as $mw) {
                    $result = $mw($request);
                    if ($result !== true) {
                        $status = is_array($result) ? (int)($result['status'] ?? 401) : 401;
                        $message = is_array($result) ? (string)($result['error'] ?? 'Unauthorized') : 'Unauthorized';
                        throw new AuthorizationException($message, $status);
                    }
                }
                $h($request);
                return;
            }
        }
        throw new NotFoundException('Route not found');
    }

    private function compilePattern(string $path): array {
        $paramNames = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function (array $matches) use (&$paramNames): string {
            $paramNames[] = $matches[1];
            return '([^\/]+)';
        }, $path);

        return ['#^' . $regex . '$#', $paramNames];
    }
}
