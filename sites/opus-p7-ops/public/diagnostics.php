<?php
declare(strict_types=1);
function p7diag_e(mixed $v): string{if(is_array($v)){$v=json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'';}if(is_bool($v)){$v=$v?'true':'false';}if($v===null){$v='null';}return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$root=dirname(__DIR__,3);
$site=(string)($_GET['site']??'site-alpha');
$autoload=$root.'/vendor/autoload.php';
$public=__DIR__;
$files=[
 'index.php'=>is_file($public.'/index.php'),
 'router.php'=>is_file($public.'/router.php'),
 'action.php'=>is_file($public.'/action.php'),
 'command.php'=>is_file($public.'/command.php'),
 'navigation.php'=>is_file($public.'/navigation.php'),
 'diagnostics.php'=>is_file($public.'/diagnostics.php'),
 'ops-ui.css'=>is_file($public.'/ops-ui.css'),
 'README.md'=>is_file(dirname(__DIR__).'/README.md'),
 'vendor/autoload.php'=>is_file($autoload),
];
if($files['vendor/autoload.php']){require $autoload;}
$operations=[];$counters=[];$vmOk=false;$vmError=null;
try{
 $factory=new \OpusLstsarManager\View\LstsarManagerViewModelFactory();
 $controller=new \OpusLstsarManager\Controller\OperationsController($factory);
 $vm=$controller->operations($site);
 $dashboard=is_array($vm['operations_dashboard']??null)?$vm['operations_dashboard']:[];
 $operations=is_array($dashboard['operations']??null)?$dashboard['operations']:[];
 $counters=is_array($dashboard['counters']??null)?$dashboard['counters']:[];
 $vmOk=true;
}catch(Throwable $e){$vmError=get_class($e).': '.$e->getMessage();}
$routes=[
 '/opus-lstsar-manager',
 '/opus-lstsar-manager/operations',
 '/opus-lstsar-manager/action',
 '/opus-lstsar-manager/command',
 '/opus-lstsar-manager/command-center',
 '/opus-lstsar-manager/navigation',
 '/opus-lstsar-manager/navigation-polish',
 '/opus-lstsar-manager/diagnostics',
 '/opus-lstsar-manager/runtime-diagnostics',
];
$report=[
 'contract'=>'P7_OPS_RUNTIME_DIAGNOSTICS_CORE',
 'php_version'=>PHP_VERSION,
 'sapi'=>PHP_SAPI,
 'site'=>$site,
 'composer_autoload'=>$files['vendor/autoload.php'],
 'public_files'=>$files,
 'routes'=>$routes,
 'operations_view_model_ok'=>$vmOk,
 'operation_count'=>count($operations),
 'counters'=>$counters,
 'error'=>$vmError,
 'side_effects'=>false,
];
?><!doctype html><html lang="fr"><head><meta charset="utf-8"><title>OPUS OPS Runtime Diagnostics</title><link rel="stylesheet" href="/ops-ui.css" data-contract="P7_OPS_RUNTIME_DIAGNOSTICS_CORE"><style>body{margin:0;background:#07111f;color:#e7eefc;font-family:Segoe UI,Arial,sans-serif}main{max-width:1200px;margin:auto;padding:32px}a{color:#69e3ff}.panel{background:#0b1728;border:1px solid #29405f;border-radius:18px;padding:20px;margin:18px 0}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.card{background:#030813;border:1px solid #29405f;border-radius:14px;padding:14px}.card strong{display:block;color:#69e3ff;font-size:1.4rem}.ok{color:#7dffb2}.fail{color:#ff8fa3}pre{white-space:pre-wrap;background:#030813;border:1px solid #29405f;border-radius:14px;padding:14px;color:#ffdf99}@media(max-width:900px){.grid{grid-template-columns:1fr}}</style></head><body><main>
<nav class="ops-main-nav p7-ops-runtime-diagnostics" data-contract="P7_OPS_RUNTIME_DIAGNOSTICS_CORE"><a href="/opus-lstsar-manager?site=<?=p7diag_e($site)?>">Dashboard</a><a href="/opus-lstsar-manager/operations?site=<?=p7diag_e($site)?>">Operations</a><a href="/opus-lstsar-manager/command?site=<?=p7diag_e($site)?>">Command Center</a><a href="/opus-lstsar-manager/navigation?site=<?=p7diag_e($site)?>">Navigation</a><a href="/opus-lstsar-manager/diagnostics?site=<?=p7diag_e($site)?>">Diagnostics</a><a href="/opus-lstsar-manager/health?site=site-alpha">Health Hub</a></nav>
<section class="panel"><h1>OPUS OPS Runtime Diagnostics</h1><p><span class="ops-badge">P7_OPS_RUNTIME_DIAGNOSTICS_CORE</span></p><div class="grid"><div class="card"><strong><?=p7diag_e(PHP_VERSION)?></strong>PHP runtime</div><div class="card"><strong><?=p7diag_e(PHP_SAPI)?></strong>SAPI</div><div class="card"><strong><?=p7diag_e($files['vendor/autoload.php'])?></strong>Composer autoload</div><div class="card"><strong><?=p7diag_e(count($operations))?></strong>Operations view-model</div></div></section>
<section class="panel"><h2>Public files</h2><pre><?=p7diag_e($files)?></pre></section>
<section class="panel"><h2>Route checks</h2><pre><?=p7diag_e($routes)?></pre></section>
<section class="panel"><h2>Operations view-model</h2><pre><?=p7diag_e(['ok'=>$vmOk,'operation_count'=>count($operations),'counters'=>$counters,'error'=>$vmError])?></pre></section>
<section class="panel"><h2>Diagnostics payload</h2><pre><?=p7diag_e(json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'{}')?></pre></section>
</main></body></html>
