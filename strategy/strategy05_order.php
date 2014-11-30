<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Internal library of functions for module ratingallocate
 *
 * All the ratingallocate specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package mod_ratingallocate
 * @copyright 2014 M Schulze
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// namespace is mandatory!
namespace ratingallocate\strategy_order;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
require_once(dirname(__FILE__) . '/../locallib.php');
require_once(dirname(__FILE__) . '/strategy_template.php');

class strategy extends \strategytemplate {

    const STRATEGYID = 'strategy_order';
    const COUNTOPTIONS = 'countoptions';


    public function get_strategyid() {
        return self::STRATEGYID;
    }

    public function get_static_settingfields() {
        return array(
            self::COUNTOPTIONS => array(// wie viele Felder es gibt
                'int',
                get_string(self::STRATEGYID . '_setting_countoptions', ratingallocate_MOD_NAME), 
                $this->get_settings_value(self::COUNTOPTIONS)
            )
        );
    }
    
    public function get_dynamic_settingfields(){
        return array();
    }
    
    public function get_default_settings(){
        return array(
                        self::COUNTOPTIONS => 2
        );
    }
    
    protected function getValidationInfo(){
        return array(self::COUNTOPTIONS => array(true,1)
        );
    }

}

// register with the strategymanager
\strategymanager::add_strategy(strategy::STRATEGYID);

/**
 * _Users view_
 * For every group for which the user can give a rating:
 * - shows the groups name and description
 * - shows a drop down menu from which the user can choose a rating
 */
class mod_ratingallocate_view_form extends \ratingallocate_strategyform {
    
    protected function construct_strategy($strategyoptions){
        return new strategy($strategyoptions);
    }
    
    public function definition() {
        global $USER;
        parent::definition();
        $mform = $this->_form;

        $ratingdata = $this->ratingallocate->get_rating_data_for_user($USER->id);

        $choicecounter = $this->get_strategysetting(strategy::COUNTOPTIONS);
        $choices = array();

        foreach ($ratingdata as $data) {
            $choices[$data->choiceid] = $data->title;
        }

        for ($i = 1; $i <= $choicecounter; $i++) {
            $mform->addElement('select', 'choice[' . $i . ']', get_string(strategy::STRATEGYID . '_no_choice', ratingallocate_MOD_NAME, $i), $choices);
        }
        foreach ($ratingdata as $data) {
            // If there is a valid value in the databse, choose the according rating
            // from the dropdown.
            // Else use a default value.
            if (is_numeric($data->rating) && $data->rating >= 0 && $mform->elementExists('choice[' . ($choicecounter - ($data->rating - 1)) . ']')) {
                $mform->getElement('choice[' . ($choicecounter - ($data->rating - 1)) . ']')->setSelected($data->choiceid);
            }
        }
    }

    public function describe_strategy() {
        return get_string(strategy::STRATEGYID . '_explain_choices', ratingallocate_MOD_NAME);
    }

    /**
     * Override to fill with correct data format
     * @return type
     */
    public function get_data() {
        $data = parent::get_data();
        $data->data = array();

        if (isset($data->choice)) {
            // we do assign the highest rating to choice no.1
            $maxrating = count($data->choice);
            foreach ($data->choice as $prio => $curchoice) {
                $data->data[$curchoice]['rating'] = $maxrating - ($prio - 1);
                $data->data[$curchoice]['choiceid'] = $curchoice;
            }
        }
        return $data;
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $usedchoices = array();

        // no data exists, so skip
        if (!array_key_exists('choice', $data)) {
            return $errors;
        }

        foreach ($data['choice'] as $choiceid => $choice) {
            if (array_key_exists($choice, $usedchoices)) {
                $errors['choice[' . $choiceid . ']'] = get_string(strategy::STRATEGYID . '_use_only_once', ratingallocate_MOD_NAME);
            }
            $usedchoices[$choice] = true;
        }
        return $errors;
    }

}
