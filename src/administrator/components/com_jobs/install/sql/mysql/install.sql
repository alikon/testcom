CREATE TABLE IF NOT EXISTS `#__tasks`
(
    `id`       int(11)  NOT NULL AUTO_INCREMENT,
    `taskname` varchar(100) NOT NULL,
    `duration` int(11)     NOT NULL,
    `jobid`    int(11)     NOT NULL,
    `taskid`   int(11)     NOT NULL,
    `exitcode` int(11)     NOT NULL,
    `lastdate` datetime,
    `nextdate` datetime,

    
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci;
  
 
CREATE TABLE IF NOT EXISTS `#__jobs` (
  `element` varchar(100) NOT NULL,
  `folder` varchar(100) NOT NULL,
  PRIMARY KEY (`element`, `folder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
