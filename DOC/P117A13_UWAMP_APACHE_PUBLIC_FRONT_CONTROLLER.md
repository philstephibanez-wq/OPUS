# P117A13 â€” UwAmp / Apache public front controller

## Scope

This gate prepares the native OPUS admin dashboard transition from PHP development server to Apache/UwAmp.

The PHP development server validation remains:

```cmd
cd /d H:\OPUS
php -S 127.0.0.1:8765 index.php
```

The Apache/UwAmp target is now:

```text
http://opus.localhost/admin/blocked-states
```

## Strict exposure contract

Apache must expose only:

```text
H:\OPUS\public
```

Apache must never expose:

```text
H:\OPUS
H:\OPUS\framework
H:\OPUS\vendor
H:\OPUS\var
H:\OPUS\DOC
```

## VirtualHost snippet

Add the following block to the UwAmp Apache vhosts configuration file.

```apache
# BEGIN MAESTRO_WORKSPACE P117A13 OPUS LOCAL VHOST
<VirtualHost *:80>
    ServerName opus.localhost
    DocumentRoot "H:/OPUS/public"

    <Directory "H:/OPUS/public">
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog "H:/OPUS/var/logs/opus_uwamp_error.log"
    CustomLog "H:/OPUS/var/logs/opus_uwamp_access.log" common
</VirtualHost>
# END MAESTRO_WORKSPACE P117A13 OPUS LOCAL VHOST
```

Add this entry to `C:\Windows\System32\drivers\etc\hosts` with administrator rights:

```text
127.0.0.1 opus.localhost
```

Then restart Apache from UwAmp.

## Validation

```cmd
cd /d H:\OPUS
php -r "require 'framework/Opus/Runtime/UwAmpPublicFrontControllerSmoke.php'; $r=\Opus\Runtime\UwAmpPublicFrontControllerSmoke::run('H:\OPUS'); foreach ($r as $k=>$v) { echo $k.'='.(is_bool($v)?($v?'true':'false'):$v).PHP_EOL; } if (!$r['ok']) exit(1);"
curl -i http://opus.localhost/admin/blocked-states
```

Expected HTTP result:

```text
HTTP/1.1 200 OK
Content-Type: text/html; charset=utf-8
```

## Next gate

P117A14 must not be mixed with the Apache exposure gate.

P117A14 target:

```text
Authentication
â†’ SSO token/session intake
â†’ FSM state gate
â†’ ACL authorization gate
â†’ admin dashboard decision
â†’ neutral public response on denial
â†’ internal diagnostics only in admin logs/dashboard
```