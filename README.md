<h1 align="center">GalacticBot</h1>

GalacticBot is a PHP 7.x library for creating trading bots on the Stellar platform.

# Features

- An abstract bot class which you can extend to do your own trading logic
- Bots can work on both the public net as the testnet on Stellar for testing
- Bots can be run in realtime or as an simulation to test out bots and settings
- Exponential Moving Average (EMA) bot implemention which is currently being live tested (see below for links)

# Requirements

- PHP version 7.1
- No extra PHP modules are required 
- MySQL is needed for running the demo EMA bot, but you could create an implementation for any other type of database

# Installation

This package is available on [Composer](https://packagist.org/packages/unwindnl/galacticbot).

Use ```composer require unwindnl/galacticbot``` to add this library to your PHP project.

# Demo

This project contains a demo project of how to setup and run a bot with a minimal web interface to interact with the bot. Please see the demo/README.md folder for more information. A live demo is available on: https://www.galacticbot.com/libdemo/. We also created a custom view for the live bot on: https://www.galacticbot.com/demo/.

## Todo 

- Trustline check

