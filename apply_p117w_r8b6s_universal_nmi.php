warning: in the working copy of 'sites/essai/config/application.fsm.json', CRLF will be replaced by LF the next time Git touches it
warning: in the working copy of 'sites/owasys-back/config/fsm.json', CRLF will be replaced by LF the next time Git touches it
warning: in the working copy of 'sites/owasys-front/config/fsm.json', CRLF will be replaced by LF the next time Git touches it
[1mdiff --git a/Opus/Scaffold/SiteScaffoldPlan.php b/Opus/Scaffold/SiteScaffoldPlan.php[m
[1mindex e84f489d..42b7a273 100644[m
[1m--- a/Opus/Scaffold/SiteScaffoldPlan.php[m
[1m+++ b/Opus/Scaffold/SiteScaffoldPlan.php[m
[36m@@ -782,6 +782,14 @@[m [mfinal class SiteScaffoldPlan implements ScaffoldPlanInterface, SiteScaffoldPlanI[m
             'id' => 'open_profiler',[m
             'origin' => 'user',[m
         ];[m
[32m+[m[32m        $signals[] = [[m
[32m+[m[32m            'id' => 'security_violation',[m
[32m+[m[32m            'origin' => 'automatic',[m
[32m+[m[32m        ];[m
[32m+[m[32m        $signals[] = [[m
[32m+[m[32m            'id' => 'critical_error',[m
[32m+[m[32m            'origin' => 'automatic',[m
[32m+[m[32m        ];[m
 [m
         $states[] = [[m
             'id' => 'begin',[m
[36m@@ -840,6 +848,21 @@[m [mfinal class SiteScaffoldPlan implements ScaffoldPlanInterface, SiteScaffoldPlanI[m
             ];[m
         }[m
 [m
[32m+[m[32m        $transitions[] = [[m
[32m+[m[32m            'id' => 'nmi.security_violation',[m
[32m+[m[32m            'from' => '*',[m
[32m+[m[32m            'signal' => 'security_violation',[m
[32m+[m[32m            'next_state' => 'begin',[m
[32m+[m[32m            'interrupt' => 'nmi',[m
[32m+[m[32m        ];[m
[32m+[m[32m        $transitions[] = [[m
[32m+[m[32m            'id' => 'nmi.critical_error',[m
[32m+[m[32m            'from' => '*',[m
[32m+[m[32m            'signal' => 'critical_error',[m
[32m+[m[32m            'next_state' => 'begin',[m
[32m+[m[32m            'interrupt' => 'nmi',[m
[32m+[m[32m        ];[m
[32m+[m
         return [[m
             'contract' => 'OPUS_APPLICATION_FSM_V1',[m
             'name' => $this->siteId . '.application',[m
[36m@@ -1331,6 +1354,14 @@[m [mPHP;[m
                         'id' => 'dispatch_api',[m
                         'origin' => 'automatic',[m
                     ],[m
[32m+[m[32m                    [[m
[32m+[m[32m                        'id' => 'security_violation',[m
[32m+[m[32m                        'origin' => 'automatic',[m
[32m+[m[32m                    ],[m
[32m+[m[32m                    [[m
[32m+[m[32m                        'id' => 'critical_error',[m
[32m+[m[32m                        'origin' => 'automatic',[m
[32m+[m[32m                    ],[m
                 ],[m
                 'states' => [[m
                     [[m
[36m@@ -1362,6 +1393,20 @@[m [mPHP;[m
                         'guards' => ['route_exists'],[m
                         'actions' => ['dispatch_rest'],[m
                     ],[m
[32m+[m[32m                    [[m
[32m+[m[32m                        'id' => 'nmi.security_violation',[m
[32m+[m[32m                        'from' => '*',[m
[32m+[m[32m                        'signal' => 'security_violation',[m
[32m+[m[32m                        'next_state' => 'api',[m
[32m+[m[32m                        'interrupt' => 'nmi',[m
[32m+[m[32m                    ],[m
[32m+[m[32m                    [[m
[32m+[m[32m                        'id' => 'nmi.critical_error',[m
[32m+[m[32m                        'from' => '*',[m
[32m+[m[32m                        'signal' => 'critical_error',[m
[32m+[m[32m                        'next_state' => 'api',[m
[32m+[m[32m                        'interrupt' => 'nmi',[m
[32m+[m[32m                    ],[m
                 ],[m
             ]),[m
             "sites/{$site}/config/security.fsm.json" => $this->json([m
[1mdiff --git a/sites/essai/config/application.fsm.json b/sites/essai/config/application.fsm.json[m
[1mindex ea9ebee4..eb87aab1 100644[m
[1m--- a/sites/essai/config/application.fsm.json[m
[1m+++ b/sites/essai/config/application.fsm.json[m
[36m@@ -62,6 +62,20 @@[m
             "from": "connexion",[m
             "signal": "open_home",[m
             "next_state": "connexion"[m
[32m+[m[32m        },[m
[32m+[m[32m        {[m
[32m+[m[32m            "id": "nmi.security_violation",[m
[32m+[m[32m            "from": "*",[m
[32m+[m[32m            "signal": "security_violation",[m
[32m+[m[32m            "next_state": "connexion",[m
[32m+[m[32m            "interrupt": "nmi"[m
[32m+[m[32m        },[m
[32m+[m[32m        {[m
[32m+[m[32m            "id": "nmi.critical_error",[m
[32m+[m[32m            "from": "*",[m
[32m+[m[32m            "signal": "critical_error",[m
[32m+[m[32m            "next_state": "connexion",[m
[32m+[m[32m            "interrupt": "nmi"[m
         }[m
     ],[m
     "signals": [[m
[36m@@ -78,6 +92,16 @@[m
             "id": "new",[m
             "origin": "user",[m
             "type": "command"[m
[32m+[m[32m        },[m
[32m+[m[32m        {[m
[32m+[m[32m            "id": "security_violation",[m
[32m+[m[32m            "origin": "automatic",[m
[32m+[m[32m            "type": "system"[m
[32m+[m[32m        },[m
[32m+[m[32m        {[m
[32m+[m[32m            "id": "critical_error",[m
[32m+[m[32m            "origin": "automatic",[m
[32m+[m[32m            "type": "system"[m
         }[m
     ][m
 }[m
[1mdiff --git a/sites/owasys-back/config/fsm.json b/sites/owasys-back/config/fsm.json[m
[1mindex 46bdd22d..6166e3bb 100644[m
[1m--- a/sites/owasys-back/config/fsm.json[m
[1m+++ b/sites/owasys-back/config/fsm.json[m
[36m@@ -44,6 +44,10 @@[m
         },[m
         {[m
             "id": "fail"[m
[32m+[m[32m        },[m
[32m+[m[32m        {[m
[32m+[m[32m            "id": "security_violation",[m
[32m+[m[32m            "origin": "automatic"[m
         }[m
     ],[m
     "transitions": [[m
[36m@@ -101,6 +105,13 @@[m
             "signal": "fail",[m
             "next_state": "api",[m
             "interrupt": "nmi"[m
[32m+[m[32m        },[m
[32m+[m[32m        {[m
[32m+[m[32m            "id": "t_security_violation",[m
[32m+[m[32m            "from": "*",[m
[32m+[m[32m            "signal": "security_violation",[m
[32m+[m[32m            "next_state": "api",[m
[32m+[m[32m            "interrupt": "nmi"[m
         }[m
     ][m
 }[m
[1mdiff --git a/sites/owasys-front/config/fsm.json b/sites/owasys-front/config/fsm.json[m
[1mindex c846dd62..d11fc7e0 100644[m
[1m--- a/sites/owasys-front/config/fsm.json[m
[1m+++ b/sites/owasys-front/config/fsm.json[m
[36m@@ -975,6 +975,12 @@[m
             "type": "event",[m
             "menu": false,[m
             "origin": "automatic"[m
[32m+[m[32m        },[m
[32m+[m[32m        {[m
[32m+[m[32m            "id": "critical_error",[m
[32m+[m[32m            "type": "system",[m
[32m+[m[32m            "menu": false,[m
[32m+[m[32m            "origin": "automatic"[m
         }[m
     ],[m
     "transitions": [[m
[36m@@ -2599,6 +2605,16 @@[m
             "from": "build",[m
             "signal": "build_context_ready",[m
             "next_state": "build"[m
[32m+[m[32m        },[m
[32m+[m[32m        {[m
[32m+[m[32m            "id": "t_critical_error",[m
[32m+[m[32m            "from": "*",[m
[32m+[m[32m            "signal": "critical_error",[m
[32m+[m[32m            "next_state": "login",[m
[32m+[m[32m            "actions": [[m
[32m+[m[32m                "clear_session"[m
[32m+[m[32m            ],[m
[32m+[m[32m            "interrupt": "nmi"[m
         }[m
     ],[m
     "guards": {[m
