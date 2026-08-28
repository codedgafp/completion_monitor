<?php

namespace block_completion_monitor\output;

use core\output\datafilter;
use block_completion_monitor\table\progress_overview_filterset;

class progress_overview_filter extends datafilter
{
    /**
     * Get data for all filter types.
     *
     * @return array
     */
    public function get_filtertypes(): array
    {
        $filtertypes = [];

        $filtertypes[] = $this->get_keywords_filter();

        if ($rolefiltertype = $this->get_roles_filter()) {
            $filtertypes[] = $rolefiltertype;
        }

        if ($groupfiltertype = $this->get_groups_filter()) {
            $filtertypes[] = $groupfiltertype;
        }

        if ($groupingfiltertype = $this->get_groupings_filter()) {
            $filtertypes[] = $groupingfiltertype;
        }

        return $filtertypes;
    }

    public function get_keywords_filter(): ?\stdClass
    {
        return $this->get_filter_object(
            progress_overview_filterset::KEYWORDS,
            get_string('filterbykeyword', 'core_user'),
            true,
            true,
            'core/datafilter/filtertypes/keyword',
            [],
            true
        );
    }

    /**
     * Get all enrolled users roles from the cours
     * 
     * @return \stdClass|null
     */
    public function get_roles_filter(): ?\stdClass
    {
        $roles = [];

        $roles += [-1 => get_string('noroles', 'role')];
        $roles += array_map(fn($role) => $role->name, get_roles_used_in_context($this->context));

        $roleskey = array_keys($roles);
        $rolesname = array_values($roles);

        return $this->get_filter_object(
            progress_overview_filterset::ROLES,
            get_string('roles', 'core_role'),
            false,
            true,
            null,
            array_map(fn($id, $name) => (object) ['value' => $id, 'title' => $name], $roleskey, $rolesname)
        );
    }

    /**
     * Get all groups from the course
     * 
     * @return \stdClass|null
     */
    public function get_groups_filter(): ?\stdClass
    {
        $groupsfromcourse = groups_get_all_groups($this->course->id);

        if (empty($groupsfromcourse)) {
            return null;
        }

        $groups = [];
        $groups += [USERSWITHOUTGROUP => get_string('nogroup', 'group')];
        $groups += array_map(fn($group) => $group->name, $groupsfromcourse);

        $groupkey = array_keys($groups);
        $groupvalue = array_values($groups);

        return $this->get_filter_object(
            progress_overview_filterset::GROUPS,
            get_string('groups', 'core_group'),
            false,
            true,
            null,
            array_map(fn($id, $name) => (object) ['value' => $id, 'title' => $name], $groupkey, $groupvalue)
        );
    }

    /**
     * Get all groupings from the course
     * 
     * @return \stdClass|null
     */
    public function get_groupings_filter(): ?\stdClass
    {
        $groupingsfromcourse = groups_get_all_groupings($this->course->id);

        if (empty($groupingsfromcourse)) {
            return null;
        }

        $groupings = [];
        $groupings += [USERSWITHOUTGROUP => get_string('nogrouping', 'group')];
        $groupings += array_map(fn($grouping) => $grouping->name, $groupingsfromcourse);

        $groupingkey = array_keys($groupings);
        $groupingvalue = array_values($groupings);

        return $this->get_filter_object(
            progress_overview_filterset::GROUPINGS,
            get_string('groupings', 'core_group'),
            false,
            true,
            null,
            array_map(fn($id, $name) => (object) ['value' => $id, 'title' => $name], $groupingkey, $groupingvalue)
        );
    }

    /**
     * Export the renderer data in a mustache template friendly format.
     *
     * @param \renderer_base $output Unused.
     * @return \stdClass Data in a format compatible with a mustache template.
     */
    public function export_for_template(\renderer_base $output): \stdClass
    {
        $export = new \stdClass;

        $export->tableregionid = $this->tableregionid;
        $export->courseid = $this->context->instanceid;
        $export->filtertypes = $this->get_filtertypes();
        $export->rownumber = 1;

        return $export;
    }
}
