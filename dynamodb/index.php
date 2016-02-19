<?php
require_once __DIR__ . '/../_library/initialize.php';

$dynamodb = Base::getSdk()->createDynamoDb();


$tables = $dynamodb->listTables()['TableNames'];
echo "Show tables..";

?>

List all tables

<ul>
<?php foreach($tables as $tbl): ?>
	<li><?php echo h($tbl); ?></li>
<?php endforeach; ?>
</ul>
