<?php
declare(strict_types=1);

namespace Opus\Application\Git;

use Opus\File\StructuredFileLoader;
use Opus\Log\LoggerInterface;
use Opus\Profiler\ProfilerInterface;
use Throwable;

/**
 * Generic, bounded Git workspace restricted to one OPUS site at a time.
 *
 * No free Git command or caller-provided option is accepted. Commit messages,
 * diffs and file contents never enter logs, profiler metadata or exceptions.
 */
final class SiteGitWorkspace implements SiteGitWorkspaceInterface
{
    public const CONTRACT = 'OPUS_SITE_GIT_WORKSPACE_V1';
    private const MAX_OUTPUT_BYTES = 2097152;
    private const MAX_DIFF_BYTES = 262144;
    private const MAX_HISTORY = 50;
    private const TIMEOUT_SECONDS = 20;

    private readonly string $opusRoot;
    private readonly string $sitesRoot;
    private readonly StructuredFileLoader $loader;

    public function __construct(
        string $opusRoot,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?ProfilerInterface $profiler = null,
        private readonly ?string $parentSpanId = null
    ) {
        $realRoot = realpath($opusRoot);
        if (!is_string($realRoot) || !is_dir($realRoot)) {
            throw new \RuntimeException('OPUS_SITE_GIT_OPUS_ROOT_INVALID');
        }
        $realRoot = rtrim(str_replace('\\', '/', $realRoot), '/');
        if (!is_dir($realRoot . '/.git')) {
            throw new \RuntimeException('OPUS_SITE_GIT_REPOSITORY_REQUIRED');
        }
        $sitesRoot = realpath($realRoot . '/sites');
        if (!is_string($sitesRoot) || !is_dir($sitesRoot)) {
            throw new \RuntimeException('OPUS_SITE_GIT_SITES_ROOT_INVALID');
        }

        $this->opusRoot = $realRoot;
        $this->sitesRoot = rtrim(str_replace('\\', '/', $sitesRoot), '/');
        $this->loader = StructuredFileLoader::instance();
    }

    public function status(string $siteId): array
    {
        return $this->observed(
            'status',
            ['application_id' => $siteId],
            function () use ($siteId): array {
                $siteId = $this->siteId($siteId);
                $sitePrefix = $this->sitePrefix($siteId);
                $branchResult = $this->runGit([
                    'symbolic-ref', '--quiet', '--short', 'HEAD',
                ], true);
                $headResult = $this->runGit([
                    'rev-parse', '--verify', 'HEAD',
                ], true);
                $statusResult = $this->runGit([
                    'status',
                    '--porcelain=v1',
                    '-z',
                    '--untracked-files=all',
                    '--',
                    $sitePrefix,
                ]);

                $changes = $this->parseStatus(
                    $siteId,
                    $statusResult['stdout']
                );

                return [
                    'contract' => 'OPUS_SITE_GIT_STATUS_V1',
                    'application_id' => $siteId,
                    'branch' => $branchResult['exit_code'] === 0
                        ? trim($branchResult['stdout'])
                        : '',
                    'head' => $headResult['exit_code'] === 0
                        ? strtolower(trim($headResult['stdout']))
                        : '',
                    'clean' => $changes === [],
                    'changes' => $changes,
                    'counts' => [
                        'total' => count($changes),
                        'staged' => count(array_filter(
                            $changes,
                            static fn (array $change): bool =>
                                ($change['staged'] ?? false) === true
                        )),
                        'unstaged' => count(array_filter(
                            $changes,
                            static fn (array $change): bool =>
                                ($change['unstaged'] ?? false) === true
                        )),
                        'untracked' => count(array_filter(
                            $changes,
                            static fn (array $change): bool =>
                                ($change['untracked'] ?? false) === true
                        )),
                    ],
                ];
            }
        );
    }

