define(['jquery', 'block_completion_monitor/utils/activity_utils', 'core/str', 'core/ajax'], function ($, Utils, Str, Ajax) {
    'use strict';
    const { getColorClass, I18N_MAP, COMPONENT, ICON_MAP, SHAPE_MAP, SERVER_KEYS } = Utils;

    class ActivityItem {

        constructor($el) {
            this.$el = $el;
            this.status = $el.data('status');
            this.required = $el.data('required') === 1;
            this.issectionurl = $el.data('issectionurl') == 1;
            this._init();
        }

        async _init() {
            await this._applyAriaLabel();
            this._applyClasses();
            this._applyIcon();
            this._bindEvents();
        }

        _applyClasses() {
            const shape = SHAPE_MAP[this.required ? SERVER_KEYS.required : SERVER_KEYS.optional];
            const colorClass = getColorClass(this.status, this.required);

            this.$el
                .addClass(`progressbar-item-${shape}`)
                .addClass(colorClass);
        }

        _applyIcon() {
            const iconName = ICON_MAP[this.status];
            if (!iconName)
                return;

            this.$el.append(
                $('<i>', {
                    class: `fa fa-${iconName}`,
                    'aria-hidden': 'true',
                })
            );
        }

        async _applyAriaLabel() {
            const obligationKey = this.required ? SERVER_KEYS.required : SERVER_KEYS.optional;
            return Promise.all([
                Str.get_string(I18N_MAP[obligationKey], COMPONENT),
                Str.get_string(I18N_MAP[this.status], COMPONENT),
            ]).then(([obligation, statusLabel]) => {
                obligation = obligation.charAt(0).toUpperCase() + obligation.slice(1);
                statusLabel = statusLabel.charAt(0).toUpperCase() + statusLabel.slice(1);
                this.$el.attr('aria-label', `${obligation}, ${statusLabel}`);
            });
        }

        _fetchConditions() {
            const courseid = parseInt(this.$el.attr('data-courseid'), 10);
            const userid = parseInt(this.$el.attr('data-userid'), 10);
            const cmid = parseInt(this.$el.data('id'), 10);

            if (!courseid || !userid || !cmid) {
                return Promise.resolve([]);
            }

            return Ajax.call([{
                methodname: 'block_completion_monitor_get_completion_conditions',
                args: {courseid: courseid, userid: userid, cmid: cmid},
            }])[0].then(response => response.conditions).catch(() => []);
        }

        _resolveConditions() {
            if (this.completionconditions) {
                return Promise.resolve(this.completionconditions);
            }

            const raw = this.$el.attr('data-conditions');

            if (typeof raw === 'string' && raw !== '') {
                this.completionconditions = JSON.parse(raw || '[]');
                return Promise.resolve(this.completionconditions);
            }

            return this._fetchConditions().then(conditions => {
                this.completionconditions = conditions;
                return conditions;
            });
        }

        _bindEvents() {
            this.$el.on('click', (e) => {
                this._resolveConditions().then(() => this._triggerSelect()).catch(() => this._triggerSelect());
            });

            this.$el.on('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.$el.trigger('click');
                }
            });
        }

        _triggerSelect() {
            this.$el.trigger('progressbar:item:select', [{
                    id: this.$el.data('id'),
                    name: this.$el.data('name'),
                    type: this.$el.data('type'),
                    url: this.$el.data('url'),
                    issectionurl: this.issectionurl,
                    opennewtab: this.$el.data('opennewtab') === 1,
                    icon: this.$el.data('icon'),
                    status: this.status,
                    required: this.required,
                    completionconditions: this.completionconditions || [],
                    el: this.$el,
                }]);
            this.isActive = false;
            this.$el.on('progressbar:detail:opened', () => {
                this.isActive = true;
                this.$el.attr('aria-expanded', 'true');
                this.$el.attr('aria-current', 'true');
                this.$el.attr('aria-controls', 'progressbar_detail');
            });

            this.$el.on('progressbar:detail:closed', () => {
                this.isActive = false;
                this.$el.attr('aria-expanded', 'false');
                this.$el.removeAttr('aria-current');
                this.$el.removeAttr('aria-controls');
            });
        }

        /**
         * Update item on status update.
         * @param {object} updateDataList
         */
        update(updateDataList) {
            this.$el.removeClass(getColorClass(this.status, this.required));
            this.$el.find('.fa').remove();

            this.status = updateDataList.status;
            this.completionconditions = updateDataList.completionConditions;
            this._applyClasses();
            this._applyIcon();
        }
    }
    return ActivityItem;
});