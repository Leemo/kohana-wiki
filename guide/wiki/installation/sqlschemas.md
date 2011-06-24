# SQL schemas

## MySQL

~~~
CREATE TABLE `wiki` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `scope` varchar(255) NOT NULL,
  `created` int(11) NOT NULL DEFAULT '0',
  `modified` int(11) NOT NULL DEFAULT '0',
  `title` varchar(255) NOT NULL,
  `markdown` mediumtext NOT NULL,
  `html` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_title` (`scope`,`title`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8;

CREATE TABLE `wiki_links` (
  `wiki_id` int(11) unsigned NOT NULL,
  `link` varchar(255) NOT NULL,
  PRIMARY KEY (`wiki_id`,`link`),
  KEY `fk_wiki_id` (`wiki_id`),
  CONSTRAINT `wiki_links_ibfk_1` FOREIGN KEY (`wiki_id`) REFERENCES `wiki` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
~~~

## PostgreSQL

Not yet, but we plan to...