<#
.SYNOPSIS
  Uploads CSA_Prep files to InfinityFree via FTP (curl), replacing the manual
  FTP-client + phpMyAdmin workflow in webapp/DEPLOY.md for the file-upload step.
  Does NOT touch the database -- SQL migrations still go through phpMyAdmin by hand,
  same as always (InfinityFree blocks external MySQL connections entirely).

.PARAMETER Target
  "staging" or "production" -- selects which section of deploy.config.local.json to use.
  Required, no default, specifically so a bare invocation can never accidentally hit
  production.

.PARAMETER Files
  Optional list of paths, relative to that target's localSourceDir, to upload.
  Omit to upload the entire localSourceDir tree.

.PARAMETER DryRun
  List what would be uploaded without actually uploading anything.

.EXAMPLE
  # Incremental deploy of just the Exhibition Exam files, to staging
  .\deploy.ps1 -Target staging -Files @('api/exhibition_create.php','exhibition.html')

.EXAMPLE
  # Full-tree staging deploy, preview only
  .\deploy.ps1 -Target staging -DryRun

.EXAMPLE
  # Full-tree production deploy (will ask for typed confirmation)
  .\deploy.ps1 -Target production
#>
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('staging', 'production')]
    [string]$Target,

    [string[]]$Files,

    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'
$scriptDir = $PSScriptRoot
$configPath = Join-Path $scriptDir 'deploy.config.local.json'

if (-not (Test-Path $configPath)) {
    Write-Host "Missing $configPath" -ForegroundColor Red
    Write-Host "Copy deploy.config.example.json to deploy.config.local.json and fill in your InfinityFree FTP credentials (from the InfinityFree control panel's FTP Accounts page -- separate from your MySQL credentials)." -ForegroundColor Yellow
    exit 1
}

$config = Get-Content $configPath -Raw | ConvertFrom-Json
$cfg = $config.$Target
if (-not $cfg) {
    Write-Host "No '$Target' section found in deploy.config.local.json" -ForegroundColor Red
    exit 1
}
if (-not $DryRun -and ([string]::IsNullOrWhiteSpace($cfg.username) -or [string]::IsNullOrWhiteSpace($cfg.password))) {
    Write-Host "deploy.config.local.json's '$Target' section is missing username/password." -ForegroundColor Red
    exit 1
}

$localSourceDir = Resolve-Path (Join-Path $scriptDir $cfg.localSourceDir)
if (-not (Test-Path $localSourceDir)) {
    Write-Host "localSourceDir does not exist: $localSourceDir" -ForegroundColor Red
    exit 1
}

# Build the list of (localPath, relativePath) pairs to upload.
$fileList = @()
if ($Files -and $Files.Count -gt 0) {
    foreach ($f in $Files) {
        $full = Join-Path $localSourceDir $f
        if (-not (Test-Path $full -PathType Leaf)) {
            Write-Host "File not found, skipping: $full" -ForegroundColor Yellow
            continue
        }
        $fileList += [PSCustomObject]@{ Local = $full; Relative = $f.Replace('\', '/') }
    }
} else {
    Get-ChildItem -Path $localSourceDir -Recurse -File | ForEach-Object {
        $rel = $_.FullName.Substring($localSourceDir.Path.Length + 1).Replace('\', '/')
        $fileList += [PSCustomObject]@{ Local = $_.FullName; Relative = $rel }
    }
}

if ($fileList.Count -eq 0) {
    Write-Host "Nothing to upload." -ForegroundColor Yellow
    exit 0
}

Write-Host "Target: $Target  ($($cfg.host):$($cfg.port)$($cfg.remoteBasePath))" -ForegroundColor Cyan
Write-Host "$($fileList.Count) file(s) to upload from $localSourceDir" -ForegroundColor Cyan

if ($DryRun) {
    $fileList | ForEach-Object { Write-Host "  [dry-run] $($_.Relative)" }
    exit 0
}

if (-not $Files -or $Files.Count -eq 0) {
    Write-Host ""
    Write-Host "This is a FULL-TREE upload of every file under $localSourceDir to '$Target'." -ForegroundColor Yellow
    $confirm = Read-Host "Type the word '$Target' to confirm"
    if ($confirm -ne $Target) {
        Write-Host "Aborted." -ForegroundColor Yellow
        exit 1
    }
}

$userPass = "$($cfg.username):$($cfg.password)"
$failed = @()
$ok = 0

foreach ($item in $fileList) {
    $remoteUrl = "ftp://$($cfg.host):$($cfg.port)$($cfg.remoteBasePath)/$($item.Relative)"
    & curl.exe --ftp-create-dirs --silent --show-error --user $userPass -T "$($item.Local)" "$remoteUrl"
    if ($LASTEXITCODE -eq 0) {
        $ok++
        Write-Host "  uploaded: $($item.Relative)" -ForegroundColor Green
    } else {
        $failed += $item.Relative
        Write-Host "  FAILED:   $($item.Relative) (curl exit $LASTEXITCODE)" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "$ok uploaded, $($failed.Count) failed." -ForegroundColor Cyan
if ($failed.Count -gt 0) {
    Write-Host "Failed files:" -ForegroundColor Red
    $failed | ForEach-Object { Write-Host "  $_" }
    exit 1
}
