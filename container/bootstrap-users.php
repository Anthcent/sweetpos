<?php

require_once '/var/www/html/lib/bootstrap_users.php';

$dbPath = getenv('SWEETPOS_DB_PATH') ?: '/data/database.sqlite';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');

sweetposBootstrapInitialUsers($pdo);
