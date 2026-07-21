<?php
declare(strict_types=1);
$siteRoot=dirname(__DIR__,3);$opusRoot=dirname(dirname($siteRoot));$required=[$opusRoot.'/Opus/Score/ScoreTemplateRenderer.php',$opusRoot.'/Opus/Security/Acl/AclPolicy.php',$opusRoot.'/Opus/Security/Sso/SsoManager.php',$siteRoot.'/config/acl.json',$siteRoot.'/config/sso.json',$siteRoot.'/application/default/templates/layout.score',$siteRoot.'/application/login/templates/index.score',$siteRoot.'/application/registry/templates/index.score'];foreach($required as $f){if(!is_file($f)){fwrite(STDERR,'OWASYS_STEP7_REQUIRED_FILE_MISSING:'.$f.PHP_EOL);exit(1);}}if(is_file($siteRoot.'/application/default/views/layout.php')){fwrite(STDERR,"OWASYS_STEP7_FORBIDDEN_FILE_PRESENT:application/default/views/layout.php
");exit(1);}echo "OWASYS_STEP7_SCORE_FSM_ACL_SSO_COMPLIANCE_OK
";
