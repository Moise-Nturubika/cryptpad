<?php

/**
 * @package tikiwiki
 */

// (c) Copyright by authors of the Tiki Wiki CMS Groupware Project
//
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.
use Tiki\Package\VendorHelper;

$section = 'cryptpad_docs';
$inputConfiguration = [
    [
        'staticKeyFilters'                => [
            'fileId'                      => 'int',           //post
            'galleryId'                   => 'int',           //post
            'name'                        => 'string',        //post
            'data'                        => 'none',          //post
            'description'                 => 'xss',           //post
            'edit'                        => 'bool',          //post
            'format'                      => 'string',        //post
        ],
    ],
];
require_once('tiki-setup.php');
$filegallib = TikiLib::lib('filegal');
include_once('lib/mime/mimetypes.php');
global $mimetypes;

$auto_query_args = [
    'fileId',
    'edit',
    'format'
];

$access->check_feature('feature_cryptpad_docs');
$access->check_feature('feature_file_galleries');

$fileId = (int)$_REQUEST['fileId'];
$smarty->assign('fileId', $fileId);

if ($fileId > 0) {
    $fileInfo = $filegallib->get_file_info($fileId);
} else {
    $fileInfo = [];
}

//This allows the document to be edited, but only the most recent of that group if it is an archive
if (! empty($fileInfo['archiveId']) && $fileInfo['archiveId'] > 0) {
    $fileId = $fileInfo['archiveId'];
    $fileInfo = $filegallib->get_file_info($fileId);
}

$cat_type = 'file';
$cat_objid = (int) $fileId;
$cat_object_exists = ! empty($fileInfo);
include_once('categorize_list.php');
include_once('tiki-section_options.php');

$gal_info = $filegallib->get_file_gallery($_REQUEST['galleryId']);

$fileTypeParts = explode(';', $fileInfo['filetype']);
$fileType = reset($fileTypeParts);

$extensionParts = explode('.', $fileInfo['filename']);
$extension = end($extensionParts);

// Support Microsoft Office formats and OpenDocument formats
$supportedExtensions = ['docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp'];
$supportedTypes = array_map(
    function ($type) use ($mimetypes) {
        return $mimetypes[$type];
    },
    $supportedExtensions
);

if (! in_array($extension, $supportedExtensions) && ! in_array($fileType, $supportedTypes)) {
    Feedback::errorAndDie(tr('Wrong file type, expected one of %0', implode(', ', $supportedTypes)), 500);
}

$globalperms = Perms::get([ 'type' => 'file', 'object' => $fileInfo['fileId'] ]);

//check permissions
if (! ($globalperms->admin_file_galleries == 'y' || $globalperms->view_file_gallery == 'y')) {
    Feedback::errorAndDie(tra('You do not have permission to view/edit this file'), 401);
}

if (! empty($_REQUEST['name']) || ! empty($fileInfo['name'])) {
    $_REQUEST['name'] = (! empty($_REQUEST['name']) ? $_REQUEST['name'] : $fileInfo['name']);
} else {
    $_REQUEST['name'] = 'New Document';
}

// Remove file extension from name for display
$nameWithoutExt = preg_replace('/\.(docx|xlsx|pptx|odt|ods|odp)$/', '', $_REQUEST['name']);
$_REQUEST['name'] = htmlspecialchars($nameWithoutExt);

// Determine document type for CryptPad API
$documentType = 'doc'; // default for CryptPad API (doc, sheet, presentation)
if (in_array($extension, ['xlsx', 'ods'])) {
    $documentType = 'sheet';
} elseif (in_array($extension, ['pptx', 'odp'])) {
    $documentType = 'presentation';  // Fixed: use 'presentation' not 'slide'
} elseif (in_array($extension, ['docx', 'odt'])) {
    $documentType = 'doc';
}

$smarty->assign('documentType', $documentType);
$smarty->assign('fileExtension', $extension);

//Upload to file gallery
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_REQUEST['data'])) {
    $_REQUEST['galleryId'] = (int)$_REQUEST['galleryId'];
    $_REQUEST['description'] = htmlspecialchars(isset($_REQUEST['description']) ? $_REQUEST['description'] : $_REQUEST['name']);
    $format = $_REQUEST['format'] ?? $extension;

    // CryptPad sends data as base64 encoded
    $_REQUEST['data'] = base64_decode($_REQUEST['data']);

    // Determine MIME type based on format
    $mimeType = $mimetypes[$format] ?? 'application/octet-stream';

    $file = Tiki\FileGallery\File::id($fileId);
    if (! $file->exists()) {
        $file->init([
            'galleryId' => $_REQUEST['galleryId'],
            'description' => $_REQUEST['description'],
            'user' => $user
        ]);
    }
    $file->replace($_REQUEST['data'], $mimeType, $_REQUEST['name'], $_REQUEST['name'] . '.' . $format);
    echo $fileId;
    die;
}

