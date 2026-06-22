<?php
require_once __DIR__ . '/ASAP/bootstrap.php';


class ExtensionFilterIteratorDecorator extends FilterIterator {
    private string $_ext = '.php';

    public function accept(): bool {
        $current = (string) $this->current();
        return substr($current, -strlen($this->_ext)) === $this->_ext && is_readable($current);
    }

    public function setExtension($pExt): void {
        $this->_ext = (string) $pExt;
    }
}

class DirectoriesAutoloaderException extends Exception {}

class DirectoriesAutoloader {
    private static $_instance = false;
    private string $_cachePath = '';
    private bool $_canRegenerate = true;
    private array $_classes = array();
    private array $_directories = array();

    private function __construct() {}

    public static function getInstance($pTmpPath) {
        if (self::$_instance === false) {
            self::$_instance = new DirectoriesAutoloader();
            self::$_instance->setCachePath($pTmpPath);
        }
        return self::$_instance;
    }

    public function setCachePath($pTmp): void {
        $pTmp = rtrim((string)$pTmp, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!is_dir($pTmp)) {
            mkdir($pTmp, 0775, true);
        }
        if (!is_writable($pTmp)) {
            throw new DirectoriesAutoloaderException('Cannot write in given CachePath [' . $pTmp . ']');
        }
        $this->_cachePath = $pTmp;
    }

    public function autoload($pClassName): bool {
        if ($this->_loadClass($pClassName)) {
            return true;
        }
        if ($this->_canRegenerate) {
            $this->_canRegenerate = false;
            $this->_includesAll();
            $this->_saveInCache();
            return $this->autoload($pClassName);
        }
        return false;
    }

    private function _includesAll(): void {
        foreach ($this->_directories as $directory => $recursive) {
            $directories = new AppendIterator();
            if ($recursive) {
                $directories->append(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)));
            } else {
                $directories->append(new DirectoryIterator($directory));
            }
            $files = new ExtensionFilterIteratorDecorator($directories);
            $files->setExtension('.php');
            foreach ($files as $fileName) {
                foreach ($this->_extractClasses((string) $fileName) as $className => $classFile) {
                    $this->_classes[strtolower($className)] = $classFile;
                }
            }
        }
    }

    private function _extractClasses($pFileName): array {
        $toReturn = array();
        $tokens = token_get_all(file_get_contents($pFileName));
        $classHunt = false;
        foreach ($tokens as $token) {
            if (!is_array($token)) {
                continue;
            }
            if ($token[0] === T_INTERFACE || $token[0] === T_CLASS || (defined('T_ENUM') && $token[0] === T_ENUM)) {
                $classHunt = true;
                continue;
            }
            if ($classHunt && $token[0] === T_STRING) {
                $toReturn[$token[1]] = $pFileName;
                $classHunt = false;
            }
        }
        return $toReturn;
    }

    private function _saveInCache(): void {
        file_put_contents($this->_cachePath . 'directoriesautoloader.cache.php', '<?php $classes = ' . var_export($this->_classes, true) . ';');
    }

    private function _loadClass($pClassName): bool {
        $className = strtolower($pClassName);
        if (count($this->_classes) === 0) {
            $cache = $this->_cachePath . 'directoriesautoloader.cache.php';
            if (is_readable($cache)) {
                $classes = array();
                require $cache;
                $this->_classes = is_array($classes) ? $classes : array();
            }
        }
        if (isset($this->_classes[$className])) {
            $classFile = $this->_classes[$className];
            if (is_string($classFile) && is_readable($classFile)) {
                require_once $classFile;
                return true;
            }

            // Stale cache protection: never require an absolute path generated
            // on another machine/deployment. Drop the cache and let autoload()
            // regenerate it from the current ROOT directories.
            unset($this->_classes[$className]);
            $cache = $this->_cachePath . 'directoriesautoloader.cache.php';
            if (is_file($cache)) {
                @unlink($cache);
            }
            return false;
        }
        return false;
    }

    public function addDirectory($pDirectory, $pRecursive = true): self {
        if (!is_readable($pDirectory)) {
            throw new DirectoriesAutoloaderException('Cannot read from [' . $pDirectory . ']');
        }
        $this->_directories[$pDirectory] = $pRecursive ? true : false;
        return $this;
    }
}

$tmpPath = defined('ROOT') ? ROOT . '/tmp/' : __DIR__ . '/../tmp/';
$base = defined('ROOT') ? ROOT : realpath(__DIR__ . '/..');

$autoloader = DirectoriesAutoloader::getInstance($tmpPath)
    ->addDirectory($base . '/framework/ASAP/')
    ->addDirectory($base . '/application/');

spl_autoload_register(array($autoloader, 'autoload'));
