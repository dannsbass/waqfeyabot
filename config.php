<?php
require(__DIR__ . '/vendor/autoload.php');
require(__DIR__ . '/simple_html_dom.php');
require(__DIR__ . '/func.php');
require(__DIR__ . '/token.php');

define('DBDUMP', "dbdump.txt");
define('SEPARATOR_DBDUMP', "\n-—\n");
define('DATABASE_WAQFEYA', 'database.waqfeya');
define('SEPARATOR_DATABASE_WAQFEYA', "\n\n###\n\n");

Bot::setToken(TOKEN_BOT, NAMA_BOT);
Bot::setAdmin(ADMIN_ID);
