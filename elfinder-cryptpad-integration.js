/**
 * elFinder CryptPad Integration
 * Adds "Edit with CryptPad" option to elFinder context menu for Office documents
 * 
 * Usage: Include this script after elFinder is loaded
 */

(function($) {
    'use strict';
    
    // Wait for elFinder to be available
    if (typeof $.fn.elfinder === 'undefined') {
        console.warn('elFinder not found. CryptPad integration will not work.');
        return;
    }
    
    /**
     * Custom elFinder command: Edit with CryptPad
     */
    var editWithCryptPad = function() {
        var self = this;
        var fm = self.fm;
        
        return {
            // Command info
            info: {
                title: 'Edit with CryptPad',
                name: 'editWithCryptPad',
                icon: 'edit',
                button: false, // Don't show in toolbar
                context: true  // Show in context menu
            },
            
            // Check if command should be enabled
            getstate: function(select) {
                var sel = this.files(select);
                var cnt = sel.length;
                
                if (!cnt) {
                    return -1; // Disabled
                }
                
                // Check if selected files are Office documents
                var supported = ['docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp'];
                var hasSupported = false;
                
                $.each(sel, function(i, file) {
                    if (file.mime) {
                        var ext = file.name.split('.').pop().toLowerCase();
                        if (supported.indexOf(ext) !== -1) {
                            hasSupported = true;
                            return false; // Break loop
                        }
                    }
                });
                
                return hasSupported ? 0 : -1; // 0 = enabled, -1 = disabled
            },
            
            // Execute command
            exec: function(hashes) {
                var self = this;
                var fm = self.fm;
                var files = this.files(hashes);
                var dfrd = $.Deferred();
                
                if (!files.length) {
                    return dfrd.reject();
                }
                
                // Get the first selected file
                var file = files[0];
                
                // Extract file ID from hash or path
                // elFinder hash format: volumeId_hash
                // TikiWiki might store fileId in file data or we need to extract it
                var fileId = null;
                var galleryId = null;
                
                // Try to get fileId from file data
                if (file.data && file.data.fileId) {
                    fileId = file.data.fileId;
                } else if (file.hash) {
                    // Try to extract from hash or use file name to lookup
                    // This depends on how TikiWiki stores file info in elFinder
                    console.log('File hash:', file.hash);
                }
                
                // Alternative: Get fileId from file path or name
                // You may need to adjust this based on your TikiWiki elFinder integration
                if (!fileId && file.url) {
                    // Try to extract fileId from URL
                    var match = file.url.match(/fileId[=\/](\d+)/);
                    if (match) {
                        fileId = match[1];
                    }
                }
                
                // If we still don't have fileId, try to get it from the file name
                // This is a fallback - you may need to adjust based on your setup
                if (!fileId) {
                    console.warn('Could not determine fileId. File:', file);
                    alert('Could not determine file ID. Please check file selection.');
                    return dfrd.reject();
                }
                
                // Get galleryId if available
                if (file.data && file.data.galleryId) {
                    galleryId = file.data.galleryId;
                } else {
                    // Try to get from current directory or use default
                    var currentDir = fm.getDir();
                    if (currentDir && currentDir.data && currentDir.data.galleryId) {
                        galleryId = currentDir.data.galleryId;
                    }
                }
                
                // Build URL to open CryptPad editor
                var baseUrl = window.location.origin + window.location.pathname;
                // Remove elFinder-specific paths if any
                baseUrl = baseUrl.replace(/\/elfinder.*$/, '');
                
                var editUrl = baseUrl + '/tiki-edit_cryptpad.php?fileId=' + fileId;
                if (galleryId) {
                    editUrl += '&galleryId=' + galleryId;
                }
                editUrl += '&edit=1';
                
                console.log('Opening CryptPad editor:', editUrl);
                
                // Open in new window/tab
                window.open(editUrl, '_blank');
                
                return dfrd.resolve();
            }
        };
    };
    
    // Register the command with elFinder
    if (typeof elFinder !== 'undefined' && elFinder.prototype && elFinder.prototype.commands) {
        elFinder.prototype.commands.editWithCryptPad = editWithCryptPad;
    } else {
        // Wait for elFinder to be ready
        $(document).ready(function() {
            // Try to register when elFinder instance is created
            var originalElfinder = $.fn.elfinder;
            $.fn.elfinder = function(options) {
                var instance = originalElfinder.call(this, options);
                
                // Register command after instance is created
                if (instance && instance.constructor && instance.constructor.prototype) {
                    instance.constructor.prototype.commands = instance.constructor.prototype.commands || {};
                    instance.constructor.prototype.commands.editWithCryptPad = editWithCryptPad;
                }
                
                return instance;
            };
        });
    }
    
    // Alternative: Register via elFinder's command manager
    // This works if elFinder is already initialized
    $(document).on('elfinderinit', function(event) {
        var elfinder = event.elfinder;
        if (elfinder && elfinder.commands) {
            elfinder.commands.editWithCryptPad = editWithCryptPad;
            
            // Add to context menu
            if (elfinder.options && elfinder.options.contextmenu) {
                // Ensure contextmenu is an object
                if (typeof elfinder.options.contextmenu === 'object') {
                    // Add to files context menu
                    if (!elfinder.options.contextmenu.files) {
                        elfinder.options.contextmenu.files = [];
                    }
                    if (elfinder.options.contextmenu.files.indexOf('editWithCryptPad') === -1) {
                        elfinder.options.contextmenu.files.push('editWithCryptPad');
                    }
                }
            }
        }
    });
    
})(jQuery);

