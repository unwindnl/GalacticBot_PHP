<h1 align="center">GalacticBot</h1>

GalacticBot is a PHP 7.x library for creating trading bots on the Stellar platform.

# Features

- An abstract bot class which you can extend to do your own trading logic
- Bots can work on both the Stellar public net as well as on the Stellar testnet for testing
- Bots can be run in realtime or as an simulation to test out bots and settings
- Exponential Moving Average (EMA) bot implemention which is currently being live tested (see below for links)
- Demo project to get a bot up and running in minutes
- Demo project also includes a script to manage bot processes

# Requirements

- PHP version 7.1
- No extra PHP modules are required 
- MySQL is needed for running the demo EMA bot, but you could create an implementation for any other type of database

# Installation

This package is available on [Composer](https://packagist.org/packages/unwindnl/galacticbot).

Use ```composer require unwindnl/galacticbot``` to add this library to your PHP project.

# Demo

This project contains a demo project of how to setup and run a bot with a minimal web interface to interact with the bot. Please see the demo/README.md [demo/README.md](demo/README.md) folder for more information.

A live demo is available on: https://www.galacticbot.com/libdemo/.

We also created an example of a custom (graphical) view for the live bot on: https://www.galacticbot.com/demo/.

# Data(base) abstraction

The bot does not interact with a database directly but uses the ```DataInterface``` interface for describing how an implemention should look like.

Please see the example ```MysqlDataInterface``` implementation of how to implement your own if you would want to interface with another database. This example implementation is not optimized and could slow down with more data in the database. So its best to clear our older data or optimize/create your own implementation. 

# Implementing your own trading algorithm

Create a class that extends the GalacticBot\Bot class.

Add a list of custom trading states your bot can have, for example:

```php
const TRADE_STATE_BUY_WAIT = "BUY_WAIT";
const TRADE_STATE_SELL_WAIT = "SELL_WAIT";
```

Implement the following abstract methods:

## initialize()

Here you could load for example data from a database you going to need for your algorithm. This is only called once.
 
## getTradeStateLabel($forState)

This is were you return a more descriptive text for each of your custom states you defined earlier.
 
## process(\GalacticBot\Time $time, $sample)

A very minimal example:

```php
protected function process(\GalacticBot\Time $time, $sample)
{
	// Get current trade state
	$tradeState = $this->data->get("tradeState");

	// Get the last added trade
	$lastTrade = $this->data->getLastTrade();

	// If we have a trade and it isn't completed yet, then update it to get the latest state
	if ($lastTrade && !$lastTrade->getIsFilledCompletely())
	{
		// Get the latest state from the Stellar network
		$lastTrade->updateFromAPIForBot($this->settings->getAPI(), $this);
		
		// If it isn't done, we'll have to return and wait for it to complete
		// You could also cancel it or change it if you want
		if (!$lastTrade->getIsFilledCompletely())
			return;
	}	
	
	if ($tradeState == self::TRADE_STATE_NONE || $tradeState == self::TRADE_STATE_BUY_WAIT)
		if (time to buy / do something with $sample)
		{
			$this->buy();
			$tradeState = self::TRADE_STATE_SELL_WAIT;
		}
	}
	else if ($tradeState == self::TRADE_STATE_SELL_WAIT)
	{
		if (time to sell / do something with $sample)
		{
			$this->sell();
			$tradeState = self::TRADE_STATE_BUY_WAIT;
		}
	}
}
```

# Warning

Please not that this library is still under development. It is not advisable to trade with large amounts at this stage.

# Open issues 

- You need to setup a trustline for the assets you want to trade with your Stellar account. The library will do this for you in the future but for now you will have to do this yourself (with for example StellarTerm).

