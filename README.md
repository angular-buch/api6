# BookManager API 6

This is the API for the BookManager application from the [German Angular Book](https://angular-buch.com). It is a RESTful API that provides CRUD operations for books.
A publicly available server is hosted at [api6.angular-buch.com](https://api6.angular-buch.com).

## Installation

> :warning: **You don't need to install this project to use the API. Please use the public server at [api6.angular-buch.com](https://api6.angular-buch.com).**

The `public` folder must contain an `.env` file with MySQL credentials.
Copy the `.env.example` to `.env`.

Dependencies are managed with composer. Run in the project root to install all deps:

```bash
composer install
```

## Swagger UI

The `swagger-ui` package is installed as a dependency via `composer`.
The served directory `public/swagger-ui` is a symlink to the `swagger-ui` package in the `vendor` folder.
To be able to configure Swagger UI, an Apache rewrite rule replaces the predefined config with our own version (`public/swagger-initializer.js`).
