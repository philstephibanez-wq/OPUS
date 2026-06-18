<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Service;

use RuntimeException;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Build the public Opus download/install view-model.
 *
 * Contract:
 *   RefBook owns the editorial install page, but versions and package URLs come
 *   from the Opus release manifest. Public download URLs must not point to GitHub.
 */
final class ReferenceOpusReleaseService
{
    public function __construct(
        private readonly string $releaseRoot,
        private readonly string $opusRoot,
        private readonly string $language
    ) {
    }

    public function pageTitle(): string
    {
        return (string) $this->texts()['title'];
    }

    /** @return array<string,mixed> */
    public function viewModel(): array
    {
        $manifest = $this->releaseManifest();
        $texts = $this->texts();
        $installed = $this->installedVersion();
        $latest = (string) ($manifest['latest_version'] ?? '');

        if ($latest === '') {
            throw new RuntimeException('OPUS_REFBOOK_RELEASE_LATEST_VERSION_MISSING');
        }

        $statusKey = $this->statusKey($installed, $latest);

        return [
            'texts' => $texts,
            'manifest' => $manifest,
            'installed_version' => $installed,
            'latest_version' => $latest,
            'status_key' => $statusKey,
            'status_label' => (string) ($texts[$statusKey] ?? $statusKey),
            'channels' => $this->channels($manifest),
            'requirements' => $manifest['requirements'] ?? [],
        ];
    }

    /** @return array<string,mixed> */
    private function releaseManifest(): array
    {
        $file = rtrim($this->releaseRoot, '/\\') . DIRECTORY_SEPARATOR . 'opus.json';
        if (!is_file($file)) {
            throw new RuntimeException('OPUS_REFBOOK_RELEASE_MANIFEST_MISSING=' . $file);
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            throw new RuntimeException('OPUS_REFBOOK_RELEASE_MANIFEST_INVALID=' . $file);
        }

        if (($data['schema'] ?? null) !== 'OPUS_RELEASE_MANIFEST_V1') {
            throw new RuntimeException('OPUS_REFBOOK_RELEASE_MANIFEST_SCHEMA_INVALID');
        }

        foreach (($data['channels'] ?? []) as $channelName => $channel) {
            if (!is_array($channel)) {
                throw new RuntimeException('OPUS_REFBOOK_RELEASE_CHANNEL_INVALID=' . (string) $channelName);
            }
            $url = (string) ($channel['package_url'] ?? '');
            if ($url === '') {
                throw new RuntimeException('OPUS_REFBOOK_RELEASE_PACKAGE_URL_MISSING=' . (string) $channelName);
            }
            if (stripos($url, 'github.com') !== false || stripos($url, 'raw.githubusercontent.com') !== false) {
                throw new RuntimeException('OPUS_REFBOOK_RELEASE_GITHUB_URL_FORBIDDEN=' . $url);
            }
        }

        return $data;
    }

    /** @return array<string,mixed> */
    private function texts(): array
    {
        $file = dirname(rtrim($this->releaseRoot, '/\\')) . DIRECTORY_SEPARATOR . 'download-install.json';
        if (!is_file($file)) {
            throw new RuntimeException('OPUS_REFBOOK_DOWNLOAD_I18N_FILE_MISSING=' . $file);
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            throw new RuntimeException('OPUS_REFBOOK_DOWNLOAD_I18N_JSON_INVALID=' . $file);
        }

        if (($data['schema'] ?? null) !== 'OPUS_REFBOOK_DOWNLOAD_INSTALL_I18N_V1') {
            throw new RuntimeException('OPUS_REFBOOK_DOWNLOAD_I18N_SCHEMA_INVALID');
        }

        $texts = $data['languages'][$this->language] ?? null;
        if (!is_array($texts)) {
            throw new RuntimeException('OPUS_REFBOOK_DOWNLOAD_I18N_MISSING=' . $this->language);
        }

        return $texts;
    }

    private function installedVersion(): string
    {
        $composer = rtrim(str_replace('\\', '/', $this->opusRoot), '/') . '/composer.json';
        if (!is_file($composer)) {
            throw new RuntimeException('OPUS_REFBOOK_OPUS_COMPOSER_MISSING=' . $composer);
        }

        $data = json_decode((string) file_get_contents($composer), true);
        if (!is_array($data)) {
            throw new RuntimeException('OPUS_REFBOOK_OPUS_COMPOSER_INVALID=' . $composer);
        }

        $version = (string) ($data['version'] ?? '');
        if ($version === '') {
            throw new RuntimeException('OPUS_REFBOOK_OPUS_VERSION_MISSING=' . $composer);
        }

        return $version;
    }

    private function statusKey(string $installed, string $latest): string
    {
        $cmp = version_compare($installed, $latest);
        if ($cmp === 0) {
            return 'status_up_to_date';
        }
        if ($cmp < 0) {
            return 'status_update_available';
        }
        return 'status_ahead';
    }

    /**
     * @param array<string,mixed> $manifest
     * @return list<array<string,string>>
     */
    private function channels(array $manifest): array
    {
        $channels = [];
        foreach (($manifest['channels'] ?? []) as $name => $channel) {
            if (!is_array($channel)) {
                continue;
            }
            $channels[] = [
                'name' => (string) $name,
                'label' => (string) ($channel['label'] ?? $name),
                'version' => (string) ($channel['version'] ?? ''),
                'package_url' => (string) ($channel['package_url'] ?? ''),
                'checksum_sha256' => (string) ($channel['checksum_sha256'] ?? ''),
                'published_at' => (string) ($channel['published_at'] ?? ''),
                'notes' => (string) ($channel['notes'] ?? ''),
            ];
        }

        if ($channels === []) {
            throw new RuntimeException('OPUS_REFBOOK_RELEASE_CHANNELS_EMPTY');
        }

        return $channels;
    }
}
