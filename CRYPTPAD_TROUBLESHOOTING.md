# CryptPad Integration Troubleshooting Guide

## Problem: Loading Screen Stuck, Message Channel Errors

### Symptoms
- CryptPad logo and loading text shows indefinitely
- Console errors: "A listener indicated an asynchronous response by returning true, but the message channel closed before a response was received"
- "INIT" message appears in console
- URL shows: `http://localhost:3000/integration/?importUrl=...&mode=edit&documentType=doc`

### Root Cause

You're **manually constructing the integration URL** with query parameters, but CryptPad's integration endpoint doesn't work that way. The integration endpoint (`/integration/`) should be loaded **without query parameters**, and all communication happens via **postMessage** between the iframe and parent window.

### The Problem

❌ **WRONG** - What you're doing:
```javascript
// Don't do this!
const iframe = document.createElement('iframe');
iframe.src = 'http://localhost:3000/integration/?importUrl=...&mode=edit&documentType=doc';
```

✅ **CORRECT** - What you should do:
```javascript
// Use the CryptPad API
CryptPadAPI.init(
    'http://localhost:3000',
    'editor-container',
    {
        document: {
            url: 'http://your-server.com/file.docx',  // OR blob: fileBlob
            fileType: 'docx',
            key: 'session-key-here'
        },
        documentType: 'doc',
        events: {
            onSave: handleSave,
            onReady: handleReady
        }
    }
);
```

## Solution: Proper Integration Setup

### Step 1: Load the CryptPad API Script

```html
<script src="http://localhost:3000/cryptpad-api.js"></script>
```

### Step 2: Create Container Element

```html
<div id="cryptpad-editor-container"></div>
```

### Step 3: Initialize with Proper Configuration

```javascript
// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    const cryptpadURL = 'http://localhost:3000';
    const containerId = 'cryptpad-editor-container';
    
    // Option A: Load document from URL
    const config = {
        document: {
            url: 'http://localhost:8081/tiki-master/tiki/tiki-cryptpad-import.php?data=...',
            fileType: 'docx',
            key: 'your-session-key'  // Generate or retrieve this
        },
        documentType: 'doc',  // 'doc' for DOCX files
        width: '100%',
        height: '600px',
        events: {
            onSave: function(blob, callback) {
                // Handle save
                console.log('Document saved, blob size:', blob.size);
                // Upload blob to your server
                uploadBlobToServer(blob).then(() => {
                    callback();  // Success
                }).catch((err) => {
                    callback({error: err.message});  // Error
                });
            },
            onReady: function() {
                console.log('Editor is ready');
            },
            onDocumentReady: function() {
                console.log('Document loaded');
            },
            onHasUnsavedChanges: function(hasChanges) {
                console.log('Unsaved changes:', hasChanges);
            }
        },
        editorConfig: {
            user: {
                id: 'user-id',
                name: 'User Name'
            }
        }
    };
    
    // Initialize the editor
    const editor = CryptPadAPI.init(cryptpadURL, containerId, config);
    
    // Handle promise
    editor.then(() => {
        console.log('Editor initialized successfully');
    }).catch((error) => {
        console.error('Failed to initialize editor:', error);
    });
});
```

### Step 4: Handle Document Loading

If your import URL requires authentication or special handling, you have two options:

#### Option A: Use a Proxy Endpoint

Create an endpoint on your server that:
1. Validates the request
2. Fetches the document
3. Returns it with proper CORS headers

```php
// tiki-cryptpad-import.php
<?php
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');

// Validate and get file
$data = $_GET['data'];
// ... your validation logic ...

// Return file
readfile($filePath);
exit;
```

#### Option B: Load Document as Blob First

```javascript
// Fetch document first, then pass as blob
async function loadDocument() {
    try {
        const response = await fetch('http://localhost:8081/tiki-master/tiki/tiki-cryptpad-import.php?data=...', {
            credentials: 'include'  // Include cookies if needed
        });
        
        if (!response.ok) {
            throw new Error('Failed to load document');
        }
        
        const blob = await response.blob();
        
        // Now initialize with blob
        const config = {
            document: {
                blob: blob,  // Pass blob directly
                fileType: 'docx',
                key: 'your-session-key'
            },
            documentType: 'doc',
            events: {
                onSave: handleSave,
                onReady: handleReady
            }
        };
        
        const editor = CryptPadAPI.init('http://localhost:3000', 'editor-container', config);
    } catch (error) {
        console.error('Error loading document:', error);
    }
}
```

## Common Issues and Fixes

### Issue 1: Message Channel Errors

**Cause:** The parent window and iframe aren't communicating properly.

