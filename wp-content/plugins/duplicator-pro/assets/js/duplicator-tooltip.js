/*! dup tooltip */
(function ($) {
    DuplicatorTooltip = {
        initialized: false,
        messages: Object.assign(
            {}, 
            {
                'copy': 'Copy to Clipboard',
                'copied': 'Copied to Clipboard',
                'copyUnable': 'Unable to Copy'
            }, 
            (typeof l10nDupTooltip === 'object' ? l10nDupTooltip : {})
        ),
        load: function () {
            if (this.initialized) {
                return;
            }

            this.loadSelector('.duplicator-page .wrap [title], [data-tooltip]');
            this.loadCopySelector('[data-dup-copy-value]');

            this.initialized = true;
        },
        loadSelector: function (selector) {
            $(selector).each(function () {
                if (this._tippy) {
                    // already init
                    return;
                }

                if (typeof $(this).data('tooltip') === 'undefined') {
                    if (typeof $(this).attr('title') !== 'undefined' && $(this).attr('title') !== '') {
                        $(this).attr('data-tooltip', $(this).attr('title') );
                        $(this).removeAttr('title');
                    } else {
                        return;
                    }
                }

                
                
                if (typeof $(this).data('tooltip-width') !== 'undefined') {
                    $maxWdith = $(this).data('tooltip-width');
                } else {
                    $maxWdith = 350;
                }

                tippy(this, {
                    content: function (ref) {
                        var header = ref.dataset.tooltipTitle;
                        var body = ref.dataset.tooltip;
                        var res = header !== undefined ? '<h3>' + header + '</h3>' : '';
                        res += '<div class="dup-tippy-content">' + body + '</div>';
                        return res;
                    },
                    allowHTML: true,
                    interactive: true,
                    placement: this.dataset.tooltipPlacement ? this.dataset.tooltipPlacement : 'bottom-start',
                    theme: 'duplicator',
                    zIndex: 900000,
                    appendTo: document.body,
                    maxWidth: $maxWdith,
                    onCreate(instance) {
                        instance.popper.childNodes.forEach((element) => {
                            element.classList.add('dup-styles');
                        });
                    },
                });
                $(this).data('dup-tooltip-loaded', true);
            });
        },
        loadCopySelector: function (selector) {
            $(selector).each(function () {
                if (this._tippy) {
                    // already init
                    return;
                }

                var element = $(this);
                if (element.hasClass('disabled')) {
                    return;
                }

                var tippyElement = tippy(this, {
                    allowHTML: true,
                    placement: this.dataset.tooltipPlacement ? this.dataset.tooltipPlacement : 'bottom-start',
                    theme: 'duplicator',
                    zIndex: 900000,
                    hideOnClick: false,
                    trigger: 'manual',
                    onCreate(instance) {
                        instance.popper.childNodes.forEach((element) => {
                            element.classList.add('dup-styles');
                        });
                    },
                });

                var copyTitle = element.is('[data-dup-copy-title]') ? element.data('dup-copy-title') : DuplicatorTooltip.messages.copy;
                tippyElement.setContent('<div class="dup-tippy-content">' + copyTitle + '</div>');

                //Have to set manually otherwise might hide on click.
                element.on('mouseover',function () {
                    tippyElement.show();
                }).on('mouseout',function () {
                    tippyElement.hide();
                });

                element.on('click',function () {
                    var valueToCopy = element.data('dup-copy-value');
                    var copiedTitle = element.is('[data-dup-copied-title]') ? element.data('dup-copied-title') : valueToCopy + ' ' + DuplicatorTooltip.messages.copied;
                    var message = DuplicatorTooltip.messages.copyUnable;
                    var tmpArea = jQuery("<textarea></textarea>").css({
                        position: 'absolute',
                        top: '-10000px'
                    }).text(valueToCopy).appendTo("body");
                    tmpArea.select();

                    try {
                        message = document.execCommand('copy') ? copiedTitle : DuplicatorTooltip.messages.copyUnable;
                    } catch (err) {
                        console.log(err);
                    }

                    tippyElement.setContent('<div class="dup-tippy-content">' + message + '</div>');
                    tippyElement.setProps({ theme: 'duplicator-filled' });

                    setTimeout(function () {
                        tippyElement.setContent('<div class="dup-tippy-content">' + copyTitle + '</div>');
                        tippyElement.setProps({ theme: 'duplicator' });
                    }, 2000);
                });
            });
        },
        updateElementContent: function (selector, content) {
            if ($(selector).get(0)) {
                $(selector).get(0)._tippy.setContent('<div class="dup-tippy-content">' + content + '</div>');
            }
        },
        unload: function () {
            var tooltips = document.querySelectorAll('[data-tooltip], [data-dup-copy-value]');
            tooltips.forEach(function (element) {
                if (element._tippy) {
                    element._tippy.destroy();
                    element._tippy = null;
                }
            });
            this.initialized = false;
        },
        reload: function () {
            this.unload();
            this.load();
        }
    }
})(jQuery);