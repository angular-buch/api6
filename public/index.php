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
require_once('books-utils.php');

/*************************************************/



function validateBook($book) {
	/** ISBN */
	if (!property_exists($book, 'isbn') OR !$book->isbn) {
		return 'ISBN must not be empty';
	}

	if (!is_string($book->isbn)) {
		return 'ISBN must be a string';
	}

	if (strlen($book->isbn) < 10) {
		return 'ISBN has a maximum length of 10';
	}

	if (strlen($book->isbn) > 20) {
		return 'ISBN has a maximum length of 20';
	}


	/** TITLE */
	if (!property_exists($book, 'title') OR !$book->title) {
		return 'Title must not be empty';
	}
	
	if (!is_string($book->title)) {
		return 'Title must be a string';
	}
	
	if (strlen($book->title) > 255) {
		return 'Title has a maximum length of 255';
	}


	/** SUBTITLE */
	if (property_exists($book, 'subtitle') AND $book->subtitle AND !is_string($book->subtitle)) {
		return 'Subtitle must be a string';
	}
	
	if (property_exists($book, 'subtitle') AND strlen($book->subtitle) > 255) {
		return 'Subtitle has a maximum length of 255';
	}
	

	/** DESCRIPTION */
	if (!property_exists($book, 'description') OR !$book->description) {
		return 'Description must not be empty';
	}
	
	if (!is_string($book->description)) {
		return 'Description must be a string';
	}
	
	if (strlen($book->description) > 10000) {
		return 'Description has a maximum length of 10000';
	}


	/** IMAGEURL */
	if (!property_exists($book, 'imageUrl') OR !$book->imageUrl) {
		return 'Image URL must not be empty';
	}

	if (!is_string($book->imageUrl)) {
		return 'Image URL must be string';
	}

	if (strlen($book->imageUrl) > 255) {
		return 'Image URL has a maximum length of 255';
	}


	/** CREATEDAT */
	if (!property_exists($book, 'createdAt') OR !is_string($book->createdAt)) {
		return 'createdAt must be ISO8601 date string';
	}
	
	if (strlen($book->createdAt) > 255) {
		return 'createdAt has a maximum length of 255';
	}


	/** AUTHORS */
	if (!property_exists($book, 'authors') OR !is_array($book->authors)) {
		return 'Authors must be an array';
	}

	if (count($book->authors) < 1) {
		return 'Authors has a minimum length of 1';
	}

	if (count($book->authors) > 100) {
		return 'Authors has a maximum length of 100';
	}

	foreach ($book->authors as $author) {
		if (!is_string($author)) {
			return 'Authors must be an array of strings';
		}
		
		if (strlen($author) > 255) {
			return 'Each author has a maximum length of 255';
		}
	}
}


/*************************************************/

$app = AppFactory::create();

$app->options('/{routes:.+}', function (Request $request, Response $response, $args) {
    return $response;
});

$app->add(function (Request $request, $handler) {
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


/** GET BOOK LIST / SEARCH */
$app->get('/books', function (Request $request, Response $response, $args) {
	global $mysqli;
	global $bookSqlColumns;

	$params = $request->getQueryParams();
	$searchParam = array_key_exists('search', $params) ? $params['search'] : '';
	
	if ($searchParam) {
		// SEARCH
		$stmt = $mysqli->prepare('SELECT ' . $bookSqlColumns . ' FROM books WHERE LOWER(CONCAT_WS(isbn, title, description, subtitle, authors)) LIKE ? ORDER BY createdAt DESC');
		$search = '%' . strtolower($searchParam) . '%';
		$stmt->bind_param('s', $search);
	} else {	
		// FULL LIST
		$stmt = $mysqli->prepare('SELECT ' . $bookSqlColumns . ' FROM books ORDER BY createdAt DESC');
	}
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
	
	$book->isbn = trim($book->isbn);

	if (!isbnExists($mysqli, $isbn)) {
		return $response->withStatus(404);
	}

	if ($book->isbn != $isbn) {
		return throwHttpError($response, 400, 'ISBN must match ISBN from URL');
	}

	$validationError = validateBook($book);
	if ($validationError) {
		return throwHttpError($response, 400, $validationError);
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
	
	$book->isbn = trim($book->isbn);

	if (isbnExists($mysqli, $book->isbn)) {
		return throwHttpError($response, 409, 'ISBN already exists');
	}
	
	$validationError = validateBook($book);
	if ($validationError) {
		return throwHttpError($response, 400, $validationError);
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
