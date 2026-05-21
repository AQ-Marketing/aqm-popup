(function ($) {
    'use strict';

    function syncTrigger($checkbox) {
        var trigger = $checkbox.data('aqmTrigger');
        if (!trigger) return;
        var $sub = $('[data-aqm-trigger-sub="' + trigger + '"]');
        if (!$sub.length) return;
        $sub.toggleClass('is-disabled', !$checkbox.is(':checked'));
    }

    function bindUpdateCheck() {
        var $btn = $('#aqm-popup-check-updates');
        var $result = $('#aqm-popup-check-updates-result');
        if (!$btn.length || !$result.length || typeof aqmPopupAdmin === 'undefined') return;

        $btn.on('click', function (e) {
            e.preventDefault();
            $btn.prop('disabled', true);
            $result
                .text(aqmPopupAdmin.i18n && aqmPopupAdmin.i18n.checking ? aqmPopupAdmin.i18n.checking : 'Checking…')
                .css({ color: '#646970', fontWeight: 'normal' });

            $.post(aqmPopupAdmin.ajaxUrl, {
                action: 'aqm_popup_check_updates',
                nonce: aqmPopupAdmin.nonce
            }).done(function (response) {
                if (!response || !response.success) {
                    var msg = (response && response.data && response.data.message) ||
                              (aqmPopupAdmin.i18n && aqmPopupAdmin.i18n.failed) ||
                              'Check failed.';
                    $result.text(msg).css({ color: '#b32d2e', fontWeight: 'bold' });
                    return;
                }
                var data = response.data || {};
                if (data.update_available) {
                    $result.empty();
                    $('<strong>').text(data.message).appendTo($result);
                    if (data.updates_url) {
                        $result.append(' ');
                        $('<a>').attr('href', data.updates_url).text('Open Plugins').appendTo($result);
                    }
                    $result.css({ color: '#00735c' });
                } else {
                    $result.text(data.message || '').css({ color: '#646970', fontWeight: 'normal' });
                }
            }).fail(function (xhr) {
                var msg = (aqmPopupAdmin.i18n && aqmPopupAdmin.i18n.failed) || 'Network error.';
                if (xhr && xhr.status) msg += ' (HTTP ' + xhr.status + ')';
                $result.text(msg).css({ color: '#b32d2e', fontWeight: 'bold' });
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });
    }

    $(function () {
        $('[data-aqm-trigger]').each(function () {
            syncTrigger($(this));
        }).on('change', function () {
            syncTrigger($(this));
        });

        bindUpdateCheck();
    });
})(jQuery);