    public function diff(string $siteId, string $relativePath): array
    {
        return $this->observed(
            'diff',
            [
                'application_id' => $siteId,
                'path' => $relativePath,
            ],
            function () use ($siteId, $relativePath): array {
                $siteId = $this->siteId($siteId);
                $relativePath = $this->relativePath($relativePath);
                $repositoryPath = $this->repositoryPath(
                    $siteId,
                    $relativePath,
                    false
                );

                $unstaged = $this->runGit([
                    'diff',
                    '--no-ext-diff',
                    '--no-color',
                    '--unified=3',
                    '--',
                    $repositoryPath,
                ]);
                $staged = $this->runGit([
                    'diff',
                    '--cached',
                    '--no-ext-diff',
                    '--no-color',
                    '--unified=3',
                    '--',
                    $repositoryPath,
                ]);
                $unstagedPayload = $this->boundedDiff($unstaged['stdout']);
                $stagedPayload = $this->boundedDiff($staged['stdout']);

                return [
                    'contract' => 'OPUS_SITE_GIT_DIFF_V1',
                    'application_id' => $siteId,
                    'path' => $relativePath,
                    'unstaged' => $unstagedPayload['content'],
                    'unstaged_truncated' => $unstagedPayload['truncated'],
                    'staged' => $stagedPayload['content'],
                    'staged_truncated' => $stagedPayload['truncated'],
                ];
            }
        );
    }

    public function history(string $siteId, int $limit = 20): array
    {
        return $this->observed(
            'history',
            ['application_id' => $siteId, 'limit' => $limit],
            function () use ($siteId, $limit): array {
                $siteId = $this->siteId($siteId);
                if ($limit < 1 || $limit > self::MAX_HISTORY) {
                    throw new \InvalidArgumentException(
                        'OPUS_SITE_GIT_HISTORY_LIMIT_INVALID'
                    );
                }
                $result = $this->runGit([
                    'log',
                    '--date=iso-strict',
                    '--pretty=format:%H%x1f%an%x1f%ad%x1f%s%x1e',
                    '-n',
                    (string) $limit,
                    '--',
                    $this->sitePrefix($siteId),
                ], true);

                $commits = [];
                if ($result['exit_code'] === 0) {
                    foreach (explode("\x1e", $result['stdout']) as $record) {
                        $record = trim($record);
                        if ($record === '') {
                            continue;
                        }
                        $parts = explode("\x1f", $record, 4);
                        if (count($parts) !== 4) {
                            throw new \RuntimeException(
                                'OPUS_SITE_GIT_HISTORY_OUTPUT_INVALID'
                            );
                        }
                        $hash = strtolower(trim($parts[0]));
                        if (preg_match('/^[a-f0-9]{40,64}$/D', $hash) !== 1) {
                            throw new \RuntimeException(
                                'OPUS_SITE_GIT_HISTORY_OUTPUT_INVALID'
                            );
                        }
                        $commits[] = [
                            'hash' => $hash,
                            'short_hash' => substr($hash, 0, 12),
                            'author' => $this->displayText($parts[1], 160),
                            'date' => $this->displayText($parts[2], 64),
                            'subject' => $this->displayText($parts[3], 240),
                        ];
                    }
                }

                return [
                    'contract' => 'OPUS_SITE_GIT_HISTORY_V1',
                    'application_id' => $siteId,
                    'limit' => $limit,
                    'commits' => $commits,
                ];
            }
        );
    }

    public function stage(string $siteId, string $relativePath): array
    {
        return $this->observed(
            'stage',
            [
                'application_id' => $siteId,
                'path' => $relativePath,
            ],
            function () use ($siteId, $relativePath): array {
                $siteId = $this->siteId($siteId);
                $relativePath = $this->relativePath($relativePath);
                $repositoryPath = $this->repositoryPath(
                    $siteId,
                    $relativePath,
                    false
                );
                $this->runGit(['add', '--', $repositoryPath]);

                return [
                    'contract' => 'OPUS_SITE_GIT_STAGE_V1',
                    'application_id' => $siteId,
                    'path' => $relativePath,
                    'status' => $this->status($siteId),
                ];
            }
        );
    }

