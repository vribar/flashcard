## Flashcard

Flashcard is a simple command line app to show off my Laravel skills for Studocu. It's build with the Laravel Framework
9.35.1 and dockerized with MySQL version 8.0 to store the flashcards and answers.


The purpose fo the app is to create flashcards with short questions and answers and practice in answering the questions. 

## Prerequesites

In order to install flashcards, you should have [docker](https://docs.docker.com/get-docker/) and [composer](https://getcomposer.org/) installed.

## Installation

Get code

    git clone https://github.com/vribar/flashcard.git

Create docker container with 

    cd flashcard
    docker build -t flashcard .

Then run the container with

    docker-compose

Then find the flashcard image with 

    docker ps

and run the app with 

    docker exec -it <IMAGENAME> php artisan flashcard:interactive

## Contributors

My cat who regularly danced on my keyboard contributes to any bugs you find

