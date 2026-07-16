<?php
declare(strict_types=1);

namespace Opus\Owasys;

use RuntimeException;

/**
 * Safe Git write operations for one OWASYS-managed application.
 *
 * Only predefined add/commit operations are exposed. The application must stay
 * inside the configured OPUS root and only its own subtree may be staged.
 */
final class RepositoryOperator
{
    public const CONTRACT = 'OWASYS_REPOSITORY_OPERATOR_V1';

    public function __construct(private readonly string $opusRoot)
    {
    }

    /** @return array<string,mixed> */
    public function stageApplication(string $applicationRoot): array
    {
        [$applicationPath, $repositoryPath] = $this->resolveRepository($applicationRoot);
        $relativeApplication = $this->relativePath($repositoryPath, $applicationPath);

        $this->git($repositoryPath, ['add', '--', $relativeApplication]);
        $staged = trim($this->git($repositoryPath, ['diff', '--cached', '--name-only', '--', $relativeApplication], true));
        $files = $staged === '' ? [] : array_values(array_filter(preg_split('/\R/', $staged) ?: [], 'is_string'));

        return [
            'contract' => self::CONTRACT,
            'action' => 'stage-application',
            'application_root' => $this->relativeToOpus($applicationPath),
            'repository_root' => $this->relativeToOpus($repositoryPath),
            'staged_files' => $files,
            'write_operation' => true,
            'arbitrary_command' => false,
        ];
    }

    /** @return array<string,mixed> */
    public function commitApplication(string $applicationRoot, string $message): array
    {
        $message = trim($message);
        if ($message === '' || strlen($message) > 200 || str_contains($message, "\0") || preg_match('/[\r\n]/', $message) === 1) {
            throw new RuntimeException('OWASYS_GIT_COMMIT_MESSAGE_INVALID');
        }

        [$applicationPath, $repositoryPath] = $this->resolveRepository($applicationRoot);
        $relativeApplication = $this->relativePath($repositoryPath, $applicationPath);
        $staged = trim($this->git($repositoryPath, ['diff', '--cached', '--name-only', '--', $relativeApplication], true));
        if ($staged === '') {
            throw new RuntimeException('OWASYS_GIT_NOTHING_STAGED_FOR_APPLICATION');
        }

        $this->git($repositoryPath, ['commit', '-m', $message, '--', $relativeApplication]);
        $head = trim($this->git($repositoryPath, ['rev-parse', '--verify', 'HEAD']));

        return [
            'contract' => self::CONTRACT,
            'action' => 'commit-application',
            'application_root' => $this->relativeToOpus($applicationPath),
            'repository_root' => $this->relativeToOpus($repositoryPath),
            'commit' => $head,
            'message' => $message,
            'write_operation' => true,
            'push_performed' => false,
            'arbitrary_command' => false,
        ];
    }

    /** @return array{0:string,1:string} */
    private function resolveRepository(string $applicationRoot): array
    {
        $applicationPath = $this->resolveApplicationRoot($applicationRoot);
        $repositoryRoot = trim($this->git($applicationPath, ['rev-parse', '--show-toplevel']));
        $repositoryPath = realpath($repositoryRoot);
        if (!is_string($repositoryPath) || !$this->isInside($repositoryPath, $this->resolvedOpusRoot())) {
            throw new RuntimeException('OWASYS_GIT_REPOSITORY_INVALID');
        }
        if (!$this->isInside($applicationPath, $repositoryPath)) {
            throw new RuntimeException('OWASYS_GIT_APPLICATION_OUTSIDE_REPOSITORY');
        }
        return [$applicationPath, $repositoryPath];
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
            throw new RuntimeException('OWASYS_GIT_COMMAND_FAILED: ' . implode("\n", $output));
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
