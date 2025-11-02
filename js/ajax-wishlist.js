jQuery(document).ready(function($)
{
    var ajax_url = glint_wishlist_vars.ajax_url;
    
    $('body').on('click', '.wishlist-toggle', function(e) 
    {
        e.preventDefault();
        
        var $button = $(this);
        var product_id = $button.data('product-id');
        
        $.ajax({
            url: ajax_url,
            type: 'POST',
            data: {
                action: 'glint_wishlist_toggle',
                product_id: product_id
            },
            dataType: 'json',
            beforeSend: function() {
                $button.addClass('loading');
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.status === 'added') {
                        $button.addClass('in-wishlist');
                        $button.find('.text').text(glint_wishlist_vars.remove_text || 'Remove from Wishlist');
                    } else {
                        $button.removeClass('in-wishlist');
                        $button.find('.text').text(glint_wishlist_vars.add_text || 'Add to Wishlist');
                        console.error('Server error:', response);
                    }
                    
                    // Update wishlist count if needed
                    if (typeof updateWishlistCount === 'function') {
                        updateWishlistCount(response.data.count);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
            },
            complete: function() {
                $button.removeClass('loading');
            }
        });
    });
});
