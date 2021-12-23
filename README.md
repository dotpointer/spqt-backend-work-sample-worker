# spqt-backend-work-sample-worker

Backend work sample distributed workers.

See https://github.com/spqt/backend-work-sample-worker.

## Purpose

Fetch HTTP response codes from URLs stored in a database and store
them in the database.

## What it does

See Purpose.

## How it works

It retrieves the URLs from the database, iterates over them and requests
the URLs one by one using cURL to get the HTTP response codes.

## Requirements / tested on

- MariaDB (or MySQL)
- PHP 7.4 with cURL, MySQL/MariaDB
- PHP cURL extension

## Getting Started

These instructions will get you a copy of the project up and running on your
local machine for development and testing purposes. See deployment for notes on
how to deploy the project on a live system.

### Prerequisites

What things you need to install the software and how to install them

```
- Debian Linux 9 or similar system
- MariaDB (or MySQL)
- PHP
```

Setup PHP with cURL and MariaDB/MySQL support and MariaDB/MySQL.

In short: apt-get install mariadb-server php php-mysqli php-curl
and then configure PHP and setup a user in MariaDB.

### Installing

Head to a directory and clone the repository:

```
git clone https://github.com/dotpointer/spqt-backend-work-sample-worker.git
cd spqt-backend-work-sample-worker/
```

Import database structure, located in database.sql

Standing in the project root directory login to the database:

```
mariadb/mysql -u <username> -p

```

If you do not have a user for the web server, then login as root and do
this to create the user named www with password www:

```
CREATE USER 'www'@'localhost' IDENTIFIED BY 'www';
```

Then import the database structure and assign a user to it, replace
www with the web server user in the database system:
```
SOURCE database.sql
GRANT ALL PRIVILEGES ON `spqt-backend-work-sample-worker`.* TO 'www'@'localhost';
FLUSH PRIVILEGES;
```

Copy the setup.example.php file to setup.php and fill in the database credentials in it.

## Usage

Run the worker.php in a terminal using PHP - the default action is process:
```
php worker.php
```

Add a site, replace the string between quotes with a valid URL:
```
php worker.php -a="https://www.../"
```

List all sites:
```
php worker.php -l
```

Delete a site, replace id with a site id from the list sites command:
```
php worker.php -d="id"
```

Process sites with the NEW status (default action):
```
php worker.php -p
```

Verbose output:
```
php worker.php -v
```

Print help information:
```
php worker.php -h
```

## Authors

* **Robert Klebe** - *Development* - [dotpointer](https://github.com/dotpointer)

## License

This project is licensed under the MIT License - see the
[LICENSE.md](LICENSE.md) file for details.

Contains dependency files that may be licensed under their own respective
licenses.
