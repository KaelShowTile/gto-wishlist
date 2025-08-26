jQuery(document).ready(function($) {
    $(document).on('click', '.remove-from-wishlist', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var product_id = $button.data('product-id');
        var $listItem = $button.closest('.glint-wishlist-product');
        
        $.ajax({
            type: 'POST',
            url: glint_wishlist_remove_btn_params.ajax_url,
            data: {
                action: 'glint_remove_from_wishlist',
                product_id: product_id,
                security: glint_wishlist_remove_btn_params.nonce
            },
            beforeSend: function() {
                $button.addClass('loading');
            },
            success: function(response) {
                if (response.success) {
                    $listItem.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if wishlist is now empty
                        if ($('.glint-wishlist-product').length === 0) {
                            $('.glint-wishlist').html(
                                '<div class="glint-wishlist-empty">' + 
                                glint_wishlist_remove_btn_params.empty_message + 
                                '</div>'
                            );
                        }
                    });
                } else {
                    alert(response.data.message || 'Error removing item');
                }
            },
            complete: function() {
                $button.removeClass('loading');
            }
        });
    });
});