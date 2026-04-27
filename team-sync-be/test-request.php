<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/api/v1/payrolls/generate-readiness', 'GET', ['salary_month' => '2026-0']);
$request->headers->set('Accept', 'application/json');
// Mock user
$user = \App\Models\User::first();
$app['auth']->guard('sanctum')->setUser($user);
$request->headers->set('Authorization', 'Bearer dummy');

$response = $kernel->handle($request);
echo "STATUS: " . $response->getStatusCode() . "\n";
echo "BODY: " . $response->getContent() . "\n";
