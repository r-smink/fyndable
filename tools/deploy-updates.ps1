#Requires -Version 5.1
<#
.SYNOPSIS
    Deploy a fyndable-client release to updates.fyndable.ai.
.DESCRIPTION
    Uploads the built zip + latest.json to the remote webdir. Use -Archive
    to move older fyndable-client-*.zip files to the archive/ folder.
#>
param(
    [Parameter(Mandatory = $true)]
    [string]$Version,

    [Parameter(Mandatory = $true)]
    [string]$User,

    [string]$Server = "updates.fyndable.ai",

    [string]$Key,

    [string]$RemotePath = "/var/www/updates.fyndable.ai/public_html/fyndable-client",

    [string]$LocalDir = "$PSScriptRoot\dist",

    [switch]$Archive
)

$ErrorActionPreference = 'Stop'

$zipName = "fyndable-client-$Version.zip"
$localZip = Join-Path $LocalDir $zipName
$localJson = Join-Path $LocalDir 'latest.json'
$localHash = "$localJson.sha256"

if (!(Test-Path $localZip)) {
    throw "Release zip not found: $localZip. Run build-fyndable-client.ps1 first."
}
if (!(Test-Path $localJson)) {
    throw "latest.json not found: $localJson. Run build-fyndable-client.ps1 first."
}

$commonSshArgs = @()
$commonScpArgs = @()
if ($Key) {
    $commonSshArgs = @('-i', $Key)
    $commonScpArgs = @('-i', $Key)
}

$remoteTarget = "$User@${Server}:$RemotePath"

# Ensure remote directory exists
ssh @commonSshArgs "$User@$Server" "mkdir -p $RemotePath/archive"

# Upload the new release and metadata
scp @commonScpArgs $localZip "$remoteTarget/$zipName"
scp @commonScpArgs $localJson "$remoteTarget/latest.json"
scp @commonScpArgs $localHash "$remoteTarget/latest.json.sha256"

# Optional: move older versions to archive/ (leave the one we just uploaded)
if ($Archive) {
    $archiveCmd = "cd $RemotePath && for f in fyndable-client-*.zip; do if [ `"\$f`" != `"$zipName`" ]; then mv `"\$f`" archive/; fi; done"
    ssh @commonSshArgs "$User@$Server" $archiveCmd
}

Write-Host "Deployed v$Version to $Server" -ForegroundColor Green
