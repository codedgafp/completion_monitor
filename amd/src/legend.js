define([
    'jquery',
    'core/modal',
    'core/str',
], function ($, Modal, Str) {
    'use strict';

    class Legend {

        constructor($container) {
            this.$container = $container;
            this.$toggle = $container.find('.progressbar_legend-toggle');
            this.$content = $container.find('.progressbar_legend-content');
            this.isOpen = false;

            this._bindEvents();
        }

        _bindEvents() {
            this.$toggle.on('click', (e) => {
                if (this._isMobile()) {
                    this._openModal();
                } else {
                    this._toggleDesktop();
                }

                if (e.detail > 0) {
                    this.$toggle.trigger('blur');
                }
            });
        }

        _isMobile() {
            return $(window).width() < 768;
        }

        _toggleDesktop() {
            if (this.isOpen) {
                this._closeDesktop();
            } else {
                this._openDesktop();
            }
        }

        async _openDesktop() {
            this.isOpen = true;
            this.$content.removeAttr('hidden');
            this.$toggle
                .attr('aria-expanded', 'true')
                .find('.fa')
                .removeClass('fa-chevron-down')
                .addClass('fa-chevron-up');

            const hideLegentLabel = await Str.get_string('hide_legend', 'block_completion_monitor');
            this.$toggle.find('span').text(hideLegentLabel);
        }

        async _closeDesktop() {
            this.isOpen = false;
            this.$content.attr('hidden', true);
            this.$toggle
                .attr('aria-expanded', 'false')
                .find('.fa')
                .removeClass('fa-chevron-up')
                .addClass('fa-chevron-down');

            const showLegendLabel = await Str.get_string('show_legend', 'block_completion_monitor');
            this.$toggle.find('span').text(showLegendLabel);
        }

        async _openModal() {
            const title = await Str.get_string('legend', 'block_completion_monitor');
            const modal = await Modal.create({
                title: title,
                body: this.$content.html(),
                removeOnClose: true,
            });
            modal.getRoot().addClass('progressbar_legend-modal');
            modal.show();
        }
    }

    return Legend;
});