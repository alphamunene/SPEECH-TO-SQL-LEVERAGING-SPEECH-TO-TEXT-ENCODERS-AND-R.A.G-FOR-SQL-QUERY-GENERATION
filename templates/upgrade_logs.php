<?php
$db = new SQLite3('logs.db');
$db->exec("ALTER TABLE logs ADD COLUMN details TEXT;");
echo "Updated.";
?>
