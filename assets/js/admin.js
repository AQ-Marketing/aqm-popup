(function ($) {
    'use strict';

    function syncTrigger($checkbox) {
        var trigger = $checkbox.data('aqmTrigger');
        if (!trigger) return;
        var $sub = $('[data-aqm-trigger-sub="' + trigger + '"]');
        if (!$sub.length) return;
        $sub.toggleClass('is-disabled', !$checkbox.is(':checked'));
    }

    $(function () {
        $('[data-aqm-trigger]').each(function () {
            syncTrigger($(this));
        }).on('change', function () {
            syncTrigger($(this));
        });
    });
})(jQuery);
