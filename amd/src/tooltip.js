define(['jquery'], function ($) {
    'use strict';

    const TOOLTIP_ID = 'progressbar_tooltip';
    const HIDE_DELAY_MS = 150;

    let instance = null;

    class Tooltip {

        constructor() {
            this.$el = $('<div>', {
                id: TOOLTIP_ID,
                class: 'progressbar_tooltip',
                role: 'tooltip',
                'aria-hidden': 'true',
            }).appendTo('body');
            this.$label = $('<span>', { class: 'progressbar_tooltip-label' }).appendTo(this.$el);

            this._$current = null;
            this._hideTimer = null;

            const hide = () => this.hide();
            $(window).on('scroll.progressbar-tooltip resize.progressbar-tooltip', hide);
        }

        /**
         * Show the tooltip anchored above $target.
         *
         * @param {jQuery} $target  The element to anchor to.
         * @param {string} text     Label to display (truncated when over MAX_CHARS).
         */
        show($target, text) {
            clearTimeout(this._hideTimer);

            if (this._$current && this._$current[0] !== $target[0]) {
                this._$current.removeAttr('aria-describedby');
            }

            this._$current = $target;
            this._$current.attr('aria-describedby', TOOLTIP_ID);

            this.$label.text(text);
            this.$el
                .attr('aria-hidden', 'false')
                .addClass('progressbar_tooltip--visible');

            this._position($target);
        }

        hide() {
            this._hideTimer = setTimeout(() => {
                if (this._$current) {
                    this._$current.removeAttr('aria-describedby');
                    this._$current = null;
                }
                this.$el
                    .attr('aria-hidden', 'true')
                    .removeClass('progressbar_tooltip--visible');
            }, HIDE_DELAY_MS);
        }

        /**
         * Attach hover/focus/keyboard listeners to .progressbar-item elements
         * inside $container.
         *
         * @param {jQuery} $container
         */
        attachTo($container) {
            $container.on('mouseenter.progressbar-tooltip', '.progressbar-item', (e) => {
                this.show($(e.currentTarget), $(e.currentTarget).data('name') || '');
            });

            $container.on('mouseleave.progressbar-tooltip', '.progressbar-item', () => {
                this.hide();
            });

            $container.on('focusin.progressbar-tooltip', '.progressbar-item', (e) => {
                this.show($(e.currentTarget), $(e.currentTarget).data('name') || '');
            });

            $container.on('focusout.progressbar-tooltip', '.progressbar-item', () => {
                this.hide();
            });

            $container.on('keydown.progressbar-tooltip', (e) => {
                if (e.key === 'Escape') {
                    clearTimeout(this._hideTimer);
                    this.hide();
                }
            });
        }

        _position($target) {
            const rect = $target[0].getBoundingClientRect();

            // Measure rendered size before placing (visibility:hidden avoids flash).
            this.$el.css({ visibility: 'hidden', left: 0, top: 0 });
            const tooltipW = this.$el.outerWidth(true);
            const tooltipH = this.$el.outerHeight(true);
            this.$el.css('visibility', '');

            // Center tooltip above the target, clamped to viewport with 4px margin.
            const margin = 4;
            const targetCenterX = rect.left + rect.width / 2;
            const idealLeft = targetCenterX - tooltipW / 2;
            const clampedLeft = Math.max(margin, Math.min(idealLeft, window.innerWidth - tooltipW - margin));

            // Shift the CSS arrow to keep it pointing at the target after clamping.
            const arrowOffset = Math.round(targetCenterX - (clampedLeft + tooltipW / 2));
            this.$el[0].style.setProperty('--arrow-x', arrowOffset + 'px');

            this.$el.css({
                top: Math.round(rect.top - tooltipH - 6) + 'px',
                left: Math.round(clampedLeft) + 'px',
            });
        }

        static getInstance() {
            if (!instance) {
                instance = new Tooltip();
            }
            return instance;
        }
    }

    return Tooltip;
});
