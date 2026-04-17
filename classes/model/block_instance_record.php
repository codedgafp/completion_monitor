<?php

/**
 * Class block_instance_record
 *
 * @package  block_completion_monitor
 */

namespace block_completion_monitor\model;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/completion_monitor/lib.php');

class block_instance_record
{

    private string $blockname;

    private int $parentcontextid;

    private int $showinsubcontexts;

    private string $pagetypepattern;

    private ?string $subpagepattern;

    private string $defaultregion;

    private int $defaultweight;

    private int $timecreated;

    private int $timemodified;


    public function __construct(
        string $blockname,
        int $parentcontextid,
        string $pagetypepattern,
        int $defaultweight = 1
    ) {
        global $CFG;

        $this->blockname = $blockname;
        $this->parentcontextid = $parentcontextid;
        $this->showinsubcontexts = 0;
        $this->pagetypepattern = $pagetypepattern;
        $this->subpagepattern = null;
        $this->defaultregion = ($CFG->blocktopregion) ?? BLOCK_POS_LEFT;
        $this->defaultweight = $defaultweight;
        $this->timecreated = time();
        $this->timemodified = time();
    }

    public function buildrecord(): \stdClass
    {
        return (object) [
            'blockname'         => $this->blockname,
            'parentcontextid'   => $this->parentcontextid,
            'showinsubcontexts' => $this->showinsubcontexts,
            'pagetypepattern'   => $this->pagetypepattern,
            'subpagepattern'    => $this->subpagepattern,
            'defaultregion'     => $this->defaultregion,
            'defaultweight'     => $this->defaultweight,
            'timecreated'       => $this->timecreated,
            'timemodified'      => $this->timemodified,
        ];
    }
}
