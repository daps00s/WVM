-- Database Backup for water_dispenser_system
-- Generated: 2025-10-15 23:18:51
-- Type: Manual

-- Table structure for dispenser

CREATE TABLE `dispenser` (
  `dispenser_id` int(11) NOT NULL AUTO_INCREMENT,
  `Description` text NOT NULL,
  `Capacity` int(11) NOT NULL,
  PRIMARY KEY (`dispenser_id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for dispenser
INSERT INTO `dispenser` VALUES ('27','ITC Machine','20');
INSERT INTO `dispenser` VALUES ('28','CBM Machine','20');
INSERT INTO `dispenser` VALUES ('29','CAF Machine','20');
INSERT INTO `dispenser` VALUES ('30','CAS Machine','20');
INSERT INTO `dispenser` VALUES ('31','CVM Machine','20');
INSERT INTO `dispenser` VALUES ('32','CED Machine','20');
INSERT INTO `dispenser` VALUES ('33','3B Machine','20');

-- Table structure for dispenserlocation

CREATE TABLE `dispenserlocation` (
  `dispenserlocation_id` int(11) NOT NULL AUTO_INCREMENT,
  `location_id` int(11) NOT NULL,
  `dispenser_id` int(11) NOT NULL,
  `Status` tinyint(1) NOT NULL DEFAULT 1,
  `DateDeployed` date DEFAULT NULL,
  PRIMARY KEY (`dispenserlocation_id`),
  UNIQUE KEY `unique_dispenser_location` (`location_id`,`dispenser_id`),
  KEY `dispenser_id` (`dispenser_id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for dispenserlocation
INSERT INTO `dispenserlocation` VALUES ('28','339','27','1','2025-07-31');
INSERT INTO `dispenserlocation` VALUES ('32','341','31','1','2025-07-31');
INSERT INTO `dispenserlocation` VALUES ('33','342','32','1','2025-07-31');
INSERT INTO `dispenserlocation` VALUES ('34','0','33','0','2025-08-25');
INSERT INTO `dispenserlocation` VALUES ('35','343','30','1','2025-08-26');
INSERT INTO `dispenserlocation` VALUES ('36','341','28','1','2025-10-14');

-- Table structure for dispenserstatus

CREATE TABLE `dispenserstatus` (
  `status_id` int(11) NOT NULL AUTO_INCREMENT,
  `water_level` float NOT NULL,
  `operational_status` varchar(20) NOT NULL,
  `dispenser_id` int(11) NOT NULL,
  PRIMARY KEY (`status_id`),
  KEY `dispenser_id` (`dispenser_id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for dispenserstatus
INSERT INTO `dispenserstatus` VALUES ('20','20','Normal','27');
INSERT INTO `dispenserstatus` VALUES ('21','20','Normal','28');
INSERT INTO `dispenserstatus` VALUES ('22','3','Disabled','29');
INSERT INTO `dispenserstatus` VALUES ('23','4','Normal','30');
INSERT INTO `dispenserstatus` VALUES ('24','5','Normal','31');
INSERT INTO `dispenserstatus` VALUES ('25','5','Normal','32');
INSERT INTO `dispenserstatus` VALUES ('26','10','Disabled','33');

-- Table structure for location

CREATE TABLE `location` (
  `location_id` int(11) NOT NULL AUTO_INCREMENT,
  `location_name` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `latitude` decimal(11,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  PRIMARY KEY (`location_id`)
) ENGINE=InnoDB AUTO_INCREMENT=347 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for location
INSERT INTO `location` VALUES ('339','CET - ITC','ITC Lab','15.63954742','120.41917920');
INSERT INTO `location` VALUES ('341','CVM ','CVM Lab','15.63987804','120.41906655');
INSERT INTO `location` VALUES ('342','CED','CED Lab ','15.63994519','120.42057395');
INSERT INTO `location` VALUES ('343','CAS','CAS lab','15.63830969','120.41842282');

-- Table structure for transaction

CREATE TABLE `transaction` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `amount_dispensed` float NOT NULL,
  `DateAndTime` datetime DEFAULT current_timestamp(),
  `coin_type` varchar(50) NOT NULL,
  `dispenser_id` int(11) NOT NULL,
  `water_type` varchar(10) NOT NULL DEFAULT 'COLD',
  PRIMARY KEY (`transaction_id`),
  KEY `dispenser_id` (`dispenser_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2771 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for transaction
INSERT INTO `transaction` VALUES ('2457','500','2025-10-10 23:22:40','10 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2458','250','2025-10-10 23:25:17','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2459','500','2025-10-10 23:29:30','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2460','500','2025-10-10 23:29:49','10 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2461','250','2025-10-10 23:38:15','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2462','250','2025-10-10 23:39:08','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2463','250','2025-10-10 23:39:26','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2464','250','2025-10-10 23:43:16','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2465','250','2025-10-10 23:51:31','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2466','50','2025-10-10 23:51:46','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2467','500','2025-10-10 23:52:35','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2468','500','2025-10-10 23:52:46','10 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2469','250','2025-10-10 23:52:58','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2470','500','2025-10-10 23:54:07','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2471','500','2025-10-10 23:54:19','10 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2472','50','2025-10-10 23:54:50','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2473','250','2025-10-10 23:55:04','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2474','500','2025-10-10 23:55:27','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2475','500','2025-10-10 23:55:37','10 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2476','250','2025-10-10 23:57:47','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2477','50','2025-10-10 23:57:59','1 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2478','500','2025-10-10 23:58:17','10 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2479','50','2025-10-10 23:58:45','1 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2480','500','2025-10-10 23:58:57','10 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2481','500','2025-10-10 23:59:11','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2482','250','2025-10-10 23:59:21','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2483','250','2025-10-11 00:06:06','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2484','250','2025-10-11 00:08:10','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2485','500','2025-10-11 00:08:30','10 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2486','500','2025-10-11 00:08:53','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2487','50','2025-10-11 00:34:13','1 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2488','50','2025-10-11 00:34:41','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2489','500','2025-10-11 00:35:05','10 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2490','250','2025-10-11 00:35:25','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2491','250','2025-10-11 00:45:14','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2492','250','2025-10-11 01:00:22','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2493','250','2025-10-11 01:01:25','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2494','250','2025-10-11 01:07:49','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2495','250','2025-10-11 01:24:51','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2496','250','2025-10-11 01:34:13','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2497','250','2025-10-11 02:15:44','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2498','250','2025-10-11 02:19:58','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2499','250','2025-10-11 02:23:00','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2500','250','2025-10-11 02:27:36','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2501','250','2025-10-11 02:35:33','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2502','250','2025-10-11 02:36:12','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2503','250','2025-10-11 02:43:17','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2504','250','2025-10-11 02:50:30','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2505','250','2025-10-11 02:54:50','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2506','250','2025-10-11 03:01:00','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2507','250','2025-10-11 19:30:21','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2508','250','2025-10-11 19:42:28','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2509','250','2025-10-11 19:43:55','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2510','250','2025-10-11 19:46:30','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2511','50','2025-10-11 19:46:51','1 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2512','250','2025-10-11 19:52:58','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2513','250','2025-10-11 19:59:28','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2514','50','2025-10-11 20:00:08','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2515','500','2025-10-11 20:01:15','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2516','500','2025-10-11 20:02:38','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2517','250','2025-10-11 20:03:17','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2518','50','2025-10-11 20:04:11','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2519','50','2025-10-11 20:04:43','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2520','50','2025-10-11 20:05:13','1 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2521','250','2025-10-11 20:09:05','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2522','290','2025-10-11 20:09:21','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2523','290','2025-10-11 20:09:53','1 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2524','250','2025-10-11 20:14:38','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2525','290','2025-10-11 20:25:28','1 Peso','28','COLD');
INSERT INTO `transaction` VALUES ('2526','250','2025-10-11 20:27:07','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2527','290','2025-10-11 20:27:53','1 Peso','28','COLD');
INSERT INTO `transaction` VALUES ('2528','250','2025-10-11 20:31:22','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2529','290','2025-10-11 20:33:04','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2530','780','2025-10-11 20:37:36','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2531','290','2025-10-13 19:20:53','1 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2532','580','2025-10-13 19:24:38','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2533','580','2025-10-13 20:03:12','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2534','290','2025-10-13 20:04:33','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2535','290','2025-10-13 20:31:25','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2536','580','2025-10-13 20:32:41','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2537','290','2025-10-13 20:39:38','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2538','290','2025-10-13 20:42:59','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2539','290','2025-10-13 20:46:16','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2540','780','2025-10-13 20:55:00','10 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2541','580','2025-10-13 21:12:20','5 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2542','780','2025-10-13 21:25:45','10 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2543','780','2025-10-13 21:30:31','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2544','780','2025-10-13 21:36:41','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2545','2.5','2025-07-15 10:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2546','5','2025-07-15 12:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2547','0.5','2025-07-16 09:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2548','2.5','2025-07-16 15:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2549','5','2025-07-17 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2550','1','2025-07-18 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2551','2.5','2025-07-19 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2552','5','2025-07-20 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2553','0.5','2025-07-21 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2554','2.5','2025-07-22 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2555','5','2025-08-01 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2556','1','2025-08-02 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2557','2.5','2025-08-03 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2558','5','2025-08-04 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2559','0.5','2025-08-05 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2560','2.5','2025-08-06 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2561','5','2025-08-07 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2562','1','2025-08-08 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2563','2.5','2025-08-09 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2564','5','2025-08-10 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2565','780','2025-10-13 21:44:22','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2566','780','2025-10-13 21:56:51','10 Peso','27','HOT');
INSERT INTO `transaction` VALUES ('2567','780','2025-10-13 21:59:27','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2568','780','2025-10-13 21:59:53','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2569','580','2025-10-13 22:00:34','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2570','780','2025-10-14 00:07:41','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2571','2.5','2025-07-15 10:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2572','5','2025-07-15 12:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2573','0.5','2025-07-16 09:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2574','2.5','2025-07-16 15:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2575','5','2025-07-17 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2576','1','2025-07-18 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2577','2.5','2025-07-19 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2578','5','2025-07-20 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2579','0.5','2025-07-21 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2580','2.5','2025-07-22 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2581','5','2025-08-01 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2582','1','2025-08-02 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2583','2.5','2025-08-03 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2584','5','2025-08-04 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2585','0.5','2025-08-05 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2586','2.5','2025-08-06 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2587','5','2025-08-07 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2588','1','2025-08-08 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2589','2.5','2025-08-09 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2590','5','2025-08-10 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2591','2.5','2025-07-15 10:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2592','5','2025-07-15 12:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2593','0.5','2025-07-16 09:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2594','2.5','2025-07-16 15:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2595','5','2025-07-17 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2596','1','2025-07-18 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2597','2.5','2025-07-19 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2598','5','2025-07-20 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2599','0.5','2025-07-21 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2600','2.5','2025-07-22 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2601','5','2025-08-01 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2602','1','2025-08-02 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2603','2.5','2025-08-03 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2604','5','2025-08-04 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2605','0.5','2025-08-05 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2606','2.5','2025-08-06 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2607','5','2025-08-07 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2608','1','2025-08-08 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2609','2.5','2025-08-09 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2610','5','2025-08-10 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2611','2.5','2025-07-15 10:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2612','5','2025-07-15 12:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2613','0.5','2025-07-16 09:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2614','2.5','2025-07-16 15:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2615','5','2025-07-17 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2616','1','2025-07-18 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2617','2.5','2025-07-19 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2618','5','2025-07-20 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2619','0.5','2025-07-21 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2620','2.5','2025-07-22 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2621','5','2025-08-01 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2622','1','2025-08-02 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2623','2.5','2025-08-03 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2624','5','2025-08-04 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2625','0.5','2025-08-05 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2626','2.5','2025-08-06 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2627','5','2025-08-07 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2628','1','2025-08-08 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2629','2.5','2025-08-09 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2630','5','2025-08-10 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2631','2.5','2025-07-15 10:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2632','5','2025-07-15 12:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2633','0.5','2025-07-16 09:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2634','2.5','2025-07-16 15:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2635','5','2025-07-17 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2636','1','2025-07-18 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2637','2.5','2025-07-19 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2638','5','2025-07-20 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2639','0.5','2025-07-21 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2640','2.5','2025-07-22 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2641','5','2025-08-01 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2642','1','2025-08-02 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2643','2.5','2025-08-03 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2644','5','2025-08-04 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2645','0.5','2025-08-05 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2646','2.5','2025-08-06 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2647','5','2025-08-07 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2648','1','2025-08-08 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2649','2.5','2025-08-09 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2650','5','2025-08-10 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2651','2.5','2025-07-15 10:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2652','5','2025-07-15 12:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2653','0.5','2025-07-16 09:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2654','2.5','2025-07-16 15:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2655','5','2025-07-17 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2656','1','2025-07-18 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2657','2.5','2025-07-19 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2658','5','2025-07-20 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2659','0.5','2025-07-21 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2660','2.5','2025-07-22 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2661','5','2025-08-01 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2662','1','2025-08-02 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2663','2.5','2025-08-03 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2664','5','2025-08-04 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2665','0.5','2025-08-05 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2666','2.5','2025-08-06 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2667','5','2025-08-07 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2668','1','2025-08-08 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2669','2.5','2025-08-09 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2670','5','2025-08-10 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2671','2.5','2025-07-15 10:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2672','5','2025-07-15 12:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2673','0.5','2025-07-16 09:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2674','2.5','2025-07-16 15:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2675','5','2025-07-17 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2676','1','2025-07-18 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2677','2.5','2025-07-19 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2678','5','2025-07-20 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2679','0.5','2025-07-21 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2680','2.5','2025-07-22 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2681','5','2025-08-01 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2682','1','2025-08-02 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2683','2.5','2025-08-03 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2684','5','2025-08-04 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2685','0.5','2025-08-05 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2686','2.5','2025-08-06 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2687','5','2025-08-07 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2688','1','2025-08-08 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2689','2.5','2025-08-09 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2690','5','2025-08-10 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2691','2.5','2025-07-15 10:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2692','5','2025-07-15 12:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2693','0.5','2025-07-16 09:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2694','2.5','2025-07-16 15:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2695','5','2025-07-17 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2696','1','2025-07-18 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2697','2.5','2025-07-19 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2698','5','2025-07-20 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2699','0.5','2025-07-21 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2700','2.5','2025-07-22 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2701','5','2025-08-01 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2702','1','2025-08-02 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2703','2.5','2025-08-03 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2704','5','2025-08-04 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2705','0.5','2025-08-05 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2706','2.5','2025-08-06 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2707','5','2025-08-07 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2708','1','2025-08-08 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2709','2.5','2025-08-09 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2710','5','2025-08-10 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2711','2.5','2025-07-15 10:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2712','5','2025-07-15 12:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2713','0.5','2025-07-16 09:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2714','2.5','2025-07-16 15:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2715','5','2025-07-17 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2716','1','2025-07-18 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2717','2.5','2025-07-19 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2718','5','2025-07-20 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2719','0.5','2025-07-21 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2720','2.5','2025-07-22 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2721','5','2025-08-01 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2722','1','2025-08-02 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2723','2.5','2025-08-03 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2724','5','2025-08-04 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2725','0.5','2025-08-05 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2726','2.5','2025-08-06 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2727','5','2025-08-07 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2728','1','2025-08-08 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2729','2.5','2025-08-09 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2730','5','2025-08-10 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2731','2.5','2025-07-15 10:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2732','5','2025-07-15 12:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2733','0.5','2025-07-16 09:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2734','2.5','2025-07-16 15:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2735','5','2025-07-17 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2736','1','2025-07-18 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2737','2.5','2025-07-19 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2738','5','2025-07-20 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2739','0.5','2025-07-21 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2740','2.5','2025-07-22 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2741','5','2025-08-01 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2742','1','2025-08-02 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2743','2.5','2025-08-03 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2744','5','2025-08-04 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2745','0.5','2025-08-05 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2746','2.5','2025-08-06 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2747','5','2025-08-07 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2748','1','2025-08-08 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2749','2.5','2025-08-09 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2750','5','2025-08-10 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2751','2.5','2025-07-15 10:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2752','5','2025-07-15 12:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2753','0.5','2025-07-16 09:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2754','2.5','2025-07-16 15:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2755','5','2025-07-17 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2756','1','2025-07-18 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2757','2.5','2025-07-19 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2758','5','2025-07-20 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2759','0.5','2025-07-21 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2760','2.5','2025-07-22 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2761','5','2025-08-01 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2762','1','2025-08-02 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2763','2.5','2025-08-03 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2764','5','2025-08-04 10:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2765','0.5','2025-08-05 12:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2766','2.5','2025-08-06 13:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2767','5','2025-08-07 11:00:00','10 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2768','1','2025-08-08 14:00:00','1 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2769','2.5','2025-08-09 16:00:00','5 Peso','27','COLD');
INSERT INTO `transaction` VALUES ('2770','5','2025-08-10 10:00:00','10 Peso','27','COLD');

-- Table structure for userlogin

CREATE TABLE `userlogin` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL DEFAULT 'pbkdf2_sha256$600000$GX6QZ8n9Y2v7W1pL3cR4tM$4kS8dV5bF2nH7jK1lP9oQ3mJ6rT0wX2yC4eB7uN8iA=',
  `email` varchar(100) NOT NULL,
  `role` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for userlogin
INSERT INTO `userlogin` VALUES ('1','admin','$2y$10$qvpsPHN4gld9W9fRKUTDU.sjSNvkz.zGjdK3r5/oAAvE/cSQRKJcO','admin@gmail.com','Administrator');

