# CryptPad Integration Analysis for DOCX Editing

## Executive Summary

**YES, CryptPad CAN support your use case**, but with some important considerations and setup requirements.

## Your Requirements

1. ✅ Embed CryptPad editor in your app (gallery)
2. ✅ Load DOCX files directly (binary files)
3. ✅ Edit documents without importing to CryptDrive
4. ✅ Save changes back to your app's storage
5. ✅ Manual save button control

## CryptPad Capabilities

### ✅ What CryptPad CAN Do

1. **Embedding Support**
   - CryptPad has a full Integration API (`cryptpad-api.js`)
   - Can be embedded in iframes
   - Supports `/integration/` endpoint specifically for external integrations

2. **Direct Document Loading**
   - Can load documents from **URL** (`config.document.url`)
   - Can load documents from **Blob** (`config.document.blob`) - directly from binary data
   - **No need to upload to CryptDrive first**

3. **DOCX Support via OnlyOffice**
   - CryptPad uses OnlyOffice for DOCX editing
   - Supports `.docx`, `.xlsx`, `.pptx` formats
   - Converts binary files internally

4. **Save Mechanism**
   - `events.onSave(blob, callback)` receives the edited document as a **Blob**
   - You can save this blob directly to your storage
   - Supports both **autosave** and **manual save**

5. **Session Management**
   - Uses collaborative session keys
   - Multiple users can edit simultaneously
   - Real-time collaboration built-in

## Required Setup

### 1. Enable Embedding in CryptPad Config

```javascript
// config/config.js
enableEmbedding: true,  // Already set in your config
```

### 2. Configure CORS Headers

Your CryptPad instance needs to allow your app's domain:

```javascript
// config/config.js - httpHeaders
'Access-Control-Allow-Origin': '*',  // Or your specific domain
'frame-ancestors': 'self https://your-app-domain.com',
```

### 3. Install OnlyOffice (for DOCX support)

Run: `./install-onlyoffice.sh --accept-license`

### 4. Enable Early Access (for OnlyOffice)

Already done via `customize/application_config.js`:
```javascript
AppConfig.enableEarlyAccess = true;
```

## Integration Flow

### Step 1: Load Document

```javascript
// Option A: Load from URL
const config = {
    document: {
        url: 'https://your-app.com/api/files/document.docx',
        fileType: 'docx',
        key: 'session-key-here'  // For collaboration
    },
    documentType: 'doc',  // 'doc' for DOCX
    events: {
        onSave: handleSave,
        onReady: handleReady
    }
};

// Option B: Load from Blob (BETTER for your use case)
const fileBlob = await fetch('your-file-url').then(r => r.blob());
const config = {
    document: {
        blob: fileBlob,  // Direct binary data
        fileType: 'docx',
        key: 'session-key-here'
    },
    documentType: 'doc',
    events: {
        onSave: handleSave,
        onReady: handleReady
    }
};
```

### Step 2: Initialize Editor

```javascript
// Load CryptPad API
<script src="https://your-cryptpad-instance.com/cryptpad-api.js"></script>

// Initialize
const editor = CryptPadAPI.init(
    'https://your-cryptpad-instance.com',
    'editor-container-id',
    config
);
```

### Step 3: Handle Save

```javascript
function handleSave(blob, callback) {
    // blob is the edited document as binary data
    // Upload to your server
    const formData = new FormData();
    formData.append('file', blob, 'document.docx');
    
    fetch('https://your-app.com/api/files/save', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (response.ok) {
            callback();  // Tell CryptPad save succeeded
        } else {
            callback({error: 'Save failed'});
        }
    });
}
```

### Step 4: Manual Save Button

```javascript
// CryptPad doesn't expose a direct "save" method
// But you can trigger save via:
editor.downloadAs('docx').then(() => {
    // This will trigger onSave callback
});

// OR use the integration's save mechanism
// The editor will call onSave when user clicks save in OnlyOffice
```

## Important Considerations

### ⚠️ Limitations

1. **OnlyOffice Installation Required**
   - OnlyOffice must be installed on CryptPad server
   - Takes ~830MB disk space
   - Requires license acceptance

2. **Session Key Management**
   - You need to generate/store session keys
   - Keys are used for collaborative editing
   - If multiple users edit same document, they need same key

3. **CORS/Embedding Configuration**
   - Your CryptPad instance must allow your app domain
   - CSP headers must permit iframe embedding
   - `enableEmbedding: true` must be set

