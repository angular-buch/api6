<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once('mysql.php');
require_once('utils.php');

/*************************************************/

function toBook($data) {
	$data['createdAt'] = stringToISO8601($data['createdAt']);
	$data['authors'] = json_decode($data['authors']);
	
	if ($data['subtitle'] == NULL) {
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
	$stmt = $mysqli->prepare('SELECT isbn, title, subtitle, description, authors, imageUrl, createdAt FROM books WHERE isbn = ? LIMIT 1');
	$stmt->bind_param('s', $isbn);
	$stmt->execute();
	$result = $stmt->get_result();
	
	return $result->fetch_array(MYSQLI_ASSOC);
}

function throwHttpError($response, $statusCode, $errorText) {
	if ($errorText) {
		$response->getBody()->write(toJSON(['error' => $errorText]));
		return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
	} else {
		return $response->withStatus($statusCode);
	}
}


function createBook($mysqli, $book) {
	$authors = json_encode($book->authors);
	
	$stmt = $mysqli->prepare('INSERT INTO books (isbn, title, subtitle, description, authors, imageUrl, createdAt) VALUES (?, ?, ?, ?, ?, ?, ?)');
	$stmt->bind_param('sssssss', $book->isbn, $book->title, $book->subtitle, $book->description, $authors, $book->imageUrl, $book->createdAt);
	return $stmt->execute();
}


/*************************************************/

$app = AppFactory::create();

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});


$app->get('/', function (Request $request, Response $response, $args) {
	$indexPage = file_get_contents('indexpage.html');
	$response->getBody()->write($indexPage);
	return $response->withStatus(200);
});


/** RESET BOOK LIST */
$app->delete('/books', function (Request $request, Response $response, $args) {
	global $mysqli;
	$defaultBooks = json_decode(file_get_contents('defaultbooks.json'));
	
	$stmt = $mysqli->prepare('DELETE FROM books');
	$stmt->execute();	

	foreach ($defaultBooks as $book) {
		createBook($mysqli, $book);
	}
	
	return $response->withStatus(200);
});


/** GET BOOK LIST */
$app->get('/books', function (Request $request, Response $response, $args) {
	global $mysqli;
	$stmt = $mysqli->prepare('SELECT isbn, title, subtitle, description, authors, imageUrl, createdAt FROM books ORDER BY createdAt DESC');
	$stmt->execute();
	$booksRaw = $stmt->get_result();
		
	$books = [];
	while ($bookRaw = $booksRaw->fetch_array(MYSQLI_ASSOC)) {
		$books[] = toBook($bookRaw);	
	}
	
	$response->getBody()->write(toJSON($books));
	return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(200);
});


/** GET SINGLE BOOK */
$app->get('/books/{isbn}', function (Request $request, Response $response, $args) {
	global $mysqli;

	$isbn = $args['isbn'];
	$book = getBookByISBN($mysqli, $isbn);
		
	if (!$book) {
		return $response->withStatus(404);
	}
	
	$response->getBody()->write(toJSON(toBook($book)));
	return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(200);
});


/** DELETE BOOK */
$app->delete('/books/{isbn}', function (Request $request, Response $response, $args) {
	global $mysqli;
	$isbn = $args['isbn'];
	if (!isbnExists($mysqli, $isbn)) {
		return $response->withStatus(404);
	}
	
	$stmt = $mysqli->prepare('DELETE FROM books WHERE isbn = ?');
	$stmt->bind_param('s', $isbn);
	$stmt->execute();
	
	return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(204);
});


