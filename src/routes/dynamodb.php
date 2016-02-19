<?php
// Routes

$app->get('/dynamodb/[{name}]', function ($request, $response, $args) {

	$dynamodb = Base::getSdk()->createDynamoDb();


	$args['tables'] = $dynamodb->listTables()['TableNames'];
	echo "Show tables..";



// Sample log message
	$this->logger->info("Slim-Skeleton '/' route");
	// Render index view
	return $this->renderer->render($response, 'dynamodb/index.phtml', $args);
});
