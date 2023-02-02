<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$booksStoragePath = __DIR__ . '/storage/books.json';
$booksStoragePathOriginal = __DIR__ . '/storage/books.original.json';

function getBooksJSON() {
	global $booksStoragePath;
 	return file_get_contents($booksStoragePath);
}

function getBooks() {
	return json_decode(getBooksJSON());
}

function resetBooks() {
	global $booksStoragePath;
	global $booksStoragePathOriginal;
	$originalBooks = file_get_contents($booksStoragePathOriginal);
	file_put_contents($booksStoragePath, $originalBooks);
}

function findIndexByISBN($books, $isbn) {
	return array_search($isbn, array_column(json_decode(json_encode($books),TRUE), 'isbn'));
} 

function getBookByISBN($isbn) {
	if (!$isbn) { return NULL; }
	$books = getBooks();
	$index = findIndexByISBN($books, $isbn);

	if ($index != FALSE) {
	  return $books[$index];
	}
}

function bookExists($isbn) {
	if (!$isbn) { return FALSE; }
	$index = findIndexByISBN(getBooks(), $isbn);
	if ($index != FALSE) {
		return TRUE;
	} else {
		return FALSE;
	}
}

function writeBooks($books) {
	global $booksStoragePath;
	file_put_contents($booksStoragePath, json_encode($books));
}

function replaceBook($book) {
	$books = getBooks();
	$index = findIndexByISBN($books, $book->isbn);
	
	// when book does not exist, insert instead
	if ($index == FALSE) {
		addBook($book);
		return;
	}
	
	$books[$index] = $book;
	writeBooks($books);
}

function addBook($book) {
	$books = getBooks();
	$books[] = $book;
	writeBooks($books);
}

function deleteBook($isbn) {
	$books = getBooks();
	$index = findIndexByISBN($books, $isbn);
	if ($index != FALSE) {
		unset($books[$index]);
		writeBooks($books);
	}
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


$app->delete('/books', function (Request $request, Response $response, $args) {
	resetBooks();
	return $response->withStatus(200);
});


$app->get('/books', function (Request $request, Response $response, $args) {
	$payload = getBooksJSON();
	$response->getBody()->write($payload);
	return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(200);
});

$app->get('/books/{isbn}', function (Request $request, Response $response, $args) {
	$book = getBookByISBN($args['isbn']);
	if (!$book) {
		return $response->withStatus(404);
	}
	
	$response->getBody()->write(json_encode($book));
	return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(200);
});

$app->delete('/books/{isbn}', function (Request $request, Response $response, $args) {
	$book = getBookByISBN($args['isbn']);
	if (!$book) {
		return $response->withStatus(404);
	}
	
	deleteBook($book->isbn);
	
	$response->getBody()->write('{ delete: true }');
	return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(204);
});


$app->put('/books/{isbn}', function (Request $request, Response $response, $args) {
	$body = $request->getBody()->getContents();
	$book = json_decode($body);
	
	
	if (!$book) {
		return $response->withStatus(404);
	}
	
	// TODO check data
	
	$book->isbn = $args['isbn'];
	replaceBook($book);	
	
	$bookFromList = getBookByISBN($book->isbn);
	
	$response->getBody()->write(json_encode($bookFromList));
	return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(200);
});

$app->post('/books', function (Request $request, Response $response, $args) {
	$body = $request->getBody()->getContents();
	
	// TODO check data
	
	$book = json_decode($body);
	if (bookExists($book->isbn)) {
	  return $response->withStatus(409); // conflict
	}
	
	
	
	addBook($book);
	$bookFromList = getBookByISBN($book->isbn);
	
	$response->getBody()->write(json_encode($bookFromList));
	return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(201);
});

$app->run();
?>