    public function stageAll(string $siteId): array
    {
        return $this->observed(
            'stage_all',
            ['application_id' => $siteId],
            function () use ($siteId): array {
                $siteId = $this->siteId($siteId);
                $before = $this->status($siteId);
                $changes = is_array($before['changes'] ?? null)
                    ? $before['changes']
                    : [];
                $candidates = [];
                foreach ($changes as $change) {
                    if (!is_array($change)) {
                        continue;
                    }
                    if (($change['conflicted'] ?? false) === true) {
                        throw new \RuntimeException(
                            'OPUS_SITE_GIT_STAGE_ALL_CONFLICT_FORBIDDEN'
                        );
                    }
                    if (($change['unstaged'] ?? false) === true
                        || ($change['untracked'] ?? false) === true) {
                        $candidates[] = (string) ($change['path'] ?? '');
                    }
                }

                if ($candidates !== []) {
                    $this->runGit([
                        'add',
                        '-A',
                        '--',
                        $this->sitePrefix($siteId),
                    ]);
                }

                return [
                    'contract' => 'OPUS_SITE_GIT_STAGE_ALL_V1',
                    'application_id' => $siteId,
                    'affected_path_count' => count($candidates),
                    'status' => $this->status($siteId),
                ];
            }
        );
    }
    public function unstage(string $siteId, string $relativePath): array
    {
        return $this->observed(
            'unstage',
            [
                'application_id' => $siteId,
                'path' => $relativePath,
            ],
            function () use ($siteId, $relativePath): array {
                $siteId = $this->siteId($siteId);
                $relativePath = $this->relativePath($relativePath);
                $repositoryPath = $this->repositoryPath(
                    $siteId,
                    $relativePath,
                    false
                );
                $this->runGit([
                    'restore',
                    '--staged',
                    '--',
                    $repositoryPath,
                ]);

                return [
                    'contract' => 'OPUS_SITE_GIT_UNSTAGE_V1',
                    'application_id' => $siteId,
                    'path' => $relativePath,
                    'status' => $this->status($siteId),
                ];
            }
        );
    }

    public function commit(string $siteId, string $message): array
    {
        return $this->observed(
            'commit',
            ['application_id' => $siteId],
            function () use ($siteId, $message): array {
                $siteId = $this->siteId($siteId);
                $message = $this->commitMessage($message);
                $staged = $this->runGit([
                    'diff',
                    '--cached',
                    '--name-only',
                    '-z',
                ]);
                $paths = array_values(array_filter(
                    explode("\0", $staged['stdout']),
                    static fn (string $path): bool => $path !== ''
                ));
                if ($paths === []) {
                    throw new \RuntimeException(
                        'OPUS_SITE_GIT_COMMIT_NOTHING_STAGED'
                    );
                }
                $prefix = $this->sitePrefix($siteId) . '/';
                foreach ($paths as $path) {
                    $path = str_replace('\\', '/', $path);
                    if (!str_starts_with($path, $prefix)) {
                        throw new \RuntimeException(
                            'OPUS_SITE_GIT_COMMIT_FOREIGN_STAGE_FORBIDDEN'
                        );
                    }
                }

                $result = $this->runGit(['commit', '-m', $message]);
                $head = $this->runGit([
                    'rev-parse', '--verify', 'HEAD',
                ]);
                $hash = strtolower(trim($head['stdout']));
                if (preg_match('/^[a-f0-9]{40,64}$/D', $hash) !== 1) {
                    throw new \RuntimeException(
                        'OPUS_SITE_GIT_COMMIT_RESULT_INVALID'
                    );
                }

                return [
                    'contract' => 'OPUS_SITE_GIT_COMMIT_V1',
                    'application_id' => $siteId,
                    'hash' => $hash,
                    'short_hash' => substr($hash, 0, 12),
                    'staged_path_count' => count($paths),
                    'result_bytes' => strlen($result['stdout']),
                    'status' => $this->status($siteId),
                ];
            }
        );
    }

