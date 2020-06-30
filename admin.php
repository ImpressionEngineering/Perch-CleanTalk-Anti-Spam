<?php

if ($CurrentUser->logged_in()) {
    $this->register_app('impeng_cleantalk', 'CleanTalk Anti Spam', 50, 'CleanTalk anti spam for Perch Forms', '0.1', true); 
    $this->require_version('impeng_cleantalk', '3.0');

    $API  = new PerchAPI(1.0, 'impeng_cleantalk');
	$Lang = $API->get('Lang');

    spl_autoload_register(function($class_name){
		if (strpos($class_name, 'PerchForms_')===0) {
	        include(PERCH_PATH.'/addons/apps/perch_forms/'.$class_name.'.class.php');
	        return true;
	    }
	    return false;
	});

	// Setting for CleanTalk access key
    $this->add_setting('impeng_cleantalk_auth_key', 'CleanTalk Access Key', 'text', '');

	// Add setting to enable CleanTalk for each form on this site
	if (class_exists('PerchForms_Forms')) {
		$Forms = new PerchForms_Forms($API);
		$forms = $Forms->all();
		foreach($forms as $Form) {
			if (json_decode($Form->formOptions())->store == 1) {
				$this->add_setting('impeng_cleantalk_activate_form_'.$Form->formKey(), $Form->formTitle().$Lang->get(' - Activate CleanTalk anti spam?'), 'checkbox', 0);
			}
		}
	} else {
		error_log("ImpEng Cleantalk requires Perch Forms to be installed");
	} 
}