$smarty->assign('page', $page);
$smarty->assign('isFromPage', isset($page));
$smarty->assign('fileId', $fileId);

// Determine if configured
$cryptpadBaseUrl = trim($prefs['cryptpad_base_url'] ?? '');
$cryptpadAvailable = !empty($cryptpadBaseUrl);
$smarty->assign('cryptpadAvailable', $cryptpadAvailable);
$smarty->assign('cryptpadBaseUrl', $cryptpadBaseUrl);

// Optional: direct pad URL to embed (e.g., https://your-cryptpad/#/2/edit/<id>/)
$padUrl = isset($_REQUEST['pad']) ? (string)$_REQUEST['pad'] : '';
$smarty->assign('padUrl', $padUrl);

// Build short-lived import URL to fetch file without cookies
// This will be used by CryptPad Integration API to load the file directly
$importUrl = '';
if ($cryptpadAvailable && $fileId > 0) {
    $expires = time() + 7200; // 2 hours
    $payload = [
        'fileId' => (int) $fileId,
        'exp' => $expires,
    ];
    $encoded = Tiki_Security::get()->encode($payload);
    global $base_url;
    $importUrl = $base_url . 'tiki-cryptpad-import.php?data=' . rawurlencode($encoded);
}
$smarty->assign('cryptpadImportUrl', $importUrl);
$smarty->assign('fileName', $fileInfo['name'] ?? 'document');