    public function restore(
        string $siteId,
        string $relativePath,
        string $expectedContentHash,
        string $confirmation
    ): array {
        return $this->observed(
            'restore',
            [
                'application_id' => $siteId,
                'path' => $relativePath,
            ],
            function () use (
                $siteId,
                $relativePath,
                $expectedContentHash,
                $confirmation
            ): array {
                $siteId = $this->siteId($siteId);
                $relativePath = $this->relativePath($relativePath);
                $repositoryPath = $this->repositoryPath(
                    $siteId,
                    $relativePath,
                    true
                );
                $this->runGit([
                    'ls-files',
                    '--error-unmatch',
                    '--',
                    $repositoryPath,
                ]);
                $target = $this->existingTarget($siteId, $relativePath);
                $currentHash = strtolower((string) hash_file(
                    'sha256',
                    $target
                ));
                $expectedContentHash = strtolower(trim($expectedContentHash));
                if (preg_match(
                    '/^[a-f0-9]{64}$/D',
                    $expectedContentHash
                ) !== 1) {
                    throw new \InvalidArgumentException(
                        'OPUS_SITE_GIT_RESTORE_HASH_INVALID'
                    );
                }
                if (!hash_equals($currentHash, $expectedContentHash)) {
                    throw new \RuntimeException(
                        'OPUS_SITE_GIT_RESTORE_CONFLICT'
                    );
                }
                $expectedConfirmation = 'RESTORE:'
                    . $siteId . ':' . $relativePath . ':' . $currentHash;
                if (!hash_equals($expectedConfirmation, $confirmation)) {
                    throw new \RuntimeException(
                        'OPUS_SITE_GIT_RESTORE_CONFIRMATION_INVALID'
                    );
                }

                $this->runGit([
                    'restore',
                    '--worktree',
                    '--',
                    $repositoryPath,
                ]);
                $restoredTarget = $this->existingTarget(
                    $siteId,
                    $relativePath
                );
                $restoredHash = strtolower((string) hash_file(
                    'sha256',
                    $restoredTarget
                ));

                return [
                    'contract' => 'OPUS_SITE_GIT_RESTORE_V1',
                    'application_id' => $siteId,
                    'path' => $relativePath,
                    'previous_sha256' => $currentHash,
                    'sha256' => $restoredHash,
                    'status' => $this->status($siteId),
                ];
            }
        );
    }

