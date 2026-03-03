<?php
require __DIR__ . '/vendor/autoload.php';
\ = new think\App();
\->initialize();
\ = think\facade\Db::getPdo();
echo '=== content_templates ===' . PHP_EOL;
\ = \->query('DESCRIBE xmt_content_templates');
while(\ = \->fetch(PDO::FETCH_ASSOC)) { echo \['Field'] . ' | ' . \['Type'] . ' | ' . \['Default'] . PHP_EOL; }
echo PHP_EOL . '=== publish_tasks ===' . PHP_EOL;
try { \ = \->query('DESCRIBE xmt_publish_tasks'); while(\ = \->fetch(PDO::FETCH_ASSOC)) { echo \['Field'] . ' | ' . \['Type'] . PHP_EOL; } } catch(Exception \) { echo 'TABLE NOT EXISTS' . PHP_EOL; }
echo PHP_EOL . '=== content_tasks ===' . PHP_EOL;
try { \ = \->query('DESCRIBE xmt_content_tasks'); while(\ = \->fetch(PDO::FETCH_ASSOC)) { echo \['Field'] . ' | ' . \['Type'] . PHP_EOL; } } catch(Exception \) { echo 'TABLE NOT EXISTS' . PHP_EOL; }
