define([
    'jquery',
    'block_completion_monitor/activity_item'
], function ($, ActivityItem) {
    'use strict';

    class ActivityList {
        constructor($el, $courseid, $userid) {
            this.$el = $el;
            this.items = new Map();

            this.courseid = $courseid;
            this.userid = $userid;

            this._init();

            this.activityToDisplay = this.getLastActivityDetail(); 
        }

        _init() {
            let $items = this.$el.find('.progressbar-item');
            this.itemToDisplay = null;

            $items.each((index, el) => {
                const $el = $(el);

                if (this.itemToDisplay === null) {
                    this.itemToDisplay = $el;
                }

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
         * Get the last activity detail the user oppened.
         * If is null, get the first activity from the progress bar. 
         */
        getLastActivityDetail() {
            let itemToDisplayId = sessionStorage.getItem('block_completion_monitor_' + this.userid + '_' + this.courseid + '_activity_detail_openned');

            if (itemToDisplayId !== null) {
                return this.items.get(parseInt(itemToDisplayId)).$el;
            }

            return this.itemToDisplay;
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