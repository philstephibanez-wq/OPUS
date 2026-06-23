@echo off
setlocal
cd /d "%~dp0"
echo == P4O_MOVE_I18N_VIEW_CLASSES ==
python tools\migrations\apply_p4o_move_i18n_view_classes.py
if errorlevel 1 (
  echo P4O_MOVE_I18N_VIEW_CLASSES_FAILED
  exit /b 1
)
php -l Opus\I18n\I18n.php || exit /b 1
php -l Opus\View\View.php || exit /b 1
php -l Opus\Kernel.php || exit /b 1
php -l Opus\Router.php || exit /b 1
php -l Opus\Bootstrap.php || exit /b 1
composer dump-autoload || exit /b 1
exit /b 0
