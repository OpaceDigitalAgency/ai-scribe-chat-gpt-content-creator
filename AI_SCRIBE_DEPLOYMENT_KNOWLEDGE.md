# AI Scribe Plugin - Deployment Knowledge & Credentials

## Repository Information
- **Plugin Name**: AI Scribe - SEO AI Writer, Content Generator, Humanizer, Blog Writer, SEO Optimizer, DALLE-3, AI WordPress Plugin ChatGPT (GPT-4o 128K)
- **GitHub Repository**: https://github.com/OpaceDigitalAgency/ai-scribe-chat-gpt-content-creator
- **WordPress.org Plugin**: https://wordpress.org/plugins/ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/
- **Main Plugin File**: `article_builder.php`

## Git Credentials & Setup
- **GitHub Username**: OpaceDigitalAgency
- **Default Branch**: `main` (fixed from v1.2.3)
- **Repository Structure**: 
  - `main` branch: Current development/release branch
  - `v1.2.3` branch: Legacy branch (should not be default)
  - Tags: Use semantic versioning (v2.5, v2.5.1, etc.)

## WordPress.org SVN Credentials
- **SVN Repository**: https://plugins.svn.wordpress.org/ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/
- **Username**: `opacewebdesign`
- **SVN Password**: `svn_AL5Ag3LLTH5WSUuASr3Rf899LdpqHUdz295d2357`
- **Main Login Password**: `$tw1cac3*pUl`
- **Profile**: https://profiles.wordpress.org/opacewebdesign

## SVN Commands Reference
```bash
# Navigate to SVN directory
cd "/Users/davidbryan/Dropbox/Opace-Sales-Marketing/Opace plugins and extensions/GPT Plugin/snailsvn"

# Check status
/opt/homebrew/bin/svn status

# Create new version tag
/opt/homebrew/bin/svn mkdir tags/[VERSION]
cp -r trunk/* tags/[VERSION]/
/opt/homebrew/bin/svn add tags/[VERSION]/*

# Commit changes
echo "svn_AL5Ag3LLTH5WSUuASr3Rf899LdpqHUdz295d2357" | /opt/homebrew/bin/svn commit -m "Version [VERSION] release: [DESCRIPTION]" --username opacewebdesign --password-from-stdin
```

## Git Commands Reference
```bash
# Navigate to Git directory
cd "/Users/davidbryan/Dropbox/Opace-Sales-Marketing/Opace plugins and extensions/GPT Plugin/ai-scribe-fixed"

# Standard workflow
git add -A
git commit -m "feat: [DESCRIPTION]"
git push origin main

# Create and push tags
git tag -a v[VERSION] -m "Release version [VERSION] - [DESCRIPTION]"
git push origin v[VERSION]

# Set remote HEAD to main
git remote set-head origin main
```

## WordPress.org Requirements
- **Maximum Tags**: 5 tags only
- **Short Description**: Under 150 characters
- **Stable Tag**: Must match version in main plugin file
- **Tested Up To**: Current WordPress version (6.8.1)

## Current Version Information
- **Current Version**: 2.5
- **Next Version**: 2.5.1 (bug fix for API key save error)
- **WordPress Tested Up To**: 6.8.1

## File Locations
- **Main Plugin File**: `article_builder.php` (contains version header)
- **Readme File**: `readme.txt` (contains stable tag and tested up to)
- **SVN Trunk**: `/trunk/` (current development)
- **SVN Tags**: `/tags/[VERSION]/` (stable releases)

## Known Issues to Fix in v2.5.1
1. **API Key Save Error**: Success message shows as "Error" but actually saves correctly
   - Location: Settings save functionality
   - Issue: Incorrect success/error message display

## Deployment Checklist
1. Update version in `article_builder.php` header
2. Update version in `readme.txt` stable tag
3. Update "Tested up to" in both files
4. Update changelog in `readme.txt`
5. Test locally
6. Commit to Git with proper tag
7. Copy to SVN trunk
8. Create SVN tag
9. Commit to WordPress.org SVN

## WordPress Version Compatibility
- **Requires at least**: 4.4 or higher
- **Tested up to**: 6.8.1 (update as needed)
- **PHP Version**: Compatible with current WordPress requirements
