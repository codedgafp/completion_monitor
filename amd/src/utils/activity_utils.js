define([], function () {
    'use strict';

    const COMPONENT = 'block_completion_monitor';

    const SERVER_KEYS = {
        not_started: 'not_started',
        in_progress: 'in_progress',
        completed: 'completed',
        locked: 'locked',
        required: 'required',
        optional: 'optional',
    }

    const STATUS = {
        [SERVER_KEYS.not_started]: 'notstarted',
        [SERVER_KEYS.in_progress]: 'inprogress',
        [SERVER_KEYS.completed]: 'completed',
        [SERVER_KEYS.locked]: 'locked',
    };

    const COLOR_CLASS = 'completion_monitor-color';

    const SHAPE_MAP = {
        [SERVER_KEYS.required]: 'square',
        [SERVER_KEYS.optional]: 'circle',
    };
    
    const I18N_MAP = {
        [SERVER_KEYS.required]: 'required_activity',
        [SERVER_KEYS.optional]: 'optional_activity',
        [SERVER_KEYS.not_started]: 'not_started',
        [SERVER_KEYS.in_progress]: 'in_progress',
        [SERVER_KEYS.completed]: 'completed',
        [SERVER_KEYS.locked]: 'locked',
    };

    const ICON_MAP = {
        [SERVER_KEYS.not_started]: 'square',
        [SERVER_KEYS.in_progress]: 'play',
        [SERVER_KEYS.completed]: 'check',
        [SERVER_KEYS.locked]: 'lock',
    };

    const getColorClass = (status, required) => {
        if (!required)
            return `${COLOR_CLASS}-optional`;
        return `${COLOR_CLASS}-${STATUS[status]}`;
    }

    const resolveStatus = (raw) => {
        if (raw.availabilityRestricted) return STATUS.locked;
        if (raw.completionState === 'complete') return STATUS.completed;
        if (raw.hasStarted) return STATUS.in_progress;
        return STATUS.not_started;
    };

    /**
     * Map Object array from Moodle (PHP/AJAX) as ViewModels.
     *
     * @param  {Array}  rawActivities
     * @return {Array}
     */
    const map = (rawActivities) => {
        return rawActivities.reduce((results, raw) => {
            const status = resolveStatus(raw);

            results.push({
                id: raw.id,
                name: raw.name,
                url: raw.url,
                status,
                isRequired: raw.isRequired ?? false,
                isHidden: raw.availabilityHidden ?? false,
            });

            return results;
        }, []);
    };
    return { map, getColorClass, COMPONENT, STATUS, SERVER_KEYS, I18N_MAP, COLOR_CLASS, SHAPE_MAP, ICON_MAP };
});