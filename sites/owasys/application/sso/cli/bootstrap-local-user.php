<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){fwrite(STDERR,"OWASYS_SSO_BOOTSTRAP_CLI_ONLY
");exit(1);} $siteRoot=dirname(__DIR__,3);$u=trim((string)($argv[1]??''));$p=(string)($argv[2]??'');$role=trim((string)($argv[3]??'admin'));if($u===''||strlen($p)<10){fwrite(STDERR,"USAGE: php sites/owasys/application/sso/cli/bootstrap-local-user.php <username> <password-min-10> [role]
");exit(1);}if(!in_array($role,['admin','developer','viewer'],true)){fwrite(STDERR,"OWASYS_SSO_BOOTSTRAP_ROLE_INVALID
");exit(1);} $file=$siteRoot.'/var/auth/local-users.json';$s=['contract'=>'OWASYS_LOCAL_USER_STORE_V1','committed'=>false,'users'=>[]];if(is_file($file)){$d=json_decode((string)file_get_contents($file),true);if(!is_array($d)||($d['contract']??'')!=='OWASYS_LOCAL_USER_STORE_V1'){fwrite(STDERR,"OWASYS_SSO_BOOTSTRAP_STORE_INVALID
");exit(1);}$s=$d;$s['users']=is_array($s['users']??null)?$s['users']:[];}$s['users'][$u]=['id'=>$u,'label'=>$u,'roles'=>[$role],'profile'=>$role,'password_hash'=>password_hash($p,PASSWORD_DEFAULT),'must_change_password'=>false,'updated_at'=>gmdate('c')];$dir=dirname($file);if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir)){fwrite(STDERR,"OWASYS_SSO_BOOTSTRAP_DIRECTORY_FAILED
");exit(1);}file_put_contents($file,json_encode($s,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL,LOCK_EX);echo "OWASYS_SSO_LOCAL_USER_READY:$u:$role
";
