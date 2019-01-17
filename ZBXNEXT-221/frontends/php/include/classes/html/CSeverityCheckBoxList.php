<?php

class CSeverityCheckBoxList extends CCheckBoxList {

	public function __construct($name, $checked_values = []) {
		parent::__construct($name, $checked_values);

		$this->addCheckBox(_('Not classified'), TRIGGER_SEVERITY_NOT_CLASSIFIED);
		$this->addCheckBox(_('Information'), TRIGGER_SEVERITY_INFORMATION);
		$this->addCheckBox(_('Warning'), TRIGGER_SEVERITY_WARNING);
		$this->addCheckBox(_('Average'), TRIGGER_SEVERITY_AVERAGE);
		$this->addCheckBox(_('High'), TRIGGER_SEVERITY_HIGH);
		$this->addCheckBox(_('Disaster'), TRIGGER_SEVERITY_DISASTER);
	}

}

