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

    public string $blockname;
    public int $parentcontextid;
    public int $showinsubcontexts;
    public string $pagetypepattern;
    public ?string $subpagepattern;
    public string $defaultregion;
    public int $defaultweight;
    public int $timecreated;
    public int $timemodified;


    public function __construct(
        string $blockname,
        int $parentcontextid,
        string $pagetypepattern,
        string $defaultregion = BLOCK_POS_TOP,
        int $defaultweight = 1
    ) {

        $this->blockname = $blockname;
        $this->parentcontextid = $parentcontextid;
        $this->showinsubcontexts = 0;
        $this->pagetypepattern = $pagetypepattern;
        $this->subpagepattern = null;
        $this->defaultregion = $defaultregion;
        $this->defaultweight = $defaultweight;
        $this->timecreated = time();
        $this->timemodified = time();
    }

    public function buildRecord(): \stdClass
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