if ($cryptpadAvailable) {
    // Load CryptPad Integration API script
    $cryptpadApiUrl = rtrim($cryptpadBaseUrl, '/') . '/cryptpad-api.js';
    $headerlib->add_jsfile($cryptpadApiUrl);
    
    // Add error handler for script loading
    $cryptpadApiUrlEscaped = addslashes($cryptpadApiUrl);
    $headerlib->add_jq_onready("
// Check if CryptPad API script loaded
var apiScript = document.querySelector('script[src*=\"cryptpad-api.js\"]');
if (apiScript) {
    apiScript.onerror = function() {
        console.error('Failed to load CryptPad API script from: " . $cryptpadApiUrlEscaped . "');
        console.log('Make sure cryptpad-api.js exists at: " . $cryptpadApiUrlEscaped . "');
    };
}
");
    
    if (isset($_REQUEST['edit'])) {
        $savingText = json_encode(tr('Saving...'));
        $smarty->assign('edit', 'true');

        // Use CryptPad API for proper integration
        $headerlib->add_jq_onready("
(function() {
    var fileId = " . json_encode($fileId) . ";
    var importUrl = " . json_encode($importUrl) . ";
    var documentType = " . json_encode($documentType) . ";
    var fileExtension = " . json_encode($extension) . ";
    var cryptpadBaseUrl = " . json_encode(rtrim($cryptpadBaseUrl, '/')) . ";
    var fileName = " . json_encode($fileInfo['name'] ?? 'document') . ";
    var galleryId = " . json_encode($fileInfo['galleryId'] ?? $_REQUEST['galleryId'] ?? 0) . ";
    
    // Generate or retrieve session key for collaboration
    function getSessionKey(fileId) {
        var key = localStorage.getItem('cryptpad_key_' + fileId);
        if (!key) {
            // Let CryptPad generate the key by passing null
            key = null;
        }
        return key;
    }
    
    // Wait for CryptPad API to be available (with timeout)
    var apiWaitStart = Date.now();
    var apiWaitTimeout = 10000; // 10 seconds timeout
    function initCryptPadEditor() {
        // Check if CryptPadAPI exists and is a function
        if (typeof CryptPadAPI === 'undefined' || typeof CryptPadAPI !== 'function') {
            var elapsed = Date.now() - apiWaitStart;
            if (elapsed > apiWaitTimeout) {
                console.error('CryptPad API failed to load after ' + (apiWaitTimeout/1000) + ' seconds');
                console.error('Please verify that cryptpad-api.js exists at: ' + cryptpadBaseUrl + '/cryptpad-api.js');
                console.error('CryptPadAPI value:', typeof CryptPadAPI !== 'undefined' ? CryptPadAPI : 'undefined');
                console.error('Available globals:', Object.keys(window).filter(k => k.toLowerCase().includes('crypt') || k.toLowerCase().includes('api')));
                alert('CryptPad API failed to load. Please check: 1. cryptpad-api.js exists at: ' + cryptpadBaseUrl + '/cryptpad-api.js 2. CryptPad instance is accessible 3. Check browser console for errors');
                fallbackToIframe();
                return;
            }
            console.log('Waiting for CryptPad API... (' + Math.round(elapsed/1000) + 's)');
            setTimeout(initCryptPadEditor, 100);
            return;
        }
        
        console.log('CryptPad API loaded, initializing editor...');
        console.log('CryptPadAPI type:', typeof CryptPadAPI);
        console.log('Import URL:', importUrl);
        console.log('Document type:', documentType);
        console.log('File extension:', fileExtension);
        
        var sessionKey = getSessionKey(fileId);
        
        // Configure CryptPad editor
        var config = {
            document: {
                url: importUrl,
                fileType: fileExtension,
                key: sessionKey
            },
            documentType: documentType,
            width: '100%',
            height: '800px',
            events: {
                onSave: function(blob, callback) {
                    console.log('Save triggered, blob size:', blob.size);
                    
                    // Convert blob to base64 for upload
                    var reader = new FileReader();
                    reader.onloadend = function() {
                        var base64 = reader.result.split(',')[1];
                        if (!base64) {
                            callback({error: 'Failed to convert file to base64'});
                            alert('Failed to prepare file for upload');
                            return;
                        }
                        
                        // Show saving indicator (adjust based on your Tiki modal system)
                        var savingMsg = " . json_encode(tr('Saving...'), JSON_HEX_APOS | JSON_HEX_QUOT) . ";
                        if (typeof $.tikiModal === 'function') {
                            $.tikiModal(savingMsg);
                        } else {
                            console.log(savingMsg);
                        }
                        
                        $.post('tiki-edit_cryptpad.php', {
                            fileId: fileId,
                            data: base64,
                            name: fileName.replace(/\\.[^.]+$/, ''),
                            format: fileExtension,
                            galleryId: galleryId
                        })
                        .done(function(response) {
                            if (typeof $.tikiModal === 'function') {
                                $.tikiModal();
                            }
                            callback(); // Tell CryptPad save succeeded
                            console.log('File saved successfully');
                            // Optionally reload to show updated file
                            // location.reload();
                        })
                        .fail(function(xhr, status, error) {
                            if (typeof $.tikiModal === 'function') {
                                $.tikiModal();
                            }
                            var errorMsg = 'Save failed: ' + (xhr.responseText || error);
                            callback({error: errorMsg});
                            console.error('Save failed:', errorMsg);
                            alert('Save failed: ' + errorMsg);
                        });
                    };
                    reader.onerror = function() {
                        callback({error: 'Failed to read file'});
                        alert('Failed to read file for upload');
                    };
                    reader.readAsDataURL(blob);
                },
                onReady: function() {
                    console.log('CryptPad editor ready');
                    $('.saveButton').prop('disabled', false);
                },
                onDocumentReady: function() {
                    console.log('Document loaded in editor');
                },
                onHasUnsavedChanges: function(hasChanges) {
                    console.log('Unsaved changes:', hasChanges);
                    // Update UI to reflect unsaved changes
                    if (hasChanges) {
                        $('.saveButton').removeClass('btn-secondary').addClass('btn-primary');
                    } else {
                        $('.saveButton').removeClass('btn-primary').addClass('btn-secondary');
                    }
                },
                // Note: onError is not a standard CryptPad API event
                // Errors will be caught in the promise catch block
            },
            editorConfig: {
                user: {
                    id: " . json_encode($user ?? 'anonymous') . ",
                    name: " . json_encode($user ?? 'Anonymous User') . "
                }
            }
        };
        
        // Initialize CryptPad editor
        try {
            // CryptPadAPI is the function itself, not an object with .init()
            var editorPromise = CryptPadAPI(
                cryptpadBaseUrl,
                'tiki_cryptpad',
                config
            );
            
            // Handle promise (CryptPad API may return a promise)
            if (editorPromise && typeof editorPromise.then === 'function') {
                editorPromise
                    .then(function(editor) {
                        console.log('Editor initialized successfully');
                        window.cryptpadEditor = editor;
                    })
                    .catch(function(error) {
                        console.error('Failed to initialize editor:', error);
                        alert('Failed to initialize editor: ' + (error.message || error));
                        fallbackToIframe();
                    });
            } else {
                // API returned editor directly
                console.log('Editor initialized');
                window.cryptpadEditor = editorPromise;
            }
        } catch (error) {
            console.error('Failed to initialize CryptPad editor:', error);
            alert('Failed to initialize editor: ' + error.message);
            fallbackToIframe();
        }
    }
    
    // Fallback to iframe if API fails (this won't work properly - just show error)
    function fallbackToIframe() {
        console.error('CryptPad API failed - fallback not supported');
        var msg = 'Failed to load CryptPad editor. Please check: ' +
            'CryptPad instance is accessible, cryptpad-api.js exists, OnlyOffice is installed.';
        $('#tiki_cryptpad').html('<div class=\"alert alert-danger\">' + msg + '</div>');
    }
    
    // Manual save button handler
    // Note: CryptPad API doesn't expose a direct save() method
    // OnlyOffice has its own save button in the toolbar
    $('.saveButton').off('click').on('click', function() {
        // OnlyOffice editor has its own save button
        // This button can be used to trigger a manual save if needed
        // But typically users should use OnlyOffice's built-in save
        console.log('Manual save button clicked - use OnlyOffice toolbar save button');
        alert('Please use the Save button in the CryptPad/OnlyOffice editor toolbar.');
    });
    
    // Cancel button
    $('.cancelButton').off('click').on('click', function() {
        if (confirm('Are you sure you want to cancel editing? Unsaved changes will be lost.')) {
            var galId = $('input[name=galleryId]').val() || galleryId;
            window.location.href = 'tiki-list_file_gallery.php?galleryId=' + galId;
        }
    });
    
    // Initialize editor when page is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCryptPadEditor);
    } else {
        initCryptPadEditor();
    }
})();
");
    } else {
        $smarty->assign('edit', 'false');

        // View mode: use CryptPad API (same as edit mode but with mode: 'view')
        $headerlib->add_jq_onready("
(function() {
    var importUrl = " . json_encode($importUrl) . ";
    var cryptpadBaseUrl = " . json_encode(rtrim($cryptpadBaseUrl, '/')) . ";
    var documentType = " . json_encode($documentType) . ";
    var fileExtension = " . json_encode($extension) . ";
    
    // Wait for CryptPad API to be available
    var apiWaitStart = Date.now();
    var apiWaitTimeout = 10000;
    function initCryptPadViewer() {
        // Check if CryptPadAPI exists and is a function
        if (typeof CryptPadAPI === 'undefined' || typeof CryptPadAPI !== 'function') {
            var elapsed = Date.now() - apiWaitStart;
            if (elapsed > apiWaitTimeout) {
                console.error('CryptPad API failed to load');
                $('#tiki_cryptpad').html('<div class=\"alert alert-danger\">Failed to load CryptPad viewer. Please check console for errors.</div>');
                return;
            }
            setTimeout(initCryptPadViewer, 100);
            return;
        }
        
        var config = {
            document: {
                url: importUrl,
                fileType: fileExtension,
                key: null  // Let CryptPad generate
            },
            documentType: documentType,
            mode: 'view',  // View-only mode
            width: '100%',
            height: '800px',
            events: {
                onReady: function() {
                    console.log('CryptPad viewer ready');
                },
                onDocumentReady: function() {
                    console.log('Document loaded in viewer');
                }
            }
        };
        
        try {
            // CryptPadAPI is the function itself, not an object with .init()
            var viewerPromise = CryptPadAPI(
                cryptpadBaseUrl,
                'tiki_cryptpad',
                config
            );
            
            if (viewerPromise && typeof viewerPromise.then === 'function') {
                viewerPromise
                    .then(function() {
                        console.log('Viewer initialized successfully');
                    })
                    .catch(function(error) {
                        console.error('Failed to initialize viewer:', error);
                        $('#tiki_cryptpad').html('<div class=\"alert alert-danger\">Failed to load document viewer: ' + (error.message || error) + '</div>');
                    });
            }
        } catch (error) {
            console.error('Failed to initialize viewer:', error);
            $('#tiki_cryptpad').html('<div class=\"alert alert-danger\">Failed to initialize viewer: ' + error.message + '</div>');
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCryptPadViewer);
    } else {
        initCryptPadViewer();
    }
})();
");
    }
} else {
    $smarty->assign('missingPackage', true);
}

// Display the template
$smarty->assign('mid', 'tiki-edit_cryptpad.tpl');
// use tiki_full to include include CSS and JavaScript
$smarty->display('tiki.tpl');
