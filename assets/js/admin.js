jQuery(document).ready(function($) {
    $('.sync-stock').on('click', function() {
        var button = $(this);
        var marketplace = button.data('marketplace');
        
        button.prop('disabled', true).text(apiIsarud.updating);
        
        $.ajax({
            url: apiIsarud.ajaxurl,
            type: 'POST',
            data: {
                action: 'isarud_sync_stock',
                marketplace: marketplace,
                nonce: apiIsarud.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(apiIsarud.success);
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert(apiIsarud.error);
            },
            complete: function() {
                button.prop('disabled', false).text(apiIsarud.updateStock);
                location.reload();
            }
        });
    });
});