
<?php
defined('MOODLE_INTERNAL') || die();
class block_completion_monitor extends block_base {
    /**
     * Set the block title.
     *
     * @throws coding_exception
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_completion_monitor');
    }

    
    public function instance_allow_config() {
        return true;
    }




    public function get_content(){

        if ($this->content !== null) return $this->content;
        $renderer = $this->page->get_renderer('block_completion_monitor');
        $html = $renderer->render_block();

        $this->content = (object)['text'=>$html,'footer'=>''];
        return $this->content;
    }
}