    /**
     * @return array{exit_code:int,stdout:string}
     */
    private function runGit(array $arguments, bool $allowFailure = false): array
    {
        foreach ($arguments as $argument) {
            if (!is_string($argument) || str_contains($argument, "\0")) {
                throw new \InvalidArgumentException(
                    'OPUS_SITE_GIT_ARGUMENT_INVALID'
                );
            }
        }

        $command = [
            'git',
            '-c',
            'color.ui=false',
            '-c',
            'core.quotepath=false',
            ...$arguments,
        ];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $environment = [];
        foreach ([
            'PATH', 'Path', 'SystemRoot', 'COMSPEC', 'HOME',
            'USERPROFILE', 'TEMP', 'TMP',
        ] as $name) {
            $value = getenv($name);
            if (is_string($value) && $value !== '') {
                $environment[$name] = $value;
            }
        }
        $environment = array_replace($environment, [
            'GIT_TERMINAL_PROMPT' => '0',
            'GCM_INTERACTIVE' => 'Never',
            'LC_ALL' => 'C',
            'LANG' => 'C',
        ]);
        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            $this->opusRoot,
            $environment,
            ['bypass_shell' => true]
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('OPUS_SITE_GIT_PROCESS_OPEN_FAILED');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $started = microtime(true);
        $exitCode = null;

        try {
            while (true) {
                $stdout .= (string) stream_get_contents($pipes[1]);
                $stderr .= (string) stream_get_contents($pipes[2]);
                if (strlen($stdout) + strlen($stderr)
                    > self::MAX_OUTPUT_BYTES) {
                    proc_terminate($process);
                    throw new \RuntimeException(
                        'OPUS_SITE_GIT_OUTPUT_LIMIT_EXCEEDED'
                    );
                }

                $status = proc_get_status($process);
                if (!is_array($status)) {
                    throw new \RuntimeException(
                        'OPUS_SITE_GIT_PROCESS_STATUS_INVALID'
                    );
                }
                if (($status['running'] ?? false) !== true) {
                    $candidate = (int) ($status['exitcode'] ?? -1);
                    $exitCode = $candidate >= 0 ? $candidate : null;
                    break;
                }
                if ((microtime(true) - $started)
                    > self::TIMEOUT_SECONDS) {
                    proc_terminate($process);
                    throw new \RuntimeException(
                        'OPUS_SITE_GIT_PROCESS_TIMEOUT'
                    );
                }
                usleep(10000);
            }

            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            $closed = proc_close($process);
            if ($exitCode === null && $closed >= 0) {
                $exitCode = $closed;
            }
        }

        $exitCode ??= -1;
        if ($exitCode !== 0 && !$allowFailure) {
            throw new \RuntimeException('OPUS_SITE_GIT_COMMAND_FAILED');
        }

        return [
            'exit_code' => $exitCode,
            'stdout' => $stdout,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parseStatus(string $siteId, string $output): array
    {
        $tokens = explode("\0", $output);
        $changes = [];
        $prefix = $this->sitePrefix($siteId) . '/';

        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '') {
                continue;
            }
            if (strlen($token) < 4 || $token[2] !== ' ') {
                throw new \RuntimeException(
                    'OPUS_SITE_GIT_STATUS_OUTPUT_INVALID'
                );
            }
            $indexCode = $token[0];
            $worktreeCode = $token[1];
            $repositoryPath = substr($token, 3);
            $oldRepositoryPath = null;
            if ($indexCode === 'R' || $indexCode === 'C') {
                $oldRepositoryPath = (string) ($tokens[++$index] ?? '');
            }
            $repositoryPath = str_replace('\\', '/', $repositoryPath);
            if (!str_starts_with($repositoryPath, $prefix)) {
                throw new \RuntimeException(
                    'OPUS_SITE_GIT_STATUS_SCOPE_INVALID'
                );
            }
            $relative = substr($repositoryPath, strlen($prefix));
            $relative = $this->relativePath($relative);
            $target = $this->opusRoot . '/' . $repositoryPath;
            $exists = is_file($target) && !is_link($target);
            $entry = [
                'path' => $relative,
                'exists' => $exists,
                'sha256' => $exists
                    ? strtolower((string) hash_file('sha256', $target))
                    : '',
                'index_status' => $indexCode,
                'worktree_status' => $worktreeCode,
                'staged' => $indexCode !== ' ' && $indexCode !== '?',
                'unstaged' => $worktreeCode !== ' ' && $worktreeCode !== '?',
                'untracked' => $indexCode === '?' && $worktreeCode === '?',
                'conflicted' => in_array(
                    $indexCode . $worktreeCode,
                    ['DD', 'AU', 'UD', 'UA', 'DU', 'AA', 'UU'],
                    true
                ),
            ];
            if (is_string($oldRepositoryPath)
                && str_starts_with($oldRepositoryPath, $prefix)) {
                $entry['old_path'] = $this->relativePath(
                    substr($oldRepositoryPath, strlen($prefix))
                );
            }
            $changes[] = $entry;
        }

        usort(
            $changes,
            static fn (array $left, array $right): int => strcmp(
                (string) ($left['path'] ?? ''),
                (string) ($right['path'] ?? '')
            )
        );
        return $changes;
    }

