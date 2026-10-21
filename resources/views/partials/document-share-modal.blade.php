<div class="modal fade" id="sx-share-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="sx-share-title">Share</h4>
            </div>
            <div class="modal-body">
                <p class="text-muted" id="sx-share-help">Send this document on WhatsApp or email. The customer gets a link to open the PDF.</p>
                <div class="form-group">
                    <label>WhatsApp number</label>
                    <input type="text" class="form-control" id="sx-share-phone" placeholder="0712 000 000">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" class="form-control" id="sx-share-email" placeholder="customer@email.com">
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea class="form-control" id="sx-share-message" rows="7"></textarea>
                </div>
                <p class="text-danger" id="sx-share-error" style="display:none;"></p>
                <p class="text-success" id="sx-share-ok" style="display:none;"></p>
            </div>
            <div class="modal-footer" style="border-top:0;padding:0 20px 16px;">
                <button type="button" class="btn sx-btn-whatsapp" id="sx-share-whatsapp"><i class="fa fa-whatsapp"></i> WhatsApp</button>
                <button type="button" class="btn btn-primary" id="sx-share-email-send"><i class="fa fa-envelope"></i> Send email</button>
                <a href="#" class="btn btn-default" id="sx-share-mailto" target="_blank"><i class="fa fa-external-link"></i> Email app</a>
                <button type="button" class="btn btn-default" id="sx-share-copy"><i class="fa fa-link"></i> Copy link</button>
            </div>
        </div>
    </div>
</div>
<script>
(function ($) {
    var shareState = {};
    var csrf = $('meta[name="csrf-token"]').attr('content');

    function showAlert(kind, text) {
        $('#sx-share-error, #sx-share-ok').hide().text('');
        if (!text) return;
        $('#sx-share-' + kind).text(text).show();
    }

    function digitsPhone(raw) {
        var digits = String(raw || '').replace(/\D+/g, '');
        if (!digits) return '';
        if (digits.indexOf('254') === 0 && digits.length >= 12) return digits;
        if (digits.charAt(0) === '0' && digits.length === 10) return '254' + digits.slice(1);
        if (digits.length === 9 && digits.charAt(0) === '7') return '254' + digits;
        return digits;
    }

    function currentMessage() {
        return $('#sx-share-message').val() || shareState.message || '';
    }

    function whatsappUrl() {
        var phone = digitsPhone($('#sx-share-phone').val());
        return 'https://wa.me/' + (phone || '') + '?text=' + encodeURIComponent(currentMessage());
    }

    function mailtoUrl() {
        var email = $.trim($('#sx-share-email').val() || '');
        return 'mailto:' + encodeURIComponent(email)
            + '?subject=' + encodeURIComponent(shareState.title || '')
            + '&body=' + encodeURIComponent(currentMessage());
    }

    $(document).on('click', '.sx-share-doc', function (e) {
        e.preventDefault();
        var url = $(this).data('share-url');
        var channel = $(this).data('share-channel') || '';
        if (!url) return;
        showAlert('', '');
        $.getJSON(url).done(function (data) {
            shareState = data || {};
            $('#sx-share-title').text(shareState.title || 'Share');
            $('#sx-share-phone').val(shareState.phone || '');
            $('#sx-share-email').val(shareState.email || '');
            $('#sx-share-message').val(shareState.message || '');
            $('#sx-share-mailto').attr('href', shareState.mailto || '#');
            $('#sx-share-modal').modal('show');
            if (channel === 'email') {
                setTimeout(function () { $('#sx-share-email').focus(); }, 400);
            } else if (channel === 'whatsapp') {
                setTimeout(function () { $('#sx-share-phone').focus(); }, 400);
            }
        }).fail(function () {
            alert('Could not prepare this document for sharing.');
        });
    });

    $('#sx-share-whatsapp').on('click', function () {
        var wa = whatsappUrl();
        var pdfUrl = shareState.pdf_url;
        var filename = shareState.filename || 'document.pdf';
        if (navigator.share && pdfUrl) {
            fetch(pdfUrl, { credentials: 'same-origin' }).then(function (res) {
                if (!res.ok) throw new Error('pdf');
                return res.blob();
            }).then(function (blob) {
                var file = new File([blob], filename, { type: 'application/pdf' });
                var payload = { title: shareState.title || 'Document', text: currentMessage(), files: [file] };
                if (navigator.canShare && navigator.canShare(payload)) {
                    return navigator.share(payload);
                }
                window.open(wa, '_blank');
            }).catch(function () {
                window.open(wa, '_blank');
            });
            return;
        }
        window.open(wa, '_blank');
    });

    $('#sx-share-mailto').on('click', function (e) {
        this.href = mailtoUrl();
        if (!$.trim($('#sx-share-email').val() || '')) {
            e.preventDefault();
            showAlert('error', 'Enter an email address first.');
        }
    });

    $('#sx-share-email-send').on('click', function () {
        var email = $.trim($('#sx-share-email').val() || '');
        var btn = $(this);
        if (!email) {
            showAlert('error', 'Enter an email address first.');
            return;
        }
        if (!shareState.email_url) return;
        btn.prop('disabled', true);
        showAlert('', '');
        $.ajax({
            url: shareState.email_url,
            method: 'POST',
            data: { email: email, _token: csrf },
            dataType: 'json'
        }).done(function (res) {
            showAlert('ok', (res && res.message) ? res.message : 'Email sent.');
        }).fail(function (xhr) {
            var msg = 'Could not send email. Use Email app instead, or check mail settings.';
            if (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.email) {
                msg = xhr.responseJSON.errors.email[0];
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
            showAlert('error', msg);
        }).always(function () {
            btn.prop('disabled', false);
        });
    });

    $('#sx-share-copy').on('click', function () {
        var link = shareState.share_url || '';
        if (!link) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(link).then(function () {
                showAlert('ok', 'Link copied.');
            }).catch(function () {
                window.prompt('Copy this link', link);
            });
            return;
        }
        window.prompt('Copy this link', link);
    });
})(jQuery);
</script>
