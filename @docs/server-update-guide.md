# Server-Specific Update Guide

## Server Details

- **Host:** 143.198.76.17:22
- **Username:** master_pkxheyszcy
- **Plugin Path:** `/home/1151261.cloudwaysapps.com/rzknzaagjv/public_html/wp-content/plugins/Mindshare-Simple-Events/`

## Current Issue

WordPress is showing "Destination folder already exists" error instead of allowing plugin update. This happens when WordPress doesn't recognize the upload as an update to the existing plugin.

## Immediate Solution Steps

### Option 1: SSH Method (Fastest)

1. **Connect to server:**

   ```bash
   ssh master_pkxheyszcy@143.198.76.17
   ```

2. **Navigate to plugins directory:**

   ```bash
   cd /home/1151261.cloudwaysapps.com/rzknzaagjv/public_html/wp-content/plugins/
   ```

3. **Backup current plugin:**

   ```bash
   cp -r Mindshare-Simple-Events/ Mindshare-Simple-Events-backup-$(date +%Y%m%d)
   ```

4. **Remove current plugin folder:**

   ```bash
   rm -rf Mindshare-Simple-Events/
   ```

5. **Now upload your new plugin zip via WordPress admin** - it should install successfully since the folder no longer exists.

6. **Activate the plugin** in WordPress admin.

### Option 2: WordPress Admin Method

1. **Go to WordPress Admin > Plugins**
2. **Deactivate** "Mindshare Simple Events"
3. **Delete** the plugin (this removes the folder)
4. **Upload** your new plugin zip file
5. **Activate** the plugin

### Option 3: FTP File Replacement

If you want to keep the plugin active during update:

1. **Connect via FTP/SFTP** to your server
2. **Navigate to:** `/home/1151261.cloudwaysapps.com/rzknzaagjv/public_html/wp-content/plugins/Mindshare-Simple-Events/`
3. **Backup the folder** by downloading it
4. **Replace the main plugin file:** Upload your updated `mindshare-events.php`
5. **Replace any other changed files** as needed
6. **Clear any caches** (WordPress cache, server cache, etc.)

## Why This Happens

The "Destination folder already exists" error occurs because:

1. WordPress doesn't recognize this as an update to existing plugin
2. The plugin folder name or structure doesn't match exactly
3. WordPress is treating it as a new installation that conflicts with existing folder

## Prevention for Future Updates

1. **Always increment version numbers** properly
2. **Test updates on staging first**
3. **Use consistent folder naming**
4. **Consider using WordPress update server** for automatic updates

## Verification Steps

After successful update:

1. **Check plugin is active** in WordPress admin
2. **Verify version number** shows 1.3.4
3. **Test plugin functionality**
4. **Check for any PHP errors** in error logs
5. **Clear all caches**

## Rollback Plan

If something goes wrong:

1. **Deactivate the new plugin**
2. **Delete the new plugin folder via SSH:**
   ```bash
   rm -rf Mindshare-Simple-Events/
   ```
3. **Restore backup:**
   ```bash
   cp -r Mindshare-Simple-Events-backup-YYYYMMDD/ Mindshare-Simple-Events/
   ```
4. **Reactivate plugin**

## Contact Information

If you encounter issues:

- Check error logs: `/home/1151261.cloudwaysapps.com/rzknzaagjv/public_html/wp-content/debug.log`
- Contact hosting support if file permission issues occur
- Ensure WordPress has proper write permissions to plugins directory
