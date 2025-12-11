# elFinder CryptPad Integration Guide

## Overview

This guide shows how to add an "Edit with CryptPad" option to elFinder's context menu for Office documents (DOCX, XLSX, PPTX, etc.).

## Method 1: Custom elFinder Command (Recommended)

### Step 1: Add the Integration Script

Include the `elfinder-cryptpad-integration.js` script **after** elFinder is loaded in your TikiWiki page:

```php
// In your TikiWiki template or PHP file where elFinder is initialized
$headerlib->add_jsfile('path/to/elfinder-cryptpad-integration.js');
```

### Step 2: Configure elFinder Options

When initializing elFinder, add the custom command to the context menu:

```javascript
$('#elfinder').elfinder({
    // ... your existing elFinder options ...
    contextmenu: {
        files: [
            'getfile', '|',
            'open', 'quicklook', '|',
            'download', '|',
            'editWithCryptPad', '|',  // Add this line
            'rename', 'duplicate', '|',
            'cut', 'copy', 'paste', '|',
            'rm', '|',
            'info'
        ]
    },
    // Register the custom command
    commands: {
        editWithCryptPad: {
            // Command will be registered by elfinder-cryptpad-integration.js
        }
    }
});
```

## Method 2: Simple Context Menu Addition (Simpler)

If Method 1 doesn't work with your TikiWiki setup, you can add a simpler context menu item:

```javascript
// Add this after elFinder is initialized
$(document).on('contextmenu', '.elfinder-cwd-file', function(e) {
    var $file = $(this);
    var fileData = $file.data('file');
    
    if (!fileData) return;
    
    // Check if it's a supported Office document
    var ext = fileData.name.split('.').pop().toLowerCase();
    var supported = ['docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp'];
    
    if (supported.indexOf(ext) === -1) return;
    
    // Add custom menu item (you'll need to extend elFinder's context menu)
    // This is a simplified example - actual implementation depends on elFinder version
});
```

## Method 3: TikiWiki-Specific Integration

If TikiWiki has a specific way to extend elFinder, you might need to:

1. **Find where elFinder is initialized in TikiWiki**
   - Look for files like `tiki-file_galleries.php` or elFinder-related templates
   - Search for `$('#elfinder').elfinder(` or similar

2. **Add the integration script**
   ```php
   // In the PHP file that loads elFinder
   $headerlib->add_jsfile('templates/your-template/elfinder-cryptpad-integration.js');
   ```

3. **Extract fileId from elFinder file data**
   - TikiWiki might store fileId in `file.data.fileId` or `file.hash`
   - You may need to adjust the extraction logic in the integration script

## Method 4: Direct URL Approach (Easiest)

If elFinder shows file URLs, you can create a custom context menu that opens the CryptPad editor directly:

```javascript
// After elFinder initialization
var elfinderInstance = $('#elfinder').elfinder('instance');

// Add custom context menu handler
elfinderInstance.bind('contextmenu', function(e) {
    var files = e.data.files || [];
    if (files.length === 0) return;
    
    var file = files[0];
    var ext = file.name.split('.').pop().toLowerCase();
    var supported = ['docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp'];
    
    if (supported.indexOf(ext) === -1) return;
    
    // Extract fileId from file URL or hash
    // This depends on how TikiWiki structures elFinder file data
    var fileId = extractFileId(file); // You need to implement this
    
    if (fileId) {
        var editUrl = 'tiki-edit_cryptpad.php?fileId=' + fileId + '&edit=1';
        window.open(editUrl, '_blank');
    }
});
```

## Troubleshooting

### Issue: Command not appearing in context menu

**Solution:**
- Make sure the script loads after elFinder
- Check browser console for errors
- Verify elFinder version compatibility
- Try adding the command manually in elFinder options

### Issue: Can't extract fileId

**Solution:**
- Check elFinder file object structure in browser console: `console.log(file)`
- Look for fileId in `file.data`, `file.hash`, or `file.url`
- Adjust the extraction logic in the integration script

### Issue: Command appears but doesn't work

**Solution:**
- Check that `tiki-edit_cryptpad.php` is accessible
- Verify fileId is being passed correctly
- Check browser console for JavaScript errors
- Ensure CryptPad is configured and OnlyOffice is installed

## Testing

1. Open elFinder in TikiWiki
2. Right-click on a DOCX, XLSX, or PPTX file
3. Look for "Edit with CryptPad" in the context menu
4. Click it - should open the file in CryptPad editor in a new tab

## Alternative: Browser Extension/Bookmarklet

If elFinder integration is too complex, you could create a bookmarklet:

```javascript
javascript:(function(){
    var fileId = prompt('Enter File ID:');
    if(fileId) {
        window.open('tiki-edit_cryptpad.php?fileId=' + fileId + '&edit=1', '_blank');
    }
})();
```

## Notes

- The integration script needs to be adjusted based on:
  - Your TikiWiki version
  - elFinder version
  - How TikiWiki stores file metadata in elFinder
  - Your specific file gallery setup

- You may need to modify `elfinder-cryptpad-integration.js` to match your TikiWiki's elFinder integration structure.

