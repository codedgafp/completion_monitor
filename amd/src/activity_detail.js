define([
    'block_completion_monitor/utils/activity_utils',
    'core/templates',
    'core/str'
], function (Utils, Templates, Str) {
    'use strict';
    const { getColorClass, COMPONENT, ICON_MAP } = Utils;

    class ActivityDetail {

        /**
     * @param {jQuery}  $container
     * @param {number}  $courseid
     * @param {number}  $userid
     * @param {Object}  [options]
     * @param {boolean} [options.closeOnReselect=false]  If true, clicking the
     *   already-open item closes the panel (toggle behaviour). If false, the
     *   panel always stays open.
     */
        constructor($container, $courseid, $userid, { closeOnReselect = false } = {}) {
            this.$container = $container;
            this.activity = null;
            this.courseid = $courseid;
            this.userid = $userid;
            this.closeOnReselect = closeOnReselect;
        }

        /**
         * Build the badge label.
         * @param  {boolean} required
         * @param  {string}  status
         * @return {Promise<string>}
        */
        _getBadgeLabel = async (required, status) => {
            return Promise.all([
                Str.get_string(required ? 'required' : 'optional', COMPONENT),
                Str.get_string(status, COMPONENT),
            ]).then(([obligation, statusLabel]) => {
                obligation = obligation.charAt(0).toUpperCase() + obligation.slice(1);
                statusLabel = statusLabel.charAt(0).toUpperCase() + statusLabel.slice(1);
                return `${obligation} - ${statusLabel}`;
            });
        };

        _applyBadgeIcon() {
            const badgeIconName = ICON_MAP[this.activity?.status];
            if (!badgeIconName)
                return;

            this.$container.find('.progressbar_detail-badge').prepend(
                $('<i>', {
                    class: `fa fa-${badgeIconName}`,
                    'aria-hidden': 'true',
                })
            );
        }

        async _render(vm) {
            const context = {
                name: vm.name,
                type: vm.type,
                url: vm.url,
                issectionurl: vm.issectionurl,
                opennewtab: vm.opennewtab,
                status: vm.status,
                is_required: vm.required,
                icon: vm.icon,
                badge_label: await this._getBadgeLabel(vm.required, vm.status),
                badge_color_class: getColorClass(vm.status, vm.required),
                completionconditions: typeof vm.completionconditions === 'string'
                    ? JSON.parse(vm.completionconditions || '[]')
                    : (vm.completionconditions || []),
            };
            this.$container.html(
                await Templates.render(
                    'block_completion_monitor/progress_activity_detail/activity_detail',
                    context
                )
            );
        
            this._applyBadgeIcon();

            if(vm.opennewtab) 
                this.$container.find('.progressbar_detail-link').attr('target', '_blank');
                this.$container.find('.progressbar_detail-link').attr('tabindex', '0');

            this._bindDetailTabTrap();
            this._notifyItem(this.activity, true);

            sessionStorage.setItem('block_completion_monitor_' + this.userid + '_' + this.courseid + '_activity_detail_openned', this.activity.id);
        }

        _bindDetailTabTrap() {
            const $link = this.$container.find('.progressbar_detail-link');
            if (!$link.length) return;

            $link.off('keydown.detailtab').on('keydown.detailtab', (e) => {
                if (e.key !== 'Tab' || e.shiftKey) return;

                const $currentItem = this.activity?.el;
                if (!$currentItem) return;

                const $allItems = $currentItem.closest('.progressbar_list').find('.progressbar-item');
                const currentIndex = $allItems.index($currentItem);
                const $nextItem = $allItems.eq(currentIndex + 1);

                if ($nextItem.length) {
                    e.preventDefault();
                    $nextItem.trigger('focus');
                }
            });
        }
        _notifyItem(id, isOpen) {
            this.activity?.el.trigger(isOpen ? 'progressbar:detail:opened' : 'progressbar:detail:closed', [id]);
        }

        /**
         * Show activity detail.
         * If it's the same activity, close the panel.
         *
         * @param {Object} vm - ViewModel of the activity
         */
        toggle(vm) {
            if (this.activity?.id === vm.id) {
                if (this.closeOnReselect) {
                    this.close();
                }
                return;
            }

            if (this.activity !== null) {
                this._notifyItem(this.activity, false);
            }

            this.activity = vm;
            this._render(vm);
        }

        updateDetail(activity) {
            if (!this.activity || this.activity.id !== activity.id) return;
            this._render(activity);
        }

        close() {
            this._notifyItem(this.activity, false);
            this.$container.find('.progressbar_detail-link').removeAttr('target').off('keydown.detailtab');
            this.activity = null;
            this.$container.empty();
        }
    }

    return ActivityDetail;
});