4. **Save Mechanism**
   - OnlyOffice integration saves via `onSave` callback
   - The callback receives a **Blob** object
   - You must handle uploading this blob to your storage
   - No direct "save to URL" - you handle the upload

5. **File Size Limits**
   - Check CryptPad's `maxUploadSize` setting
   - OnlyOffice has its own limits

### ✅ Advantages

1. **No CryptDrive Dependency**
   - Documents don't need to be in CryptDrive
   - Can work directly with your storage

2. **Real-time Collaboration**
   - Multiple users can edit simultaneously
   - Changes sync in real-time

3. **Full OnlyOffice Features**
   - Rich editing capabilities
   - Formatting, comments, etc.

4. **Binary File Support**
   - Works with DOCX binary files directly
   - No conversion needed on your side

## Recommended Implementation

### Architecture

```
Your App (Gallery)
    ↓
User clicks document
    ↓
Load document binary → Convert to Blob
    ↓
Initialize CryptPad API with blob
    ↓
CryptPad loads OnlyOffice editor
    ↓
User edits document
    ↓
onSave callback receives edited blob
    ↓
Upload blob to your storage
    ↓
Update your app's file reference
```

### Code Example

```javascript
class CryptPadEditor {
    constructor(cryptpadURL, containerId) {
        this.cryptpadURL = cryptpadURL;
        this.containerId = containerId;
        this.editor = null;
        this.currentBlob = null;
    }

    async loadDocument(fileUrl, fileId) {
        // Load file as blob
        const response = await fetch(fileUrl);
        const blob = await response.blob();
        
        // Generate or retrieve session key
        const sessionKey = await this.getSessionKey(fileId);
        
        const config = {
            document: {
                blob: blob,  // Direct binary
                fileType: 'docx',
                key: sessionKey
            },
            documentType: 'doc',
            width: '100%',
            height: '600px',
            events: {
                onSave: (blob, callback) => this.handleSave(blob, fileId, callback),
                onReady: () => console.log('Editor ready'),
                onHasUnsavedChanges: (hasChanges) => {
                    // Update your UI
                    this.updateSaveButton(hasChanges);
                }
            },
            editorConfig: {
                user: {
                    id: this.getUserId(),
                    name: this.getUserName()
                }
            }
        };

        this.editor = CryptPadAPI.init(
            this.cryptpadURL,
            this.containerId,
            config
        );
    }

    async handleSave(blob, fileId, callback) {
        try {
            // Upload to your server
            const formData = new FormData();
            formData.append('file', blob, 'document.docx');
            formData.append('fileId', fileId);

            const response = await fetch('/api/files/save', {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                this.currentBlob = blob;
                callback();  // Success
            } else {
                callback({error: 'Save failed'});
            }
        } catch (error) {
            callback({error: error.message});
        }
    }

    async getSessionKey(fileId) {
        // Retrieve or generate session key for this file
        // Store in your database/cache
        let key = await this.retrieveSessionKey(fileId);
        if (!key) {
            key = this.generateSessionKey();
            await this.storeSessionKey(fileId, key);
        }
        return key;
    }

    manualSave() {
        // Trigger save - OnlyOffice will call onSave
        // Note: OnlyOffice has its own save button
        // You might need to trigger it programmatically
        // or rely on OnlyOffice's built-in save
    }
}
```

## Testing Checklist

- [ ] CryptPad instance has embedding enabled
- [ ] OnlyOffice is installed and working
- [ ] CORS headers allow your app domain
- [ ] Can load DOCX file as blob
- [ ] Editor loads successfully
- [ ] Can edit document
- [ ] onSave callback receives blob
- [ ] Can upload blob to your storage
- [ ] File updates correctly in your app

## Conclusion

**CryptPad CAN support your use case** with the following:

✅ **Works:**
- Direct binary file loading (no CryptDrive needed)
- DOCX editing via OnlyOffice
- Save callback with blob
- Embedding in your app

⚠️ **Requires:**
- OnlyOffice installation
- Proper CORS/embedding configuration
- Session key management
- Blob upload handling in your app

**Recommendation:** This is a viable solution. The main work is:
1. Setting up CryptPad with OnlyOffice
2. Implementing the integration API in your app
3. Handling the save callback to upload blobs to your storage

The integration API is well-designed for this exact use case!

