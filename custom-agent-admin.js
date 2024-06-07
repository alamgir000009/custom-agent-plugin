jQuery(document).ready(function($) {
    $('.agent-status-select').change(function() {
        var post_id = $(this).data('post-id');
        var new_status = $(this).val();
        var nonce = $(this).data('nonce');

        $.ajax({
            url: customAgentAdmin.ajax_url,
            method: 'POST',
            data: {
                action: 'update_agent_status',
                post_id: post_id,
                new_status: new_status,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Status updated successfully');
                    window.location.reload();
                } else {
                    alert('Failed to update status: ' + response.data);
                }
            }
        });
    });
});
