<?php
function toJSON($data) {
	return json_encode($data, JSON_PRETTY_PRINT);
}


function stringToISO8601($value) {
	if (!$value) {
		return $value;
	}
	return (new DateTime($value))->format(DateTime::ISO8601);
}
?>