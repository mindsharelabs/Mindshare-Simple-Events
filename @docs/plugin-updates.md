# Plugin Updates and Version Management

## Overview

This document outlines the proper procedures for managing plugin versions and updates for the Mindshare Simple Events plugin.

## Version Management

### Version Consistency Requirements

The plugin uses two version declarations that **must always match**:

1. **Plugin Header Version** (line 6 in `mindshare-events.php`):

   ```php
   * Version: 1.3.4
   ```

2. **Internal Plugin Version** (line 25 in `mindshare-events.php`):
   ```php
   $this->define( 'MINDEVENTS_PLUGIN_VERSION', '1.3.4');
   ```

### Common Update Issues

#### "Destination folder already exists" Error

**Problem:** WordPress shows this error instead of the update dialog when:

- Plugin header version doesn't match internal version
- WordPress doesn't recognize the upload as an update to existing plugin
- Version number is lower than or equal to currently installed version

**Solution:**

1. Ensure both version numbers in `mindshare-events.php` match
2. Increment version number higher than currently installed version
3. Test update process on staging environment first

## Update Process

### Before Releasing Updates

1. **Version Increment:**

   - Update both version declarations in `mindshare-events.php`
   - Use semantic versioning (MAJOR.MINOR.PATCH)
   - Ensure new version is higher than previous

2. **Testing:**

   - Test on staging environment
   - Verify update process works correctly
   - Check all functionality after update

3. **Documentation:**
   - Update changelog
   - Document any breaking changes
   - Update installation instructions if needed

### Version Numbering Guidelines

- **Major (X.0.0):** Breaking changes, major feature additions
- **Minor (1.X.0):** New features, backwards compatible
- **Patch (1.1.X):** Bug fixes, minor improvements

### Current Version: 1.3.4

## Installation Methods

### Fresh Installation

- Upload plugin zip file
- WordPress will extract to `/wp-content/plugins/Mindshare-Simple-Events/`
- Activate plugin

### Update Installation

- Deactivate current plugin (optional but recommended)
- Upload new version zip file
- WordPress should recognize as update and replace files
- Reactivate plugin

### Manual Update (Required for Version Conflicts)

When WordPress shows "Destination folder already exists" error, you must use the manual update process:

#### Method 1: Via SSH/FTP (Recommended)

1. **Backup current plugin:**

   ```bash
   # Connect via SSH: ssh master_pkxheyszcy@143.198.76.17
   cd /home/1151261.cloudwaysapps.com/rzknzaagjv/public_html/wp-content/plugins/
   cp -r Mindshare-Simple-Events/ Mindshare-Simple-Events-backup/
   ```

2. **Deactivate plugin in WordPress admin**

3. **Remove old plugin folder:**

   ```bash
   rm -rf Mindshare-Simple-Events/
   ```

4. **Upload new plugin via WordPress admin or FTP**

5. **Activate plugin**

#### Method 2: Via WordPress Admin (Alternative)

1. **Deactivate plugin** in WordPress admin
2. **Delete plugin** from WordPress admin (Plugins > Installed Plugins > Delete)
3. **Upload new plugin** as fresh installation
4. **Activate plugin**

#### Method 3: Direct File Replacement (Advanced)

1. **Backup plugin folder via FTP**
2. **Keep plugin activated**
3. **Replace individual files** via FTP, maintaining folder structure
4. **Clear any caches**

## Troubleshooting Updates

### If Update Dialog Doesn't Appear

1. **Check Version Numbers:**

   - Verify header version matches internal version
   - Ensure new version is higher than installed version

2. **Clear Caches:**

   - Clear WordPress object cache
   - Clear any caching plugins
   - Clear browser cache

3. **Manual Installation:**
   - Use manual update process described above
   - Contact hosting provider if file permission issues occur

### File Permission Issues

If you encounter permission errors:

- Ensure WordPress has write permissions to plugins directory
- Check with hosting provider about file ownership
- May need to upload via FTP instead of WordPress admin

## Best Practices

1. **Always test updates on staging first**
2. **Keep version numbers synchronized**
3. **Backup before updating**
4. **Document all changes**
5. **Use semantic versioning**
6. **Test all functionality after updates**

## Support

For update-related issues:

- Check this documentation first
- Verify version number consistency
- Test on staging environment
- Contact Mindshare Labs support if issues persist
