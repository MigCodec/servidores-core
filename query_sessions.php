<?php
$db = new PDO('sqlite:' . __DIR__ . '/database/database.sqlite');
foreach ($db->query('SELECT id,last_activity,substr(payload,1,120) p FROM sessions ORDER BY last_activity DESC LIMIT 10') as $r) {
    echo $r['id'], ' | ', date('c', (int)($r['last_activity'] ?? 0)), ' | ', $r['p'], PHP_EOL;
}
