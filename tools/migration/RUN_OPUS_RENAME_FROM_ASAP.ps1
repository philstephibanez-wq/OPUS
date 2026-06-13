param(
    [switch]$Apply
)

$ErrorActionPreference = 'Stop'

function Fail([string]$Message) {
    Write-Error $Message
    exit 1
}

$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $Root

Write-Host 'OPUS_RENAME_FROM_ASAP'
Write-Host ('Root=' + $Root)

if (-not (Test-Path (Join-Path $Root 'composer.json'))) {
    Fail 'RENAMING_CONTRACT_FAILED: composer.json not found at repository root.'
}

if (-not (Test-Path (Join-Path $Root '.git'))) {
    Fail 'RENAMING_CONTRACT_FAILED: .git directory not found. Run from a real framework clone.'
}

$gitStatus = (& git status --short 2>$null)
if ($LASTEXITCODE -ne 0) {
    Fail 'RENAMING_CONTRACT_FAILED: git status failed.'
}

if (($gitStatus | Measure-Object).Count -gt 0) {
    Fail 'RENAMING_CONTRACT_FAILED: working tree is not clean. Commit/stash before renaming.'
}

$asapDir = Join-Path $Root 'framework\Asap'
$opusDir = Join-Path $Root 'framework\Opus'

if ((Test-Path $asapDir) -and (Test-Path $opusDir)) {
    Fail 'RENAMING_CONTRACT_FAILED: both framework\Asap and framework\Opus exist.'
}

if (-not (Test-Path $asapDir)) {
    Fail 'RENAMING_CONTRACT_FAILED: framework\Asap not found. Nothing official to rename.'
}

if (-not $Apply) {
    Write-Host 'DRY_RUN_ONLY'
    Write-Host 'Run again with -Apply to perform the filesystem rename and text replacements.'
    exit 0
}

Write-Host 'STEP 1/4 Rename framework directory'
Rename-Item -LiteralPath $asapDir -NewName 'Opus'

Write-Host 'STEP 2/4 Rename file and directory names containing ASAP/Asap/asap'
$items = Get-ChildItem -LiteralPath $Root -Recurse -Force |
    Where-Object {
        $_.FullName -notmatch '\\.git(\\|$)' -and
        ($_.Name -cmatch 'ASAP|Asap|asap')
    } |
    Sort-Object { $_.FullName.Length } -Descending

foreach ($item in $items) {
    $newName = $item.Name.Replace('ASAP', 'OPUS').Replace('Asap', 'Opus').Replace('asap', 'opus')
    if ($newName -ne $item.Name) {
        $target = Join-Path $item.DirectoryName $newName
        if (Test-Path -LiteralPath $target) {
            Fail ('RENAMING_CONTRACT_FAILED: target already exists: ' + $target)
        }
        Rename-Item -LiteralPath $item.FullName -NewName $newName
    }
}

Write-Host 'STEP 3/4 Replace textual identifiers'
$extensions = @('.php', '.md', '.json', '.xml', '.yml', '.yaml', '.cmd', '.bat', '.ps1', '.js', '.css', '.html', '.htm', '.twig', '.txt', '.ini')
$files = Get-ChildItem -LiteralPath $Root -Recurse -File -Force |
    Where-Object {
        $_.FullName -notmatch '\\.git(\\|$)' -and
        $extensions -contains $_.Extension.ToLowerInvariant()
    }

foreach ($file in $files) {
    $path = $file.FullName
    $raw = Get-Content -LiteralPath $path -Raw
    $updated = $raw

    $updated = $updated.Replace('logandplay/asap', 'logandplay/opus')
    $updated = $updated.Replace('ASAP\\', 'Opus\\')
    $updated = $updated.Replace('namespace ASAP', 'namespace Opus')
    $updated = $updated.Replace('use ASAP\\', 'use Opus\\')
    $updated = $updated.Replace('framework/Asap', 'framework/Opus')
    $updated = $updated.Replace('framework\\Asap', 'framework\\Opus')
    $updated = $updated.Replace('ASAP_REF_BOOK', 'OPUS_REF_BOOK')
    $updated = $updated.Replace('ASAP_REFBOOK', 'OPUS_REFBOOK')
    $updated = $updated.Replace('ASAP_', 'OPUS_')
    $updated = $updated.Replace('_ASAP_', '_OPUS_')
    $updated = $updated.Replace('asap_', 'opus_')
    $updated = $updated.Replace('asap-', 'opus-')
    $updated = $updated.Replace('asap.', 'opus.')
    $updated = $updated.Replace('ASAP ', 'Opus ')
    $updated = $updated.Replace('ASAP —', 'Opus —')
    $updated = $updated.Replace('ASAP -', 'Opus -')
    $updated = $updated.Replace('ASAP:', 'Opus:')
    $updated = $updated.Replace('ASAP.', 'Opus.')
    $updated = $updated.Replace('ASAP,', 'Opus,')
    $updated = $updated.Replace('ASAP)', 'Opus)')
    $updated = $updated.Replace('(ASAP', '(Opus')
    $updated = $updated.Replace('Asap', 'Opus')

    if ($updated -ne $raw) {
        Set-Content -LiteralPath $path -Value $updated -NoNewline -Encoding UTF8
    }
}

Write-Host 'STEP 4/4 Normalize composer.json identity'
$composerPath = Join-Path $Root 'composer.json'
$composer = Get-Content -LiteralPath $composerPath -Raw | ConvertFrom-Json
$composer.name = 'logandplay/opus'
$composer.description = 'Opus 8.1.0 "Berlioz" PHP 8 framework core'
$composer.version = '8.1.0'
$composer.autoload.'psr-4' = [ordered]@{ 'Opus\' = 'framework/Opus/' }
($composer | ConvertTo-Json -Depth 20) + "`n" | Set-Content -LiteralPath $composerPath -Encoding UTF8

Write-Host 'OPUS_RENAME_APPLY_OK'
Write-Host 'Next required checks:'
Write-Host '  composer dump-autoload'
Write-Host '  run the existing smoke/recipe suite'
Write-Host '  git status --short'
