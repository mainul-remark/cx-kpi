<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Route;
use Uzzal\Acl\Models\Resource;

class AclResourceSeeder extends Seeder
{
    /**
     * Seed the ACL resources from application controller routes.
     */
    public function run(): void
    {
        $timestamp = now();
        $rows = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();

            if (!is_string($action) || $action === 'Closure') {
                continue;
            }

            if (!str_starts_with($action, 'App\\Http\\Controllers\\')) {
                continue;
            }

            $actionParts = explode('@', $action, 2);
            if (count($actionParts) === 2) {
                [$controllerClass, $method] = $actionParts;
            } else {
                $controllerClass = $actionParts[0];
                $method = '__invoke';
            }

            $controller = $this->formatControllerName($controllerClass);

            if ($controller === '') {
                continue;
            }

            $resourceId = sha1($action);
            $rows[$resourceId] = [
                'resource_id' => $resourceId,
                'name' => $controller . ' ' . $this->formatHttpMethod($route->methods()) . '::' . ucfirst($method),
                'controller' => $controller,
                'action' => $action,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if ($rows === []) {
            return;
        }

        Resource::query()->upsert(
            array_values($rows),
            ['resource_id'],
            ['name', 'controller', 'action', 'updated_at']
        );
    }

    private function formatControllerName(string $controllerClass): string
    {
        $controller = str_replace('App\\Http\\Controllers\\', '', $controllerClass);
        $controller = preg_replace('/Controller$/', '', $controller) ?? '';

        return str_replace('\\', '-', $controller);
    }

    private function formatHttpMethod(array $methods): string
    {
        $methods = array_values(array_filter(
            $methods,
            static fn (string $method) => $method !== 'HEAD' && $method !== 'OPTIONS'
        ));

        if ($methods === []) {
            $methods = ['HEAD'];
        }

        return implode('|', array_map('strtoupper', $methods));
    }
}
