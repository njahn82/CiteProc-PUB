<?php

foreach ($argv as $value){
	$val_array = split("=",$value);
	if($val_array[0] == 'input'){
		$input['input'] = $val_array[1];
	} elseif ($val_array[0] == 'style'){
		$input['style'] = $val_array[1];
	} elseif ($val_array[0] == 'format'){
		$input['format'] = $val_array[1];
	}
}

print_r($input);


?>