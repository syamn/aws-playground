<?php
// Routes

// Load library
require_once __DIR__ . '/library/initialize.php';

// Load all routing files
$routeFiles = (array) glob(__DIR__.'/routes/*.php');
foreach($routeFiles as $routeFile) {
	require $routeFile;
}


// Default route

$app->get('/[{name}]', function ($request, $response, $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});
