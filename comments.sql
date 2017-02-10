-- phpMyAdmin SQL Dump
-- version 4.0.10deb1
-- http://www.phpmyadmin.net
--

-- --------------------------------------------------------

--
-- Table structure for table `blog_comments`
--

CREATE TABLE IF NOT EXISTS `blog_comments` (
  `cid` int(11) NOT NULL AUTO_INCREMENT,
  `bid` int(11) NOT NULL,
  `content` text,
  `author_name` varchar(128) NOT NULL,
  `author_tripcode` varchar(42) DEFAULT NULL,
  `posted_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `author_ip` mediumtext,
  `deleted` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`cid`),
  KEY `bid` (`bid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=0 ;

-- --------------------------------------------------------

--
-- Table structure for table `blog_comments_moderator`
--

CREATE TABLE IF NOT EXISTS `blog_comments_moderator` (
  `bid` int(11) NOT NULL,
  `moderator` varchar(64) DEFAULT NULL,
  KEY `blog_comments_moderator` (`bid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


--
-- Constraints for dumped tables
--

--
-- Constraints for table `blog_comments`
--
ALTER TABLE `blog_comments`
  ADD CONSTRAINT `blog_comments_ibfk_1` FOREIGN KEY (`bid`) REFERENCES `blog_entry` (`id`);

--
-- Constraints for table `blog_comments_moderator`
--
ALTER TABLE `blog_comments_moderator`
  ADD CONSTRAINT `blog_comments_moderator` FOREIGN KEY (`bid`) REFERENCES `blog_entry` (`id`);
