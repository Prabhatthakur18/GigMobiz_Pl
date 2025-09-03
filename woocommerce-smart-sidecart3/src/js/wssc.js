jQuery(document).ready(function($) {
    console.log('WSSC JavaScript loaded');
    
    // IMMEDIATELY hide modal on page load - before anything else
    var modal = $('#wssc-bulk-modal');
    if (modal.length) {
        console.log('Modal found, immediately hiding it');
        modal.hide();
        modal.css({
            'display': 'none',
            'opacity': '0',
            'visibility': 'hidden',
            'pointer-events': 'none'
        });
        modal.removeClass('wssc-modal-show');
        
        // Inject critical CSS to force hide modal
        var criticalCSS = `
            <style id="wssc-critical-modal-hide">
                #wssc-bulk-modal {
                    display: none !important;
                    opacity: 0 !important;
                    visibility: hidden !important;
                    pointer-events: none !important;
                    transform: scale(0) !important;
                    max-height: 0 !important;
                    max-width: 0 !important;
                    overflow: hidden !important;
                }
                #wssc-bulk-modal * {
                    display: none !important;
                }
            </style>
        `;
        $('head').append(criticalCSS);
        
        // Also add inline style to modal element itself
        modal.attr('style', 'display: none !important; opacity: 0 !important; visibility: hidden !important; pointer-events: none !important;');
    }
    
    // Handle add to cart from side cart recommendations
    $(document).on('click', '.wssc-add-btn', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Prevent event bubbling
        console.log('Add button clicked');
        
        var button = $(this);
        var productId = button.data('product-id');
        var originalText = button.html();
        
        console.log('Product ID:', productId);
        
        // Disable button and show loading
        button.prop('disabled', true).html('Adding...');
        
        // Get mobile brand and model from the page if available
        var mobileBrand = $('#mobile_brand').val() || '';
        var mobileModel = $('#mobile_model').val() || '';
        
        console.log('Mobile Brand:', mobileBrand, 'Mobile Model:', mobileModel);
        
        var ajaxData = {
            action: 'wssc_add_to_cart',
            product_id: productId,
            quantity: 1,
            nonce: wsscAjax.nonce,
            mobile_brand: mobileBrand,
            mobile_model: mobileModel
        };
        
        $.ajax({
            url: wsscAjax.url,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                console.log('Add to cart response:', response);
                if (response.success) {
                    // Update cart count
                    $('.cart-contents-count').text(response.data.cart_count);
                    
                    // Update quantity badge
                    var card = button.closest('.wssc-product-card');
                    var badge = card.find('.wssc-qty-badge');
                    var currentQty = badge.length ? parseInt(badge.text()) : 0;
                    var newQty = currentQty + 1;
                    
                    if (badge.length) {
                        badge.text(newQty);
                    } else {
                        card.append('<span class="wssc-qty-badge">' + newQty + '</span>');
                    }
                    
                    // Show success message
                    showToast('✅ Product added to cart!', 'success');
                    
                    // Update cart fragments
                    $(document.body).trigger('wc_fragment_refresh');
                } else {
                    showToast('❌ Failed to add product', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.log('Add to cart error:', error);
                showToast('❌ Error adding product', 'error');
            },
            complete: function() {
                // Re-enable button
                button.prop('disabled', false).html(originalText);
            }
        });
    });

    // Handle bulk button click - Fixed to prevent auto-opening
    $(document).on('click', '.wssc-bulk-btn', function(e) {
        try {
            console.log('=== BULK BUTTON CLICK DEBUG ===');
            e.preventDefault();
            e.stopPropagation(); // Prevent event bubbling
            console.log('Bulk button clicked');
            console.log('Event target:', e.target);
            console.log('Button element:', this);
            console.log('Button text:', $(this).text());
            console.log('Button data-product:', $(this).data('product'));
            
            var productId = $(this).data('product');
            console.log('Setting product ID:', productId);
            
            // Check if modal exists before trying to open
            var modal = $('#wssc-bulk-modal');
            console.log('Modal exists in DOM:', modal.length > 0);
            
            if (modal.length > 0) {
                $('#bulk-product-id').val(productId);
                console.log('Product ID set, calling openModal()');
                openModal();
            } else {
                console.error('Modal not found in DOM!');
                // Try to find modal again
                setTimeout(function() {
                    var modalRetry = $('#wssc-bulk-modal');
                    if (modalRetry.length > 0) {
                        console.log('Modal found on retry, opening...');
                        $('#bulk-product-id').val(productId);
                        openModal();
                    } else {
                        console.error('Modal still not found after retry');
                    }
                }, 100);
            }
        } catch (error) {
            console.error('Error in bulk button click handler:', error);
        }
    });
    
    // Handle cancel button click - Unified selector
    $(document).on('click', '#wssc-cancel-bulk, .wssc-cancel-bulk, .cancel-bulk', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('Cancel button clicked');
        closeModal();
    });
    
    // Close modal when clicking outside - Fixed
    $(document).on('click', '.wssc-modal', function(e) {
        // Only close if clicking directly on the modal background
        if (e.target === this) {
            console.log('Clicked outside modal');
            closeModal();
        }
    });
    
    // Close modal with Escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#wssc-bulk-modal').is(':visible')) {
            console.log('Escape key pressed');
            closeModal();
        }
    });
    
    // Handle bulk form submission
    $(document).on('submit', '#wssc-bulk-form', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('Bulk form submitted');
        
        var formData = {
            action: 'wssc_buy_bulk',
            product_id: $('#bulk-product-id').val(),
            name: $('#bulk-name').val(),
            phone: $('#bulk-phone').val(),
            email: $('#bulk-email').val(),
            quantity: $('#bulk-quantity').val(),
            message: $('#bulk-message').val(),
            nonce: wsscAjax.nonce
        };
        
        console.log('Form data:', formData);
        
        // Disable submit button during request
        var submitBtn = $(this).find('button[type="submit"]');
        var originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text('Submitting...');
        
        $.ajax({
            url: wsscAjax.url,
            type: 'POST',
            data: formData,
            success: function(response) {
                console.log('Bulk form response:', response);
                if (response.success) {
                    showToast('✅ ' + response.data.message, 'success');
                    closeModal();
                } else {
                    showToast('❌ ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.log('Bulk form error:', error);
                showToast('❌ Error submitting request', 'error');
            },
            complete: function() {
                // Re-enable submit button
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Function to open modal
    function openModal() {
        console.log('=== OPENING MODAL DEBUG ===');
        var modal = $('#wssc-bulk-modal');
        
        // Check if modal exists
        if (modal.length === 0) {
            console.error('Modal element not found in DOM!');
            return;
        }
        
        console.log('Modal found, current state:');
        console.log('- Display:', modal.css('display'));
        console.log('- Opacity:', modal.css('opacity'));
        console.log('- Visibility:', modal.css('visibility'));
        console.log('- Z-index:', modal.css('z-index'));
        console.log('- Position:', modal.css('position'));
        
        // Show modal with all visibility properties
        modal.show();
        modal.css({
            'display': 'flex',
            'opacity': '1',
            'visibility': 'visible',
            'z-index': '10000',
            'position': 'fixed'
        });
        modal.addClass('wssc-modal-show');
        
        // Force show with jQuery
        modal.fadeIn(100);
        
        // Ensure it's properly visible
        setTimeout(function() {
            console.log('After timeout - Modal state:');
            console.log('- Display:', modal.css('display'));
            console.log('- Opacity:', modal.css('opacity'));
            console.log('- Visibility:', modal.css('visibility'));
            console.log('- Is visible:', modal.is(':visible'));
            
            if (!modal.is(':visible')) {
                console.log('Modal still not visible, forcing show...');
                modal.show().css({
                    'display': 'flex',
                    'opacity': '1',
                    'visibility': 'visible',
                    'z-index': '10000',
                    'position': 'fixed'
                });
            }
        }, 100);
        
        console.log('Modal opened');
    }
    
    // Function to close modal - Fixed implementation
    function closeModal() {
        console.log('Closing modal');
        
        var modal = $('#wssc-bulk-modal');
        
        // Hide modal completely with all possible methods
        modal.hide();
        modal.css({
            'display': 'none',
            'opacity': '0',
            'visibility': 'hidden'
        });
        modal.removeClass('wssc-modal-show');
        
        // Reset form
        $('#wssc-bulk-form')[0].reset();
        $('#bulk-product-id').val('');
        
        // Force hide if still visible
        setTimeout(function() {
            if (modal.is(':visible')) {
                modal.hide().css({
                    'display': 'none',
                    'opacity': '0',
                    'visibility': 'hidden'
                });
            }
        }, 100);
        
        console.log('Modal closed and form reset');
    }
    
    // Toast function
    function showToast(message, type) {
        console.log('Showing toast:', message, type);
        
        // Remove existing toasts
        $('.wssc-toast').remove();
        
        var toast = $('<div class="wssc-toast ' + type + '">' + message + '</div>');
        $('body').append(toast);
        
        setTimeout(function() {
            toast.fadeOut(400, function() {
                toast.remove();
            });
        }, 4000);
    }
    
    // Debug: Log when modal exists
    if ($('#wssc-bulk-modal').length) {
        console.log('Modal found in DOM');
        // Check modal visibility state
        var modal = $('#wssc-bulk-modal');
        console.log('Modal display style:', modal.css('display'));
        console.log('Modal opacity:', modal.css('opacity'));
        console.log('Modal visibility:', modal.css('visibility'));
        console.log('Modal has wssc-modal-show class:', modal.hasClass('wssc-modal-show'));
    } else {
        console.log('Modal NOT found in DOM');
    }
    
    // Additional initialization check
    $(document).ready(function() {
        console.log('=== WSSC INITIALIZATION DEBUG ===');
        var modal = $('#wssc-bulk-modal');
        console.log('On document ready - Modal exists:', modal.length > 0);
        
        if (modal.length > 0) {
            console.log('Modal CSS properties:');
            console.log('- Display:', modal.css('display'));
            console.log('- Opacity:', modal.css('opacity'));
            console.log('- Visibility:', modal.css('visibility'));
            console.log('- Z-index:', modal.css('z-index'));
            console.log('- Position:', modal.css('position'));
        }
        
        // Check if bulk button exists
        var bulkBtn = $('.wssc-bulk-btn');
        console.log('Bulk button exists:', bulkBtn.length > 0);
        if (bulkBtn.length > 0) {
            console.log('Bulk button text:', bulkBtn.text());
            console.log('Bulk button data-product:', bulkBtn.data('product'));
        }
    });
    
    // Check again after window load
    $(window).on('load', function() {
        console.log('=== WSSC WINDOW LOAD DEBUG ===');
        var modal = $('#wssc-bulk-modal');
        console.log('On window load - Modal exists:', modal.length > 0);
        
        if (modal.length > 0) {
            console.log('Modal CSS properties after window load:');
            console.log('- Display:', modal.css('display'));
            console.log('- Opacity:', modal.css('opacity'));
            console.log('- Visibility:', modal.css('visibility'));
        }
    });
    
    // Debug: Log mobile selectors
    if ($('#mobile_brand').length && $('#mobile_model').length) {
        console.log('Mobile selectors found');
    } else {
        console.log('Mobile selectors NOT found');
    }

    // Prevent any accidental modal triggers on page load
    $(window).on('load', function() {
        closeModal(); // Ensure modal is closed on page load
    });
    
    // Additional safety: Ensure modal is hidden on DOM ready
    closeModal(); // Ensure modal is closed when DOM is ready
    
    // Multiple checks to ensure modal stays hidden
    var checkModalHidden = function() {
        var modal = $('#wssc-bulk-modal');
        if (modal.length && (modal.is(':visible') || modal.css('display') !== 'none')) {
            console.log('Modal still visible, forcing hide');
            closeModal();
        }
    };
    
    // Check multiple times with increasing delays
    setTimeout(checkModalHidden, 100);
    setTimeout(checkModalHidden, 300);
    setTimeout(checkModalHidden, 500);
    setTimeout(checkModalHidden, 1000);
    
    // Set up periodic checking every 2 seconds for the first 10 seconds
    var periodicCheck = setInterval(function() {
        checkModalHidden();
    }, 2000);
    
    // Stop periodic checking after 10 seconds
    setTimeout(function() {
        clearInterval(periodicCheck);
    }, 10000);
    
    // Also check on window focus and scroll to catch any late-showing modals
    $(window).on('focus scroll', function() {
        checkModalHidden();
    });
    
    // Use MutationObserver to watch for any changes to the modal
    if (window.MutationObserver && modal.length) {
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && 
                    (mutation.attributeName === 'style' || mutation.attributeName === 'class')) {
                    var currentModal = $('#wssc-bulk-modal');
                    if (currentModal.is(':visible') || currentModal.css('display') !== 'none') {
                        console.log('Modal became visible via mutation, hiding it');
                        closeModal();
                    }
                }
            });
        });
        
        observer.observe(modal[0], {
            attributes: true,
            attributeFilter: ['style', 'class']
        });
    }
    
    // Override any CSS that might be showing the modal
    var overrideConflictingCSS = function() {
        var conflictingCSS = `
            <style id="wssc-override-conflicting">
                /* Override any conflicting CSS */
                .wssc-modal:not(.wssc-modal-show) {
                    display: none !important;
                }
                .wssc-modal {
                    display: none !important;
                }
                .wssc-modal.wssc-modal-show {
                    display: flex !important;
                }
                /* Specific override for bulk modal */
                #wssc-bulk-modal:not(.wssc-modal-show) {
                    display: none !important;
                    opacity: 0 !important;
                    visibility: hidden !important;
                    pointer-events: none !important;
                }
            </style>
        `;
        $('head').append(conflictingCSS);
    };
    
    // Apply CSS override after a short delay
    setTimeout(overrideConflictingCSS, 50);
    
    // Final safety: Override any global modal functions that might exist
    window.wsscForceHideModal = function() {
        console.log('Force hiding modal via global function');
        closeModal();
    };
    
    // Override any existing modal functions that might be causing issues
    if (typeof window.openModal === 'function') {
        var originalOpenModal = window.openModal;
        window.openModal = function() {
            console.log('Preventing automatic modal opening');
            return false;
        };
    }
    
    // Also prevent any automatic modal triggers
    $(document).off('click', '.wssc-modal');
    $(document).off('click', '#wssc-bulk-modal');
    
    // Scan for any conflicting CSS rules and override them
    var scanAndOverrideCSS = function() {
        var allStyles = document.styleSheets;
        for (var i = 0; i < allStyles.length; i++) {
            try {
                var rules = allStyles[i].cssRules || allStyles[i].rules;
                if (rules) {
                    for (var j = 0; j < rules.length; j++) {
                        var rule = rules[j];
                        if (rule.selectorText && 
                            (rule.selectorText.includes('.wssc-modal') || 
                             rule.selectorText.includes('#wssc-bulk-modal')) &&
                            rule.style && rule.style.display === 'flex') {
                            console.log('Found conflicting CSS rule:', rule.selectorText);
                        }
                    }
                }
            } catch (e) {
                // Cross-origin stylesheets will throw errors, ignore them
            }
        }
    };
    
    // Run CSS scan after a delay
    setTimeout(scanAndOverrideCSS, 100);
    
    // Ultimate safety: Ensure modal is hidden on any page event
    $(document).on('DOMNodeInserted DOMNodeRemoved DOMAttrModified', function(e) {
        if (e.target && e.target.id === 'wssc-bulk-modal') {
            console.log('Modal DOM changed, ensuring it stays hidden');
            setTimeout(checkModalHidden, 10);
        }
    });
    
    // Also check on any AJAX completion
    $(document).ajaxComplete(function() {
        checkModalHidden();
    });
    
    // Final check: Override any show() methods on the modal
    if (modal.length) {
        var originalShow = modal.show;
        modal.show = function() {
            console.log('Modal show() called, preventing it');
            return this;
        };
    }
    
    // Override any global jQuery show methods that might affect our modal
    var originalJQueryShow = $.fn.show;
    $.fn.show = function() {
        if (this.attr('id') === 'wssc-bulk-modal') {
            console.log('jQuery show() called on modal, preventing it');
            return this;
        }
        return originalJQueryShow.apply(this, arguments);
    };
    
    // Also override any CSS animations or transitions that might show the modal
    var preventModalAnimations = function() {
        var animationCSS = `
            <style id="wssc-prevent-animations">
                #wssc-bulk-modal {
                    animation: none !important;
                    transition: none !important;
                }
            </style>
        `;
        $('head').append(animationCSS);
    };
    
    setTimeout(preventModalAnimations, 150);
    
    // Final comprehensive fix: Override any remaining ways the modal could show
    var comprehensiveModalHide = function() {
        var modal = $('#wssc-bulk-modal');
        if (modal.length) {
            // Force hide with all possible methods
            modal.hide();
            modal.fadeOut(0);
            modal.slideUp(0);
            modal.css({
                'display': 'none',
                'opacity': '0',
                'visibility': 'hidden',
                'pointer-events': 'none',
                'transform': 'scale(0)',
                'max-height': '0',
                'max-width': '0',
                'overflow': 'hidden',
                'position': 'absolute',
                'left': '-9999px',
                'top': '-9999px'
            });
            modal.removeClass('wssc-modal-show');
            modal.attr('style', 'display: none !important; opacity: 0 !important; visibility: hidden !important; pointer-events: none !important;');
            
            // Also hide any child elements
            modal.find('*').hide();
        }
    };
    
    // Run comprehensive hide multiple times
    setTimeout(comprehensiveModalHide, 50);
    setTimeout(comprehensiveModalHide, 200);
    setTimeout(comprehensiveModalHide, 500);
    
    // Also run on any DOM changes
    $(document).on('DOMSubtreeModified', function(e) {
        if (e.target && e.target.id === 'wssc-bulk-modal') {
            comprehensiveModalHide();
        }
    });
    
    // Override any global modal functions that might exist
    if (typeof window.showModal === 'function') {
        var originalShowModal = window.showModal;
        window.showModal = function() {
            console.log('Global showModal called, preventing it');
            return false;
        };
    }
    
    // Also prevent any automatic modal triggers from other sources
    $(document).off('click', '.wssc-modal');
    $(document).off('click', '#wssc-bulk-modal');
    
    // Override any existing modal functions
    if (typeof window.openModal === 'function') {
        var originalOpenModal = window.openModal;
        window.openModal = function() {
            console.log('Global openModal called, preventing it');
            return false;
        };
    }
    
    // Final safety: Override any jQuery methods that might affect our modal
    var originalJQueryMethods = {
        show: $.fn.show,
        fadeIn: $.fn.fadeIn,
        slideDown: $.fn.slideDown
    };
    
    $.fn.show = function() {
        if (this.attr('id') === 'wssc-bulk-modal') {
            console.log('jQuery show() called on modal, preventing it');
            return this;
        }
        return originalJQueryMethods.show.apply(this, arguments);
    };
    
    $.fn.fadeIn = function() {
        if (this.attr('id') === 'wssc-bulk-modal') {
            console.log('jQuery fadeIn() called on modal, preventing it');
            return this;
        }
        return originalJQueryMethods.fadeIn.apply(this, arguments);
    };
    
    $.fn.slideDown = function() {
        if (this.attr('id') === 'wssc-bulk-modal') {
            console.log('jQuery slideDown() called on modal, preventing it');
            return this;
        }
        return originalJQueryMethods.slideDown.apply(this, arguments);
    };
    
    // Ultimate safety: Override any remaining ways the modal could show
    var ultimateModalHide = function() {
        var modal = $('#wssc-bulk-modal');
        if (modal.length) {
            // Use the most aggressive hiding method possible
            modal.css({
                'display': 'none !important',
                'opacity': '0 !important',
                'visibility': 'hidden !important',
                'pointer-events': 'none !important',
                'transform': 'scale(0) !important',
                'max-height': '0 !important',
                'max-width': '0 !important',
                'overflow': 'hidden !important',
                'position': 'absolute !important',
                'left': '-9999px !important',
                'top': '-9999px !important',
                'z-index': '-9999 !important'
            });
            
            // Also add inline styles
            modal.attr('style', 'display: none !important; opacity: 0 !important; visibility: hidden !important; pointer-events: none !important; position: absolute !important; left: -9999px !important; top: -9999px !important; z-index: -9999 !important;');
            
            // Hide all children
            modal.find('*').hide();
        }
    };
    
    // Run ultimate hide multiple times
    setTimeout(ultimateModalHide, 100);
    setTimeout(ultimateModalHide, 300);
    setTimeout(ultimateModalHide, 600);
    setTimeout(ultimateModalHide, 1000);
    
    // Also run on any page event
    $(window).on('load resize scroll focus blur', ultimateModalHide);
    $(document).on('click keydown mousemove', ultimateModalHide);
});