**Fix:**
- Make sure you're using `CryptPadAPI.init()` and not manually creating iframes
- Ensure the CryptPad API script is loaded before initialization
- Check that `enableEmbedding: true` is set in CryptPad config

### Issue 2: CORS Errors

**Cause:** Your document URL doesn't allow requests from CryptPad's origin.

**Fix:**
```php
// Add CORS headers to your import endpoint
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
```

### Issue 3: Loading Screen Stuck

**Cause:** The `START` command isn't being sent, or document loading fails.

**Fix:**
- Check browser console for errors
- Verify your document URL is accessible
- Make sure OnlyOffice is installed (`www/common/onlyoffice/dist` exists)
- Check that `enableEarlyAccess = true` is set

### Issue 4: Session Key Issues

**Cause:** Invalid or missing session key.

**Fix:**
```javascript
// Generate a session key (store it for this document)
function getSessionKey(fileId) {
    // Retrieve from localStorage or generate new
    let key = localStorage.getItem('cryptpad-key-' + fileId);
    if (!key) {
        // Generate a random key (CryptPad will handle it)
        key = null;  // Let CryptPad generate it
    }
    return key;
}
```

## Complete Working Example

```html
<!DOCTYPE html>
<html>
<head>
    <title>CryptPad Integration</title>
    <script src="http://localhost:3000/cryptpad-api.js"></script>
</head>
<body>
    <div id="cryptpad-editor" style="width: 100%; height: 600px;"></div>
    
    <script>
        async function initEditor() {
            const cryptpadURL = 'http://localhost:3000';
            const fileId = 3;
            
            // Option 1: Load from URL
            const documentUrl = 'http://localhost:8081/tiki-master/tiki/tiki-cryptpad-import.php?data=eyJkYXRhIjp7ImZpbGVJZCI6MywiZXhwIjoxNzY1MDEyNjk1fSwiaGFzaCI6ImI2YmYwNzYwNTllMGQyN2U2MDY4YzhhZDJkNGIwMWE4NTBjYWQ3MjUifQ%3D%3D';
            
            const config = {
                document: {
                    url: documentUrl,
                    fileType: 'docx',
                    key: null  // Let CryptPad generate
                },
                documentType: 'doc',
                width: '100%',
                height: '600px',
                events: {
                    onSave: function(blob, callback) {
                        // Upload to your server
                        const formData = new FormData();
                        formData.append('file', blob, 'document.docx');
                        formData.append('fileId', fileId);
                        
                        fetch('/api/files/save', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (response.ok) {
                                callback();  // Success
                            } else {
                                callback({error: 'Save failed'});
                            }
                        })
                        .catch(error => {
                            callback({error: error.message});
                        });
                    },
                    onReady: function() {
                        console.log('Editor ready');
                    },
                    onDocumentReady: function() {
                        console.log('Document loaded');
                    },
                    onHasUnsavedChanges: function(hasChanges) {
                        console.log('Has unsaved changes:', hasChanges);
                    }
                },
                editorConfig: {
                    user: {
                        id: 'user-123',
                        name: 'User Name'
                    }
                }
            };
            
            try {
                const editor = await CryptPadAPI.init(cryptpadURL, 'cryptpad-editor', config);
                console.log('Editor initialized');
            } catch (error) {
                console.error('Failed to initialize editor:', error);
            }
        }
        
        // Initialize when page loads
        window.addEventListener('DOMContentLoaded', initEditor);
    </script>
</body>
</html>
```

## Debugging Steps

1. **Check Browser Console**
   - Look for errors
   - Check network tab for failed requests
   - Verify messages are being sent/received

2. **Verify CryptPad Configuration**
   ```javascript
   // In browser console on CryptPad instance
   console.log(ApiConfig.enableEmbedding);  // Should be true
   ```

3. **Test Integration Endpoint**
   - Open: `http://localhost:3000/integration/` directly
   - Should show loading screen, then error (expected - needs parent communication)

4. **Check OnlyOffice Installation**
   - Verify: `http://localhost:3000/www/common/onlyoffice/dist/` exists
   - Should contain version folders (v8, etc.)

5. **Test Document URL**
   ```javascript
   // In browser console
   fetch('http://localhost:8081/tiki-master/tiki/tiki-cryptpad-import.php?data=...')
       .then(r => r.blob())
       .then(blob => console.log('Document loaded:', blob.size))
       .catch(err => console.error('Error:', err));
   ```

## Key Takeaways

1. ✅ **Use CryptPadAPI.init()** - Don't manually create iframes
2. ✅ **No query parameters** - Integration endpoint doesn't accept URL params
3. ✅ **Use postMessage** - All communication is via messages
4. ✅ **Handle CORS** - Your document endpoint needs proper headers
5. ✅ **Check OnlyOffice** - Must be installed for DOCX support

