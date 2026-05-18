define(['jquery', 'block_completion_monitor/activity_item'], function ($, ActivityItem) {
    'use strict';

    class ActivityList {

        $el;
        items = new Map();

        constructor($el) {
            this.$el = $el;
            this.items = new Map();
            this._init();
        }

        _init() {
            let $items = this.$el.find('.progressbar-item');
            $items.each((index, el) => {
                const $el = $(el);
                const id = $el.data('id');
                this.items.set(id, new ActivityItem($el));

                if (index < $items.length - 1)
                    $el.after('<span class="progressbar_separator" aria-hidden="true"></span>');
            });

            this._bindEvents();
        }

        _showArrows(e) {
            if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') {
                return;
            }

            e.preventDefault();

            const $items = this.$el.find('.progressbar-item');
            const current = $items.index($(e.currentTarget));
            const next = e.key === 'ArrowRight' ? current + 1 : current - 1;
            const $target = $items.eq(next);

            if ($target.length) {
                $target.trigger('focus');
            }
        }

        _bindEvents() {
            this.$el.on('progressbar:item:select', (e, vm) => {
                this.$el.trigger('progressbar:list:select', [vm]);
            });

            this.$el.on('keydown', '.progressbar-item', (e) => {
                this._showArrows(e);
            });
            
            this.$el.on('scroll', '.progressbar-item', (e) => {
                this._showArrows(e);
            });
        }

        /**
         * Update activity status by activity id.
         *
         * @param {number} activityId
         * @param {object} updateDataList
         */
        updateItem(activityId, updateDataList) {
            const item = this.items.get(activityId);
            item?.update(updateDataList);
        }

        /**
         * Return items count.
         * 
         * @return {number}
         */
        get count() {
            return this.items.size;
        }
    }
    return ActivityList;
});