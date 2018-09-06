/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE TABLE `BotData` (
  `botID` varchar(255) NOT NULL,
  `name` varchar(64) NOT NULL,
  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `value` text,
  PRIMARY KEY (`botID`,`name`,`date`),
  KEY `search` (`botID`,`name`),
  KEY `forBot` (`botID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `BotTrade` (
  `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `botID` varchar(255) NOT NULL,
  `previousBotTradeID` int(11) unsigned DEFAULT NULL,
  `type` varchar(64) DEFAULT NULL,
  `state` varchar(32) NOT NULL,
  `offerID` varchar(255) DEFAULT NULL,
  `transactionEnvelopeXdr` text,
  `claimedOffers` text,
  `sellAmount` double DEFAULT NULL,
  `spentAmount` double DEFAULT NULL,
  `amountRemaining` double DEFAULT NULL,
  `boughtAmount` double DEFAULT NULL,
  `price` double DEFAULT NULL,
  `priceN` double DEFAULT NULL,
  `priceD` double DEFAULT NULL,
  `paidPrice` double DEFAULT NULL,
  `fee` double DEFAULT NULL,
  `stateData` double DEFAULT NULL,
  `fillPercentage` double DEFAULT NULL,
  `createdAt` datetime NOT NULL,
  `processedAt` datetime NOT NULL,
  `updatedAt` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `search` (`botID`,`processedAt`),
  KEY `forBot` (`botID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
