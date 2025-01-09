<?php
use Psr\Http\Message\ResponseInterface as Response;

function toJSON($data) {
	return json_encode($data, JSON_PRETTY_PRINT);
}


function stringToISO8601($value) {
	if (!$value) {
		return $value;
	}
	return (new DateTime($value))->format(DateTime::ISO8601);
}

function throwHttpError(Response $response, $statusCode, $errorText) {
	if ($errorText) {
		$response->getBody()->write(toJSON(['error' => $errorText]));
		return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
	} else {
		return $response->withStatus($statusCode);
	}
}
?>