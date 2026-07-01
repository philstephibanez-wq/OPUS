<?php
declare(strict_types=1);
$root=getcwd();
$files=[
 'sites/opus-p7-ops/public/command.php',
 'sites/opus-p7-ops/public/router.php',
 'sites/opus-p7-ops/README.md',
];
foreach($files as $f){if(!is_file($root.'/'.$f)){fwrite(STDERR,'P7_OPS_COMMAND_CENTER_FILE_MISSING: '.$f.PHP_EOL);exit(1);}}
$readme=$root.'/sites/opus-p7-ops/README.md';
$s=file_get_contents($readme);
if($s===false){fwrite(STDERR,'P7_OPS_COMMAND_CENTER_README_READ_FAILED'.PHP_EOL);exit(1);}
if(!str_contains($s,'P7_OPS_COMMAND_CENTER_CORE')){
 $s=rtrim($s).PHP_EOL.PHP_EOL.'## P7_OPS_COMMAND_CENTER_CORE'.PHP_EOL.PHP_EOL.'- Adds `/opus-lstsar-manager/command` and `/opus-lstsar-manager/command-center`.'.PHP_EOL.'- Provides OPS summary, operations table, quick action links and diagnostics.'.PHP_EOL.'- Keeps command previews read-only with `side_effects=false`.'.PHP_EOL;
 file_put_contents($readme,$s);
}
echo 'P7_OPS_COMMAND_CENTER_CORE_UPDATED'.PHP_EOL;
