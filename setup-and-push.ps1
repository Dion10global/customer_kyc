# ============================================================================
# setup-and-push.ps1
#
# One-shot setup: initialise git locally under BenNyaruz's identity,
# make the initial commit, create the GitHub repo, and push.
#
# USAGE (from this folder, in PowerShell):
#   .\setup-and-push.ps1
#
# Defaults are baked in for BenNyaruz <bennyaruviro@gmail.com>. Override
# with -GitHubUser / -RepoName / -Visibility if you ever reuse this script.
# ============================================================================
param(
    [string]$GitHubUser = "BenNyaruz",
    [string]$RepoName   = "customers-app",
    [string]$Visibility = "public",
    [string]$UserName   = "Benjamin Kudzai Nyaruviro",
    [string]$UserEmail  = "bennyaruviro@gmail.com"
)

$ErrorActionPreference = "Stop"
Write-Host "==> Working directory: $(Get-Location)" -ForegroundColor Cyan

if (Test-Path ".git") {
    Write-Host "==> Removing stale .git directory" -ForegroundColor Yellow
    Remove-Item -Recurse -Force ".git"
}

Write-Host "==> git init" -ForegroundColor Cyan
git init -b main | Out-Null

Write-Host "==> Configuring local git identity: $UserName <$UserEmail>" -ForegroundColor Cyan
git config user.name  $UserName
git config user.email $UserEmail
git config commit.gpgsign false

Write-Host "==> Staging files" -ForegroundColor Cyan
git add .

Write-Host "==> Initial commit" -ForegroundColor Cyan
git commit -m "Initial commit: customers.php + Vercel deploy config + CI/CD pipeline"

$ghAvailable = $false
try { gh --version | Out-Null; $ghAvailable = $true } catch { $ghAvailable = $false }

if ($ghAvailable) {
    Write-Host "==> Creating GitHub repository '$GitHubUser/$RepoName' ($Visibility)" -ForegroundColor Cyan
    gh repo create "$GitHubUser/$RepoName" --$Visibility --source=. --remote=origin --push
    Write-Host ""
    Write-Host "DONE." -ForegroundColor Green
    Write-Host "Repository URL: https://github.com/$GitHubUser/$RepoName" -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "GitHub CLI ('gh') not found." -ForegroundColor Yellow
    Write-Host "Create the repo manually at https://github.com/new, then run:" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "  git remote add origin https://github.com/$GitHubUser/$RepoName.git" -ForegroundColor White
    Write-Host "  git push -u origin main" -ForegroundColor White
}
