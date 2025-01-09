<?php
$bookSqlColumns = 'isbn, title, subtitle, description, authors, imageUrl, createdAt';

function toBook($data) {
	$data['createdAt'] = stringToISO8601($data['createdAt']);
	$data['authors'] = json_decode($data['authors']);
	
	if (!$data['subtitle']) {
		unset($data['subtitle']);
	}
	
	return $data;
}

function isbnExists($mysqli, $isbn) {
	$stmt = $mysqli->prepare('SELECT COUNT(*) as cnt FROM books WHERE isbn = ?');
 	$stmt->bind_param('s', $isbn);
 	$stmt->execute();
	$result = $stmt->get_result()->fetch_array(MYSQLI_ASSOC);
	return $result['cnt'] != 0;
}

function getBookByISBN($mysqli, $isbn) {
	global $bookSqlColumns;
	$stmt = $mysqli->prepare('SELECT ' . $bookSqlColumns . ' FROM books WHERE isbn = ? LIMIT 1');
	$stmt->bind_param('s', $isbn);
	$stmt->execute();
	$result = $stmt->get_result();
	
	return $result->fetch_array(MYSQLI_ASSOC);
}


function createBook($mysqli, $book) {
	$authors = json_encode($book->authors);
	
	$stmt = $mysqli->prepare('INSERT INTO books (isbn, title, subtitle, description, authors, imageUrl, createdAt) VALUES (?, ?, ?, ?, ?, ?, ?)');
	$stmt->bind_param('sssssss', $book->isbn, $book->title, $book->subtitle, $book->description, $authors, $book->imageUrl, $book->createdAt);
	return $stmt->execute();
}
?>