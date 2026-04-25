/**
 * SimpleHideContent - دکمه ویرایشگر برای درج شورت‌کدهای پنهان
 */

(function($) {
    'use strict';

    const templates = {
        hide: {
            name: 'پنهان (نیاز به پاسخ)',
            start: '[hide]',
            end: '[/hide]',
            placeholder: 'متن پنهان شده را اینجا بنویسید'
        },
        login: {
            name: 'ورود (نیاز به لاگین)',
            start: '[login]',
            end: '[/login]',
            placeholder: 'متن قابل مشاهده فقط برای کاربران وارد شده'
        }
    };

    function initEditorButton() {
        var $buttonRow = $('#wmd-button-row');
        if (!$buttonRow.length) {
            setTimeout(initEditorButton, 500);
            return;
        }
        if ($('#wmd-hc-button').length) return;

        var eyeSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';

        var buttonHtml = '<li class="wmd-button" id="wmd-hc-button" title="درج محتوای پنهان" style="list-style:none; display:inline-block; cursor:pointer; margin:0 2px;">' +
            '<span style="display:inline-block; width:20px; height:20px; line-height:20px; text-align:center;">' + eyeSvg + '</span>' +
            '</li>';

        $buttonRow.append(buttonHtml);

        $('body').append('<div id="hc-menu-container" style="display:none; position:absolute; z-index:10000; background:#fff; border:1px solid #ddd; border-radius:4px; box-shadow:0 2px 8px rgba(0,0,0,0.15); min-width:160px; padding:4px 0;"></div>');

        var $menu = $('#hc-menu-container');
        $menu.html('<div class="hc-menu-item" data-type="hide">' + templates.hide.name + '</div>' +
                   '<div class="hc-menu-item" data-type="login">' + templates.login.name + '</div>');

        if (!$('#hc-menu-styles').length) {
            $('<style id="hc-menu-styles">' +
              '.hc-menu-item { padding: 8px 16px; cursor: pointer; font-size: 13px; color: #333; transition: background 0.2s; }' +
              '.hc-menu-item:hover { background: #f5f5f5; }' +
              '</style>').appendTo('head');
        }

        $('#wmd-hc-button').on('click', function(e) {
            e.stopPropagation();
            if ($menu.is(':visible')) {
                $menu.hide();
            } else {
                var offset = $(this).offset();
                $menu.css({
                    top: offset.top + $(this).outerHeight() + 5,
                    left: offset.left
                }).show();
            }
        });

        $menu.on('click', '.hc-menu-item', function(e) {
            e.stopPropagation();
            var type = $(this).data('type');
            insertShortcode(type);
            $menu.hide();
        });

        $(document).on('click', function() {
            $menu.hide();
        });
    }

    function insertShortcode(type) {
        var textarea = document.getElementById('text');
        if (!textarea) return;

        var tmpl = templates[type];
        if (!tmpl) return;

        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var selectedText = textarea.value.substring(start, end);
        var contentToWrap = selectedText;

        if (!contentToWrap) {
            contentToWrap = tmpl.placeholder;
        }

        // اضافه کردن دو خط جدید بعد از شورت‌کد برای ایجاد پاراگراف مجزا در Markdown
        var suffix = '\n\n';
        var newText = tmpl.start + contentToWrap + tmpl.end + suffix;

        var before = textarea.value.substring(0, start);
        var after = textarea.value.substring(end);
        textarea.value = before + newText + after;

        var newPos = start + newText.length;
        textarea.selectionStart = newPos;
        textarea.selectionEnd = newPos;
        textarea.focus();

        var event = new Event('input', { bubbles: true });
        textarea.dispatchEvent(event);
    }

    $(document).ready(function() {
        setTimeout(initEditorButton, 300);
    });

})(window.jQuery || window.$);
