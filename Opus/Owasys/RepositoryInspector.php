<?php
declare(strict_types=1);

namespace Opus\Owasys;

use RuntimeException;

/**
 * Read-only Git inspection for an OWASYS-managed OPUS application.
 *
 * No arbitrary command is accepted. Every Git operation is predefined and the
 * inspected application must remain inside the configured OPUS root.
 */
final class RepositoryInspector
{
    public const CONTRACT = 'OWASYS_REPOSITORY_INSPECTION_V1';

    public function __construct(private readonly string $opusRoot)
    {
    }

    /** @return array<string,mixed> */
    public function inspect(string $applicationRoot, int $historyLimit = 10): array
    {
        if ($historyLimit < 1 || $historyLimit > 50) {
            throw new RuntimeException('OWASYS_GIT_HISTORY_LIMIT_INVALID');
        }

        $applicationPath = $this->resolveApplicationRoot($applicationRoot);
        $repositoryRoot = trim($this->git($applicationPath, ['rev-parse', '--show-toplevel']));
        if ($repositoryRoot === '') {
            throw new RuntimeException('OWASYS_GIT_REPOSITORY_NOT_FOUND');
        }

        $repositoryPath = realpath($repositoryRoot);
        if (!is_string($repositoryPath) || !$this->isInside($repositoryPath, $this->resolvedOpusRoot())) {
            throw new RuntimeException('OWASYS_GIT_REPOSITORY_OUTSIDE_OPUS_ROOT');
        }
        if (!$this->isInside($applicationPath, $repositoryPath)) {
            throw new RuntimeException('OWASYS_GIT_APPLICATION_OUTSIDE_REPOSITORY');
        }

        $branch = trim($this->git($repositoryPath, ['branch', '--show-current'], true));
        $head = trim($this->git($repositoryPath, ['rev-parse', '--verify', 'HEAD'], true));
        $statusOutput = $this->git($repositoryPath, ['status', '--porcelain=v1', '--untracked-files=all'], true);
        $status = $this->parseStatus($statusOutput);
        $historyOutput = $this->git($repositoryPath, [
            'log', '-n', (string) $historyLimit, '--date=iso-strict', '--pretty=format:%H%x09%ad%x09%an%x09%s',
        ], true);

        return [
            'contract' => self::CONTRACT,
            'application_root' => $this->relativeToOpus($applicationPath),
            'repository_root' => $this->relativeToOpus($repositoryPath),
            'branch' => $branch !== '' ? $branch : null,
            'head' => $head !== '' ? $head : null,
            'clean' => $status === [],
            'changes' => $status,
            'history' => $this->parseHistory($historyOutput),
            'capabilities' => [
                'status' => true,
                'diff' => true,
                'history' => true,
                'write_operations' => false,
                'arbitrary_commands' => false,
            ],
        ];
    }

    public function diff(string $applicationRoot, ?string $relativePath = null): string
    {
        $applicationPath = $this->resolveApplicationRoot($applicationRoot);
        $repositoryRoot = trim($this->git($applicationPath, ['rev-parse', '--show-toplevel']));
        $repositoryPath = realpath($repositoryRoot);
        if (!is_string($repositoryPath) || !$this->isInside($repositoryPath, $this->resolvedOpusRoot())) {
            throw new RuntimeException('OWASYS_GIT_REPOSITORY_INVALID');
        }

        $arguments = ['diff', '--no-ext-diff', '--'];
        if ($relativePath !== null) {
            $target = $this->resolveFileInsideApplication($applicationPath, $relativePath);
            $arguments[] = $this->relativePath($repositoryPath, $target);
        } else {
            $arguments[] = $this->relativePath($repositoryPath, $applicationPath);
        }

        return $this->git($repositoryPath, $arguments, true);
    }

    /** @return list<array{index:string,worktree:string,path:string}> */
    private function parseStatus(string $output): array
    {
        $changes = [];
        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            if (strlen($line) < 4) {
                continue;
            }
            $changes[] = [
                'index' => $line[0],
                'worktree' => $line[1],
                'path' => substr($line, 3),
            ];
        }
        return $changes;
    }

    /** @return list<array{hash:string,date:string,author:string,subject:string}> */
    private function parseHistory(string $output): array
    {
        $history = [];
        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            if ($line === '') {
                continue;
            }
            $parts = explode("\t", $line, 4);
            if (count($parts) !== 4) {
                continue;
            }
            $history[] = ['hash' => $parts[0], 'date' => $parts[1], 'author' => $parts[2], 'subject' => $parts[3]];
        }
        return $history;
    }

    /** @param list<string> $arguments */
    private function git(string $workingDirectory, array $arguments, bool $allowFailure = false): string
    {
        $command = 'git -C ' . escapeshellarg($workingDirectory);
        foreach ($arguments as $argument) {
            $command .= ' ' . escapeshellarg($argument);
        }

        $output = [];
        $code = 0;
        exec($command . ' 2>&1', $output, $code);
        if ($code !== 0 && !$allowFailure) {
            throw new RuntimeException('OWASYS_GIT_COMMAND_FAILED');
        }
        return implode("\n", $output);
    }

    private function resolveApplicationRoot(string $applicationRoot): string
    {
        $relative = trim(str_replace('\\', '/', $applicationRoot), '/');
        if ($relative === '' || str_contains($relative, '..') || preg_match('/^[A-Za-z]:/', $relative) === 1) {
            throw new RuntimeException('OWASYS_GIT_APPLICATION_ROOT_INVALID');
        }
        $path = realpath($this->resolvedOpusRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if (!is_string($path) || !is_dir($path) || !$this->isInside($path, $this->resolvedOpusRoot())) {
            throw new RuntimeException('OWASYS_GIT_APPLICATION_ROOT_MISSING');
        }
        return $path;
    }

    private function resolveFileInsideApplication(string $applicationPath, string $relativePath): string
    {
        $relative = trim(str_replace('\\', '/', $relativePath), '/');
        if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '.git/')) {
            throw new RuntimeException('OWASYS_GIT_DIFF_PATH_INVALID');
        }
        $path = realpath($applicationPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if (!is_string($path) || !is_file($path) || !$this->isInside($path, $applicationPath)) {
            throw new RuntimeException('OWASYS_GIT_DIFF_PATH_MISSING');
        }
        return $path;
    }

    private function resolvedOpusRoot(): string
    {
        $root = realpath($this->opusRoot);
        if (!is_string($root) || !is_dir($root)) {
            throw new RuntimeException('OWASYS_OPUS_ROOT_INVALID');
        }
        return $root;
    }

    private function relativeToOpus(string $path): string
    {
        return $this->relativePath($this->resolvedOpusRoot(), $path);
    }

    private function relativePath(string $root, string $path): string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $path = str_replace('\\', '/', $path);
        return ltrim(substr($path, strlen($root)), '/');
    }

    private function isInside(string $path, string $root): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $root = rtrim(str_replace('\\', '/', $root), '/');
        return $path === $root || str_starts_with($path . '/', $root . '/');
    }
}
