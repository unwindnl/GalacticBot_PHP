This is a simple example interface to the example EMA bot implementation. You can this on any Linux machine with PHP, MySQL and Apache. This demo is a fully functional bot which can trade on the Stellar platform and also do simulations to see what settings would work best.

# Installation

- Copy all the files from this folder to an empty project folder.
- Run ```composer install``` from the newly created folder to install the dependencies.
- Create a MySQL database and a user.
- Rename setup.example.php to setup.php
- Fill in your Stellar account secrets and database settings (please create a separate Stellar account for each of your bots - you don't want a bot to make a mistake with all your XLM holdings)
- Start running the bots with ```php worker.php checkstart```
- Make the folder available with apache or another HTTP web server and browse to the folder in your browser to setup and start your bot(s)
