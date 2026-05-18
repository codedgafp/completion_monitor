define([
    'jquery',
    'block_completion_monitor/activity_list',
], function ($, ActivityList) {
    'use strict';

    class ProgressBar {

        constructor($el, activities) {
            this.$el = $el;
            this.$list = $el.find('.progressbar_list');
            this.$arrowLeft = $el.find('[data-action="scroll-left"]');
            this.$arrowRight = $el.find('[data-action="scroll-right"]');
            this.$liveRegion = $el.find('[role="status"]');
            this.activityList = new ActivityList(
                $el.find('.progressbar_list'),
                activities
            );

            this._handleMobileOrder();
            this._bindEvents();
            this._updateArrows();
        }

        _bindEvents() {
            this.$arrowLeft.on('click', () => this._scroll('left'));
            this.$arrowRight.on('click', () => this._scroll('right'));

            this.$arrowLeft.on('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this._scroll('left');
                }
            });

            this.$arrowRight.on('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this._scroll('right');
                }
            });

            this._bindSwipe();

            this.$el.on('progressbar:list:select', (e, vm) => {
                this.$el.trigger('progressbar:activity:selected', [vm]);
            });

            this.$el.on('focus', '.progressbar-item', (e) => {
                const $item = $(e.target);
                const viewport = this.$el.find('.progressbar_list-viewport')[0];
                const itemLeft = $item.position().left;
                const itemRight = itemLeft + $item.outerWidth();
                const viewportWidth = viewport.clientWidth;

                if (itemRight > viewportWidth) {
                    viewport.scrollLeft += itemRight - viewportWidth + 10;
                } else if (itemLeft < 0) {
                    viewport.scrollLeft += itemLeft - 10;
                }

                setTimeout(() => this._updateArrows(), 50);
            });

            const onResize = this._debounce(() => { 
                this._updateArrows();
                this._handleMobileOrder();
            }, 120);
            $(window).on('resize.progressbar', onResize);
        }

        _bindSwipe() {
            let startX = 0;

            this.$el[0].addEventListener('touchstart', (e) => {
                startX = e.touches[0].clientX;
            }, { passive: true });

            this.$el[0].addEventListener('touchend', (e) => {
                const delta = startX - e.changedTouches[0].clientX;
                if (Math.abs(delta) < 40) return;
                this._scroll(delta > 0 ? 'right' : 'left');
            }, { passive: true });
        }

        _scroll(direction) {
            const viewport = this.$el.find('.progressbar_list-viewport')[0];
            const scrollStep = viewport.clientWidth * 0.8;

            if (direction === 'left') {
                viewport.scrollLeft -= scrollStep;
            } else {
                viewport.scrollLeft += scrollStep;
            }

            setTimeout(() => this._updateArrows(), 50);
        }

        _updateArrows() {
            if (!this.activityList) return;

            const viewport = this.$el.find('.progressbar_list-viewport')[0];
            const overflow = this._hasOverflow();
            const atStart = !viewport || viewport.scrollLeft === 0;
            const atEnd = !viewport || viewport.scrollLeft + viewport.clientWidth >= viewport.scrollWidth - 1;

            this.$arrowLeft.toggleClass('hidden', !overflow || atStart);
            this.$arrowRight.toggleClass('hidden', !overflow || atEnd);
        }

        _handleMobileOrder() {
            const $bottomRow = this.$el.find('.progressbar_bottom-row');
            const $legend = this.$el.find('.progressbar_legend-container');
            const $detail = this.$el.find('.progressbar_detail-container');

            if ($(window).width() < 768) {
                $bottomRow.prepend($legend);
            } else {
                $bottomRow.append($legend);
            }
        }

        _hasOverflow() {
            const viewport = this.$el.find('.progressbar_list-viewport')[0];
            return viewport ? viewport.scrollWidth > viewport.clientWidth : false;
        }

        _debounce(fn, delay) {
            let timer;
            return (...args) => {
                clearTimeout(timer);
                timer = setTimeout(() => fn(...args), delay);
            };
        }

        updateActivity(activityId, updateDataList) {
            this.activityList.updateItem(activityId, updateDataList);
            this._updateArrows();
        }

        /**
         * Destroy the component and clean up listeners.
         */
        destroy() {
            $(window).off('resize.progressbar');
            this.$el.off('progressbar:list:select');
        }
    }

    return ProgressBar;
});