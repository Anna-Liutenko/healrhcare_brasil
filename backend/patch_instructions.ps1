<#
PowerShell helper: create a branch, commit current changes, push and create a PR (if gh CLI available).
Run from repository root.
#>

param(
    [string]$BranchName = 'feature/publish-render-html',
    [string]$CommitMessage = "Stage 2: Add RenderPageHtml; update PublishPage/UpdatePage; add tests"
)

Write-Host "This script will create branch '$BranchName', commit current changes, push to origin and try to create a PR (if 'gh' is installed)."

$confirm = Read-Host "Proceed with git operations? (y/N)"
if ($confirm -ne 'y') { Write-Host 'Aborted by user.'; exit 0 }

# Create branch
git checkout -b $BranchName

# Stage all changes
git add -A

# Commit
git commit -m $CommitMessage

# Push branch
git push -u origin $BranchName

# Try to create PR using gh CLI if available
if (Get-Command gh -ErrorAction SilentlyContinue) {
    gh pr create --fill
} else {
    Write-Host "'gh' CLI not found. Open a pull request via your repository UI (GitHub/GitLab) for branch: $BranchName"
}
