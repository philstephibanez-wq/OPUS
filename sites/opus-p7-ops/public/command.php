<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

function cc_e(mixed $v): string { if (is_array($v)) { $v=json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:''; } if (is_bool($v)) { $v=$v?'true':'false'; } if ($v===null) { $v='null'; } return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function cc_id(array $o): string { return (string)($o['operation_id'] ?? $o['id'] ?? ''); }
$root=dirname(__DIR__,3); require $root.'/vendor/autoload.php';
$site=(string)($_GET['site'] ?? 'site-alpha');
$action=(string)($_GET['action'] ?? 'preview');
$modes=['preview'=>'controlled_preview','dry-run'=>'controlled_dry_run','audit'=>'controlled_audit'];
if(!isset($modes[$action])){$action='preview';}
$f=new \OpusLstsarManager\View\LstsarManagerViewModelFactory();
$c=new \OpusLstsarManager\Controller\OperationsController($f);
$vm=$c->operations($site);
$d=is_array($vm['operations_dashboard']??null)?$vm['operations_dashboard']:[];
$counters=is_array($d['counters']??null)?$d['counters']:[];
$ops=is_array($d['operations']??null)?$d['operations']:[];
$first=is_array($ops[0]??null)?$ops[0]:[];
$firstId=cc_id($first);
$result=['contract'=>'P7_OPS_COMMAND_CENTER_CORE','site'=>$site,'action'=>$action,'mode'=>$modes[$action],'operation_id'=>$firstId,'side_effects'=>false,'operation_count'=>count($ops)];
?><!doctype html><html lang="fr"><head><meta charset="utf-8"><title>OPUS OPS Command Center</title><style>body{margin:0;background:#07111f;color:#e7eefc;font-family:Segoe UI,Arial,sans-serif}main{max-width:1200px;margin:auto;padding:32px}a{color:#69e3ff}.panel{background:#0b1728;border:1px solid #29405f;border-radius:18px;padding:20px;margin:18px 0}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.card{background:#030813;border:1px solid #29405f;border-radius:14px;padding:14px}.card strong{display:block;color:#69e3ff;font-size:1.5rem}.nav,.actions{display:flex;gap:8px;flex-wrap:wrap}.nav a,.actions a,.badge{border:1px solid #29405f;border-radius:999px;padding:7px 10px;text-decoration:none;background:#07111f;color:#f6f8ff;font-weight:700}.badge{display:inline-block;color:#69e3ff;background:#12375c}table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid #29405f;padding:10px;text-align:left}th{color:#69e3ff}pre{white-space:pre-wrap;background:#030813;border:1px solid #29405f;border-radius:14px;padding:14px;color:#ffdf99}@media(max-width:900px){.grid{grid-template-columns:1fr}table{display:block;overflow:auto}}</style><link rel="stylesheet" href="/ops-ui.css" data-contract="P7_OPS_NAVIGATION_POLISH_CORE">
</head><body><?= p7ops_language_selector($_SERVER['REQUEST_URI'] ?? '/opus-lstsar-manager') ?>
<main>
<p><span class="badge">P7_OPS_COMMAND_CENTER_CORE</span></p><h1>OPUS OPS Command Center</h1>
<div class="nav"><a href="/opus-lstsar-manager?site=<?=cc_e($site)?>">Dashboard</a><a href="/opus-lstsar-manager/operations?site=<?=cc_e($site)?>">Operations</a><a href="/opus-lstsar-manager/command?site=<?=cc_e($site)?>">Command Center</a><a href="/opus-lstsar-manager/action?site=<?=cc_e($site)?>&operation=<?=cc_e($firstId)?>&action=preview">Actions</a></div>
<section class="panel"><h2>OPS summary</h2><div class="grid"><div class="card"><strong><?=cc_e($counters['operations']??count($ops))?></strong>Operations</div><div class="card"><strong><?=cc_e($counters['active']??0)?></strong>Active</div><div class="card"><strong><?=cc_e($counters['ready']??0)?></strong>Ready</div><div class="card"><strong><?=cc_e($counters['blocked']??0)?></strong>Blocked</div></div></section>
<section class="panel"><h2>Quick actions</h2><div class="actions"><a href="/opus-lstsar-manager/action?site=<?=cc_e($site)?>&operation=<?=cc_e($firstId)?>&action=preview">Preview</a><a href="/opus-lstsar-manager/action?site=<?=cc_e($site)?>&operation=<?=cc_e($firstId)?>&action=dry-run">Dry-run</a><a href="/opus-lstsar-manager/action?site=<?=cc_e($site)?>&operation=<?=cc_e($firstId)?>&action=audit">Audit</a></div><pre><?=cc_e(json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'{}')?></pre></section>
<section class="panel"><h2>Operations table</h2><table><tr><th>Operation</th><th>Status</th><th>Actions</th></tr><?php foreach($ops as $op): if(!is_array($op)) continue; $id=cc_id($op); $st=(string)($op['status']??(($op['ready']??false)?'ready':'unknown')); ?><tr><td><code><?=cc_e($id)?></code></td><td><span class="badge"><?=cc_e($st)?></span></td><td><div class="actions"><a href="/opus-lstsar-manager/action?site=<?=cc_e($site)?>&operation=<?=cc_e($id)?>&action=preview">Preview</a><a href="/opus-lstsar-manager/action?site=<?=cc_e($site)?>&operation=<?=cc_e($id)?>&action=dry-run">Dry-run</a><a href="/opus-lstsar-manager/action?site=<?=cc_e($site)?>&operation=<?=cc_e($id)?>&action=audit">Audit</a></div></td></tr><?php endforeach; ?></table></section>
<section class="panel"><h2>Diagnostics</h2><pre><?=cc_e(json_encode(['routes'=>['/opus-lstsar-manager/command','/opus-lstsar-manager/command-center','/opus-lstsar-manager/action'],'modes'=>array_values($modes),'side_effects'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'{}')?></pre></section>
</main></body></html>