/** UPDATE BOOK */
$app->put('/books/{isbn}', function (Request $request, Response $response, $args) {
	global $mysqli;
	$body = $request->getBody()->getContents();
	$book = json_decode($body);
	$isbn = $args['isbn'];

	// check whether book exists
	if (!isbnExists($mysqli, $isbn)) {
		return $response->withStatus(404);
	}

	// book validation
	if ($book->isbn != $isbn) {
		return throwHttpError($response, 400, 'ISBN must match ISBN from URL');
	}
	
	if (!is_string($book->title)) {
		return throwHttpError($response, 400, 'Title must be string');
	}
	
	if (strlen($book->isbn) > 255) {
		return throwHttpError($response, 400, 'Title has a maximum length of 255');
	}
	
	if ($book->subtitle AND !is_string($book->subtitle)) {
		return throwHttpError($response, 400, 'Subtitle must be string');
	}
	
	if (strlen($book->isbn) > 255) {
		return throwHttpError($response, 400, 'Subtitle has a maximum length of 255');
	}
	
	if (!is_string($book->description)) {
		return throwHttpError($response, 400, 'Description must be string');
	}
	
	if (!is_string($book->imageUrl)) {
		return throwHttpError($response, 400, 'Image URL must be string');
	}
	
	if (!is_string($book->createdAt)) {
		return throwHttpError($response, 400, 'createdAt must be ISO8601 date string');
	}
	
	if (strlen($book->subtitle) > 255) {
		return throwHttpError($response, 400, 'Image URL has a maximum length of 255');
	}
	
	if (!is_array($book->authors)) {
		return throwHttpError($response, 400, 'Authors must be an array of strings');
	}

	
	// update in DB
	$authors = json_encode($book->authors);
	$stmt = $mysqli->prepare('UPDATE books SET title = ?, subtitle = ?, description = ?, authors = ?, imageUrl = ?, createdAt = ? WHERE isbn = ?');
	$stmt->bind_param('sssssss', $book->title, $book->subtitle, $book->description, $authors, $book->imageUrl, $book->createdAt, $isbn);
	$stmt->execute();
	
	// return book from DB
	$bookFromDB = getBookByISBN($mysqli, $isbn);
	if (!$bookFromDB) {
		return $response->withStatus(500);
	}
	
	$response->getBody()->write(toJSON(toBook($bookFromDB)));
	return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(201);
});


/** CREATE BOOK */
$app->post('/books', function (Request $request, Response $response, $args) {
	global $mysqli;
	$body = $request->getBody()->getContents();
	$book = json_decode($body);
	
	// book validation
	if (!is_string($book->isbn)) {
		return throwHttpError($response, 400, 'ISBN must be string');
	}
	
	if (strlen($book->isbn) > 40) {
		return throwHttpError($response, 400, 'ISBN has a maximum length of 40');
	}
	
	if (!is_string($book->title)) {
		return throwHttpError($response, 400, 'Title must be string');
	}
	
	if (strlen($book->isbn) > 255) {
		return throwHttpError($response, 400, 'Title has a maximum length of 255');
	}
	
	if ($book->subtitle AND !is_string($book->subtitle)) {
		return throwHttpError($response, 400, 'Subtitle must be string');
	}
	
	if (strlen($book->isbn) > 255) {
		return throwHttpError($response, 400, 'Subtitle has a maximum length of 255');
	}
	
	if (!is_string($book->description)) {
		return throwHttpError($response, 400, 'Description must be string');
	}
	
	if (!is_string($book->imageUrl)) {
		return throwHttpError($response, 400, 'Image URL must be string');
	}
	
	if (strlen($book->subtitle) > 255) {
		return throwHttpError($response, 400, 'Image URL has a maximum length of 255');
	}
	
	if (!is_string($book->createdAt)) {
		return throwHttpError($response, 400, 'createdAt must be ISO8601 date string');
	}
	
	if (!is_array($book->authors)) {
		return throwHttpError($response, 400, 'Authors must be an array of strings');
	}
	
	if (isbnExists($mysqli, $book->isbn)) {
		return throwHttpError($response, 409, 'ISBN already exists');
	}

	// create book in DB
	createBook($mysqli, $book);
	
	// return book from DB
	$bookFromDB = getBookByISBN($mysqli, $book->isbn);
	if (!$bookFromDB) {
		return $response->withStatus(500);
	}
	
	$response->getBody()->write(toJSON(toBook($bookFromDB)));
	return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(201);
});

$app->run();
?>
