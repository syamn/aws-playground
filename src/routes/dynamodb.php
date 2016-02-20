<?php
// DynamoDB Routes

use Aws\DynamoDb\Marshaler;

$app->group('/dynamodb', function(){
	$dynamodb = Base::getSdk()->createDynamoDb();
	$m = new Marshaler();

	/**
	 * Listing all tables
	 */
	$this->get('/tables', function ($request, $response, $args) use ($dynamodb, $m) {
		$args['tables'] = $dynamodb->listTables()['TableNames'];
		return $this->renderer->render($response, 'dynamodb/index.phtml', $args);
	});

	/**
	 * Listing threads
	 */
	$this->get('/threads', function ($request, $response, $args) use ($dynamodb, $m) {
		try {
			$result = $dynamodb->scan([
				'TableName' => 'Threads',
				'FilterExpression' => 'IsDeleted = :v_IsDeleted',
				'ExpressionAttributeValues' => $m->marshalItem([
					':v_IsDeleted' => false
				]),
				'ConsistentRead' => false,
				'ReturnConsumedCapacity' => 'TOTAL',
			]);
		}catch(Exception $ex){
			$args['lines'][] = 'Thread scanning failed..';
			$args['lines'][] = $ex->getMessage();
			return $this->renderer->render($response, 'lines.phtml', $args);
		}

		$args['threads'] = [];
		if ($result['Count'] > 0){
			foreach($result['Items'] as $item){
				$item = $m->unmarshalItem($item);
				$item['PrettyLastPostedTime'] = date('Y-m-d H:i:s', $item['LastPostedTime']);
				$item['PrettyCreatedTime'] = date('Y-m-d H:i:s', $item['CreatedTime']);
				$args['threads'][] = $item;
			}
		}

		$args['consumedCapacity'] = $result['ConsumedCapacity']['CapacityUnits'];
		return $this->renderer->render($response, 'dynamodb/threads.phtml', $args);
	});

	/**
	 * Creating new thread
	 */
	$this->post('/threads', function ($request, $response, $args) use ($dynamodb, $m) {
		$title = $request->getParam('title') ?? 'No Title';
		$body = $request->getParam('body') ?? 'No Body';
		$email = $request->getParam('email') ?? 'anonymous@example.com';
		$now = time();
		$ip = $_SERVER["REMOTE_ADDR"] ?? 'Unknown IP Address';

		// Validate title
		if(!preg_match('/^[^#¥\\`\'"?\t\n]{1,40}$/i', $title)){
			$args['lines'][] = 'Do not use special character or length check failed (1-40 chars) in Title';
			$args['lines'][] = '<a href="/dynamodb/threads">Back to thread list</a>';
			return $this->renderer->render($response, 'lines.phtml', $args);
		}

		try {
			$result = $dynamodb->putItem([
				'TableName' => 'Threads',
				'Item' => $m->marshalItem([
					'ThreadName' => $title, // hash key | LSI (hash key)
					'CreatedTime' => $now, // range key
					'CreatedBy' => $email,
					'LastPostedTime' => $now, //LSI (range key)
					'LastPostedBy' => '(none)',
					'NumPosts' => 0,
					'Body' => $body,
					'Ip' => $ip,
					'IsDeleted' => false,
				]),
				'ReturnConsumedCapacity' => 'TOTAL',
			]);
		}catch(Exception $ex){
			$args['lines'][] = 'Thread creating failed..';
			$args['lines'][] = $ex->getMessage();
			return $this->renderer->render($response, 'lines.phtml', $args);
		}

		$args['lines'][] = 'Thread created successfully!';
		$args['lines'][] = 'Consumed capacity is ' . $result['ConsumedCapacity']['CapacityUnits'];
		$args['lines'][] = '<a href="/dynamodb/threads">Back to thread list</a>';
		return $this->renderer->render($response, 'lines.phtml', $args);
	});

	/**
	 * Listing posts
	 */
	$this->get('/threads/{title}/{createdTime}', function ($request, $response, $args) use ($dynamodb, $m) {
		// Load thread
		try {
			$result = $dynamodb->getItem([
				'TableName' => 'Threads',
				'Key' => $m->marshalItem([
					'ThreadName' => $args['title'],
					'CreatedTime' => (int)$args['createdTime'],
				])
			]);
		}catch(Exception $ex){
			$args['lines'][] = 'Thread checking failed..';
			$args['lines'][] = $ex->getMessage();
			return $this->renderer->render($response, 'lines.phtml', $args);
		}

		if (is_null($result['Item'])){
			$args['lines'][] = '404 Not Found';
			return $this->renderer->render($response, 'lines.phtml', $args);
		}

		$thread = $m->unmarshalItem($result['Item']);
		if ($thread['IsDeleted'] === true){
			$args['lines'][] = '403 Access Denied';
			return $this->renderer->render($response, 'lines.phtml', $args);
		}

		$args['body'] = $thread['Body'];


		// Load posts
		try {
			$result = $dynamodb->query([
				'TableName' => 'Posts',
				'KeyConditionExpression' => 'Thread = :v_Thread',
				'FilterExpression' => 'IsDeleted = :v_IsDeleted',
				'ExpressionAttributeValues' => $m->marshalItem([
					':v_Thread' => $args['title'] . '#' . (int)$args['createdTime'],
					':v_IsDeleted' => false,
				]),
				'ConsistentRead' => false,
				'ReturnConsumedCapacity' => 'TOTAL',
			]);
		}catch(Exception $ex){
			$args['lines'][] = 'Posts scanning failed..';
			$args['lines'][] = $ex->getMessage();
			return $this->renderer->render($response, 'lines.phtml', $args);
		}

		$args['posts'] = [];
		if ($result['Count'] > 0){
			foreach($result['Items'] as $item){
				$item = $m->unmarshalItem($item);
				$item['PrettyPostedTime'] = date('Y-m-d H:i:s', $item['PostedTime']);
				$args['posts'][] = $item;
			}
		}

		$args['consumedCapacity'] = $result['ConsumedCapacity']['CapacityUnits'];
		return $this->renderer->render($response, 'dynamodb/posts.phtml', $args);
	});

	/**
	 * Posting new message
	 */
	$this->post('/threads/{title}/{createdTime}', function ($request, $response, $args) use ($dynamodb, $m) {
		// TODO check is thread existed and available?
		$body = $request->getParam('body') ?? 'No Body';
		$email = $request->getParam('email') ?? 'anonymous@example.com';
		$now = time();
		$ip = $_SERVER["REMOTE_ADDR"] ?? 'Unknown IP Address';

		// Put in Posts table
		try {
			$result = $dynamodb->putItem([
				'TableName' => 'Posts',
				'Item' => $m->marshalItem([
					'Thread' => $args['title'] . '#' . $args['createdTime'], // hash key
					'PostedTime' => $now, // range key
					'PostedBy' => $email,
					'Body' => $body,
					'Ip' => $ip,
					'IsDeleted' => false,
				]),
				'ReturnConsumedCapacity' => 'TOTAL',
			]);
		}catch(Exception $ex){
			$args['lines'][] = 'Posting failed..';
			$args['lines'][] = $ex->getMessage();
			return $this->renderer->render($response, 'lines.phtml', $args);
		}

		// Update Threads table (w/ atomic counter)
		// TODO implement it with transaction architecture
		try {
			$result = $dynamodb->updateItem([
				'TableName' => 'Threads',
				'Key' => $m->marshalItem([
					'ThreadName' => $args['title'],
					'CreatedTime' => (int)$args['createdTime'],
				]),
				'UpdateExpression' => 'set LastPostedBy = :v_LastPostedBy, LastPostedTime = :v_LastPostedTime, NumPosts = NumPosts + :v_plus_NumPosts',
				'ExpressionAttributeValues' => $m->marshalItem([
					':v_LastPostedBy' => $email,
					':v_LastPostedTime' => $now,
					':v_plus_NumPosts' => 1,
				]),
//				'ReturnValues' => 'ALL_NEW',
				'ReturnConsumedCapacity' => 'TOTAL',
			]);
		}catch(Exception $ex){
			$args['lines'][] = 'Updating thread info failed..';
			$args['lines'][] = $ex->getMessage();
			return $this->renderer->render($response, 'lines.phtml', $args);
		}

		$args['lines'][] = 'Sent successfully!';
		$args['lines'][] = 'Consumed capacity is ' . $result['ConsumedCapacity']['CapacityUnits'];
		$args['lines'][] = '<a href="/dynamodb/threads/' . $args['title'] . '/' . $args['createdTime'] . '">Back to the thread</a>';
		return $this->renderer->render($response, 'lines.phtml', $args);
	});

	/**
	 * Initialize tables
	 */
	$this->get('/init', function ($request, $response, $args) use ($dynamodb, $m) {
		$exists = $dynamodb->listTables()['TableNames'];
		$args['lines'] = [];

		// --- Creating Threads ------
		$name = 'Threads';
		if (in_array($name, $exists)){
			$args['lines'][] = "Table '$name' already existed!";
		}else {
			$args['lines'][] = "Creating Table '$name' ...";
			$result = $dynamodb->createTable([
				'TableName' => $name,
				'AttributeDefinitions' => [
					['AttributeName' => 'ThreadName', 'AttributeType' => 'S'],
					['AttributeName' => 'CreatedTime', 'AttributeType' => 'N'],
					['AttributeName' => 'LastPostedTime', 'AttributeType' => 'N'],
				],
				'KeySchema' => [
					['AttributeName' => 'ThreadName', 'KeyType' => 'HASH'],
					['AttributeName' => 'CreatedTime', 'KeyType' => 'RANGE'],
				],
				'LocalSecondaryIndexes' => [
					[
						'IndexName' => 'ThreadName-CreatedTime',
						'KeySchema' => [
							['AttributeName' => 'ThreadName', 'KeyType' => 'HASH'],
							['AttributeName' => 'LastPostedTime', 'KeyType' => 'RANGE'],
						],
						'Projection' => ['ProjectionType' => 'ALL'],
					],
				],
				'ProvisionedThroughput' => ['ReadCapacityUnits' => 5, 'WriteCapacityUnits' => 5]
			]);
			$dynamodb->waitUntil('TableExists', ['TableName' => $name, '@waiter' => ['delay' => 5, 'maxAttempts' => 20]]);
			$args['lines'][] = 'Table ' . $name . ' has been created!';
		}

		// --- Creating Posts ------
		$name = 'Posts';
		if (in_array($name, $exists)){
			$args['lines'][] = "Table '$name' already existed!";
		}else {
			$args['lines'][] = "Creating Table '$name' ...";
			$result = $dynamodb->createTable([
				'TableName' => $name,
				'AttributeDefinitions' => [
					['AttributeName' => 'Thread', 'AttributeType' => 'S'],
					['AttributeName' => 'PostedTime', 'AttributeType' => 'N'],
				],
				'KeySchema' => [
					['AttributeName' => 'Thread', 'KeyType' => 'HASH'],
					['AttributeName' => 'PostedTime', 'KeyType' => 'RANGE'],
				],
				'ProvisionedThroughput' => ['ReadCapacityUnits' => 5, 'WriteCapacityUnits' => 5]
			]);
			$dynamodb->waitUntil('TableExists', ['TableName' => $name, '@waiter' => ['delay' => 5, 'maxAttempts' => 20]]);
			$args['lines'][] = 'Table ' . $name . ' has been created!';
		}

		$args['lines'][] = '<a href="/dynamodb/threads">Move to thread list</a>';
		return $this->renderer->render($response, 'lines.phtml', $args);
	});
});

