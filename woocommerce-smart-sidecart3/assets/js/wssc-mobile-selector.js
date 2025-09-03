jQuery(document).ready(function($) {
    // Handle brand selection change
    $(document).on('change', '#mobile_brand', function() {
        var brandName = $(this).val();
        var modelSelect = $('#mobile_model');
        
        // Reset model dropdown
        modelSelect.html('<option value="">Loading models...</option>').prop('disabled', true);
        
        if (brandName) {
            // Fetch models for selected brand
            $.ajax({
                url: wsscMobile.ajax_url,
                type: 'POST',
                data: {
                    action: 'wssc_get_models',
                    brand_name: brandName,
                    nonce: wsscMobile.nonce
                },
                success: function(response) {
                    modelSelect.html('<option value="">Choose Model</option>');
                    
                    if (response && response.length > 0) {
                        $.each(response, function(index, model) {
                            modelSelect.append('<option value="' + model.model_name + '">' + model.model_name + '</option>');
                        });
                        modelSelect.prop('disabled', false);
                    } else {
                        modelSelect.html('<option value="">No models found</option>');
                    }
                },
                error: function() {
                    modelSelect.html('<option value="">Error loading models</option>');
                }
            });
        } else {
            modelSelect.html('<option value="">First select brand</option>');
        }
    });

    // Validate mobile selection before form submission
    $(document).on('submit', 'form.cart', function(e) {
        var mobileBrand = $('#mobile_brand').val();
        var mobileModel = $('#mobile_model').val();
        
        // Only validate if mobile selector exists on the page
        if ($('#mobile_brand').length && $('#mobile_model').length) {
            if (!mobileBrand || !mobileModel) {
                e.preventDefault();
                alert('Please select both mobile brand and model before adding to cart.');
                return false;
            }
        }
    });

    // Enhanced validation for AJAX add to cart (from side cart)
    $(document).on('click', '.wssc-add-btn', function(e) {
        var mobileBrand = $('#mobile_brand').val();
        var mobileModel = $('#mobile_model').val();
        
        // Only validate if mobile selector exists on the page
        if ($('#mobile_brand').length && $('#mobile_model').length) {
            if (!mobileBrand || !mobileModel) {
                e.preventDefault();
                alert('Please select both mobile brand and model before adding to cart.');
                return false;
            }
        }
    });

    // Store selected mobile data in form when adding to cart
    $(document).on('click', '.single_add_to_cart_button, .wssc-add-btn', function() {
        var mobileBrand = $('#mobile_brand').val();
        var mobileModel = $('#mobile_model').val();
        
        if (mobileBrand && mobileModel) {
            // Store in hidden inputs for form submission
            var form = $(this).closest('form');
            
            // Remove existing hidden inputs to avoid duplicates
            form.find('input[name="mobile_brand"]').remove();
            form.find('input[name="mobile_model"]').remove();
            
            // Add new hidden inputs
            form.append('<input type="hidden" name="mobile_brand" value="' + mobileBrand + '">');
            form.append('<input type="hidden" name="mobile_model" value="' + mobileModel + '">');
        }
    });
});