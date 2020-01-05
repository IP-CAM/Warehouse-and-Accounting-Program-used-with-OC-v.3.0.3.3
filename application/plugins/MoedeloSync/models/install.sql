CREATE TABLE `plugin_sync_entries` (
  `entry_id` int(11) NOT NULL AUTO_INCREMENT,
  `sync_destination` varchar(45) DEFAULT NULL,
  `local_id` int(11) DEFAULT NULL,
  `local_hash` varchar(32) DEFAULT NULL,
  `local_tstamp` datetime DEFAULT NULL,
  `local_deleted` tinyint(4) DEFAULT NULL,
  `remote_id` int(11) DEFAULT NULL,
  `remote_hash` varchar(32) DEFAULT NULL,
  `remote_tstamp` datetime DEFAULT NULL,
  `remote_deleted` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`entry_id`),
  UNIQUE KEY `index3` (`sync_destination`,`remote_id`),
  UNIQUE KEY `index4` (`local_id`,`sync_destination`),
  KEY `index2` (`sync_destination`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