    private function siteId(string $siteId): string
    {
        $siteId = strtolower(trim($siteId));
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $siteId) !== 1) {
            throw new \InvalidArgumentException(
                'OPUS_SITE_GIT_SITE_ID_INVALID'
            );
        }
        $candidate = realpath($this->sitesRoot . '/' . $siteId);
        $candidate = is_string($candidate)
            ? rtrim(str_replace('\\', '/', $candidate), '/')
            : '';
        if ($candidate === ''
            || !str_starts_with($candidate, $this->sitesRoot . '/')
            || !is_dir($candidate)) {
            throw new \RuntimeException('OPUS_SITE_GIT_SITE_ROOT_INVALID');
        }
        $site = $this->loader->read($candidate . '/config/site.json');
        if (($site['contract'] ?? null) !== 'OPUS_SITE_STANDARD_CONTRACT_CORE'
            || strtolower(trim((string) ($site['site_id'] ?? '')))
                !== $siteId) {
            throw new \RuntimeException(
                'OPUS_SITE_GIT_SITE_CONTRACT_INVALID'
            );
        }
        return $siteId;
    }

    private function sitePrefix(string $siteId): string
    {
        return 'sites/' . $siteId;
    }

    private function relativePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === ''
            || str_contains($path, "\0")
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:\//', $path) === 1
            || preg_match('/[\x00-\x1F\x7F:]/', $path) === 1
            || preg_match('/^[A-Za-z0-9._\/-]{1,512}$/D', $path) !== 1) {
            throw new \InvalidArgumentException(
                'OPUS_SITE_GIT_PATH_INVALID'
            );
        }
        $path = trim($path, '/');
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.'
                || $segment === '..' || strtolower($segment) === '.git') {
                throw new \InvalidArgumentException(
                    'OPUS_SITE_GIT_PATH_INVALID'
                );
            }
        }
        return $path;
    }

    private function repositoryPath(
        string $siteId,
        string $relativePath,
        bool $mustExist
    ): string {
        $repositoryPath = $this->sitePrefix($siteId)
            . '/' . $relativePath;
        $candidate = $this->opusRoot . '/' . $repositoryPath;
        if (is_link($candidate)) {
            throw new \RuntimeException(
                'OPUS_SITE_GIT_SYMLINK_FORBIDDEN'
            );
        }
        if (is_dir($candidate)) {
            throw new \RuntimeException(
                'OPUS_SITE_GIT_DIRECTORY_FORBIDDEN'
            );
        }
        if ($mustExist) {
            $real = realpath($candidate);
            $siteRoot = realpath($this->sitesRoot . '/' . $siteId);
            $real = is_string($real) ? str_replace('\\', '/', $real) : '';
            $siteRoot = is_string($siteRoot)
                ? rtrim(str_replace('\\', '/', $siteRoot), '/')
                : '';
            if ($real === '' || $siteRoot === ''
                || !str_starts_with($real, $siteRoot . '/')
                || !is_file($real)) {
                throw new \RuntimeException(
                    'OPUS_SITE_GIT_FILE_INVALID'
                );
            }
        } else {
            $parent = realpath(dirname($candidate));
            $siteRoot = realpath($this->sitesRoot . '/' . $siteId);
            $parent = is_string($parent)
                ? rtrim(str_replace('\\', '/', $parent), '/')
                : '';
            $siteRoot = is_string($siteRoot)
                ? rtrim(str_replace('\\', '/', $siteRoot), '/')
                : '';
            if ($parent !== '' && $siteRoot !== ''
                && $parent !== $siteRoot
                && !str_starts_with($parent, $siteRoot . '/')) {
                throw new \RuntimeException(
                    'OPUS_SITE_GIT_PATH_SCOPE_INVALID'
                );
            }
        }
        return $repositoryPath;
    }

    private function existingTarget(
        string $siteId,
        string $relativePath
    ): string {
        $repositoryPath = $this->repositoryPath(
            $siteId,
            $relativePath,
            true
        );
        $target = realpath($this->opusRoot . '/' . $repositoryPath);
        if (!is_string($target) || !is_file($target) || is_link($target)) {
            throw new \RuntimeException('OPUS_SITE_GIT_FILE_INVALID');
        }
        return $target;
    }

    private function commitMessage(string $message): string
    {
        $message = trim($message);
        if ($message === ''
            || strlen($message) > 200
            || preg_match('/[\x00-\x1F\x7F]/', $message) === 1) {
            throw new \InvalidArgumentException(
                'OPUS_SITE_GIT_COMMIT_MESSAGE_INVALID'
            );
        }
        return $message;
    }

    /** @return array{content:string,truncated:bool} */
    private function boundedDiff(string $diff): array
    {
        if (strlen($diff) <= self::MAX_DIFF_BYTES) {
            return ['content' => $diff, 'truncated' => false];
        }
        return [
            'content' => substr($diff, 0, self::MAX_DIFF_BYTES),
            'truncated' => true,
        ];
    }

    private function displayText(string $value, int $maxBytes): string
    {
        $value = trim(preg_replace('/[\x00-\x1F\x7F]/', ' ', $value) ?? '');
        return strlen($value) <= $maxBytes
            ? $value
            : substr($value, 0, $maxBytes);
    }

    /**
     * @param array<string,mixed> $context
     * @param callable():array<string,mixed> $callback
     * @return array<string,mixed>
     */
    private function observed(
        string $operation,
        array $context,
        callable $callback
    ): array {
        $ownedTrace = false;
        $spanId = null;
        $traceId = null;
        if ($this->profiler !== null) {
            if ($this->profiler->getActiveTrace() === null) {
                $this->profiler->start();
                $ownedTrace = true;
            }
            $traceId = $this->profiler->getActiveTrace()?->getTraceId();
            $spanId = $this->profiler->beginSpan(
                'git',
                'git.' . $operation,
                $this->safeContext($context),
                $this->parentSpanId
            );
        }

        try {
            $result = $callback();
            $summary = [
                'operation' => $operation,
                'status' => 'succeeded',
            ];
            $this->logger?->info(
                'git',
                'OPUS_SITE_GIT_OPERATION_SUCCEEDED',
                $this->safeContext([...$context, ...$summary]),
                $traceId
            );
            if ($spanId !== null) {
                $this->profiler?->endSpan(
                    $spanId,
                    'success',
                    $summary
                );
            }
            if ($ownedTrace) {
                $this->profiler?->stop($summary);
            }
            return $result;
        } catch (Throwable $error) {
            $summary = [
                'operation' => $operation,
                'status' => 'failed',
                'error_code' => $this->safeErrorCode($error),
            ];
            $this->logger?->error(
                'git',
                'OPUS_SITE_GIT_OPERATION_FAILED',
                $this->safeContext([...$context, ...$summary]),
                $traceId
            );
            if ($spanId !== null) {
                $this->profiler?->endSpan(
                    $spanId,
                    'error',
                    $summary
                );
            }
            if ($ownedTrace) {
                $this->profiler?->stop($summary);
            }
            throw $error;
        }
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function safeContext(array $context): array
    {
        $allowed = [
            'application_id', 'path', 'operation', 'status',
            'limit', 'error_code',
        ];
        return array_intersect_key($context, array_flip($allowed));
    }

    private function safeErrorCode(Throwable $error): string
    {
        $message = trim($error->getMessage());
        return preg_match('/^[A-Z0-9_:-]{3,240}$/D', $message) === 1
            ? $message
            : 'OPUS_SITE_GIT_OPERATION_FAILED';
    }
}
