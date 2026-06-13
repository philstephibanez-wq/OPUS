param(
    [switch]$Apply
)

$ErrorActionPreference = 'Stop'

function Fail([string]$Message) {
    Write-Error $Message
    exit 1
}

function Get-ItemParentPath($Item) {
    if ($Item.PSIsContainer) {
        if ($null -eq $Item.Parent) {
            return $null
        }
        return $Item.Parent.FullName
    }

    return $Item.DirectoryName
}

function Read-TextStrict([string]$Path) {
    if (-not (Test-Path -LiteralPath $Path)) {
        Fail ('RENAMING_CONTRACT_FAILED: text file not found: ' + $Path)
    }

    $text = [System.IO.File]::ReadAllText($Path)
    if ($null -eq $text) {
        return ''
    }
    return $text
}

function Write-TextUtf8NoBom([string]$Path, [string]$Content) {
    if ($null -eq $Content) {
        $Content = ''
    }

    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $Content, $utf8NoBom)
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

$asapDir = Join-Path $Root 'framework\Asap'
$opusDir = Join-Path $Root 'framework\Opus'
$asapExists = Test-Path $asapDir
$opusExists = Test-Path $opusDir
$partialResume = (-not $asapExists) -and $opusExists

$gitStatus = (& git status --short 2>$null)
if ($LASTEXITCODE -ne 0) {
    Fail 'RENAMING_CONTRACT_FAILED: git status failed.'
}

if ((($gitStatus | Measure-Object).Count -gt 0) -and (-not $partialResume)) {
    Fail 'RENAMING_CONTRACT_FAILED: working tree is not clean. Commit/stash before renaming.'
}

if ($partialResume) {
    Write-Host 'RESUME_PARTIAL_RENAME: framework\Opus already exists and framework\Asap is absent.'
}

if ($asapExists -and $opusExists) {
    Fail 'RENAMING_CONTRACT_FAILED: both framework\Asap and framework\Opus exist.'
}

if ((-not $asapExists) -and (-not $opusExists)) {
    Fail 'RENAMING_CONTRACT_FAILED: neither framework\Asap nor framework\Opus exists.'
}

if (-not $Apply) {
    Write-Host 'DRY_RUN_ONLY'
    if ($partialResume) {
        Write-Host 'Partial rename detected. Run again with -Apply to resume text/package normalization.'
    } else {
        Write-Host 'Run again with -Apply to perform the filesystem rename and text replacements.'
    }
    exit 0
}

Write-Host 'STEP 1/4 Rename framework directory'
if ($asapExists) {
    Rename-Item -LiteralPath $asapDir -NewName 'Opus'
} else {
    Write-Host 'STEP 1/4 SKIP: framework directory already renamed.'
}

Write-Host 'STEP 2/4 Rename file and directory names containing ASAP/Asap/asap'
$scriptPath = $PSCommandPath
$items = Get-ChildItem -LiteralPath $Root -Recurse -Force |
    Where-Object {
        $_.FullName -notmatch '\\.git(\\|$)' -and
        $_.FullName -ne $scriptPath -and
        ($_.Name -cmatch 'ASAP|Asap|asap')
    } |
    Sort-Object { $_.FullName.Length } -Descending

foreach ($item in $items) {
    $newName = $item.Name.Replace('ASAP', 'OPUS').Replace('Asap', 'Opus').Replace('asap', 'opus')
    if ($newName -ne $item.Name) {
        $parentPath = Get-ItemParentPath $item
        if ([string]::IsNullOrWhiteSpace($parentPath)) {
            Fail ('RENAMING_CONTRACT_FAILED: parent path could not be resolved for: ' + $item.FullName)
        }

        $target = Join-Path $parentPath $newName
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
        $_.FullName -ne $scriptPath -and
        $extensions -contains $_.Extension.ToLowerInvariant()
    }

foreach ($file in $files) {
    $path = $file.FullName
    $raw = Read-TextStrict $path
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
        Write-TextUtf8NoBom $path $updated
    }
}

Write-Host 'STEP 4/4 Normalize composer.json identity'
$composerPath = Join-Path $Root 'composer.json'
$composerText = Read-TextStrict $composerPath
$composerText = $composerText.TrimStart([char]0xFEFF)
if ([string]::IsNullOrWhiteSpace($composerText)) {
    Fail 'RENAMING_CONTRACT_FAILED: composer.json is empty after migration interruption. Restore composer.json from git before rerunning.'
}
$composer = $composerText | ConvertFrom-Json
$composer.name = 'logandplay/opus'
$composer.description = 'Opus 8.1.0 "Berlioz" PHP 8 framework core'
$composer.version = '8.1.0'
$composer.autoload.'psr-4' = [ordered]@{ 'Opus\' = 'framework/Opus/' }
$normalizedComposer = ($composer | ConvertTo-Json -Depth 20) + "`n"
Write-TextUtf8NoBom $composerPath $normalizedComposer

Write-Host 'OPUS_RENAME_APPLY_OK'
Write-Host 'Next required checks:'
Write-Host '  composer dump-autoload'
Write-Host '  run the existing smoke/recipe suite'
Write-Host '  git status --short'
