jQuery(document).ready(function($) {
    $('#aipu-request-google-indexing').on('click', function() {
        var $button = $(this);
        var $status = $('#aipu-indexing-status');

        $status.html('Requesting...').removeClass('success error').show();
        $button.prop('disabled', true);

        $.ajax({
            url: aipuMetaBox.ajax_url,
            type: 'POST',
            data: {
                action: 'aipu_request_indexing',
                nonce: aipuMetaBox.nonce,
                post_id: aipuMetaBox.post_id
            },
            success: function(response) {
                if (response.success) {
                    $status.html(response.data.message).addClass('success');
                } else {
                    $status.html(response.data.message || 'Error during request.').addClass('error');
                }
            },
            error: function(xhr) {
                $status.html('AJAX error: ' + xhr.statusText).addClass('error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    // Placeholder for scan link button (no AJAX call implemented yet for this)
    $('.aipu-scan-link-btn').on('click', function() {
        var url = $(this).data('url');
        $('#aipu-scan-link-results').html('<em>Scanning ' + url + '... (This is a mock action for now)</em>');
        // TODO: Implement AJAX call to scan the link if desired
        // For now, it just shows a message.
        // The AIPU_External_Retriever->scan_linked_website(url) would be called via AJAX.
    });
});
