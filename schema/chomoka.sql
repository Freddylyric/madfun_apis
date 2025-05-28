-- phpMyAdmin SQL Dump
-- version 4.5.1
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Oct 12, 2020 at 09:44 AM
-- Server version: 10.1.13-MariaDB
-- PHP Version: 5.6.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `chomoka`
--

-- --------------------------------------------------------

--
-- Table structure for table `attribution`
--

CREATE TABLE `attribution` (
  `id` bigint(20) NOT NULL,
  `profile_id` bigint(20) NOT NULL,
  `keyword_id` bigint(20) NOT NULL,
  `transaction_id` bigint(20) NOT NULL,
  `created` datetime DEFAULT NULL,
  `updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `blacklist`
--

CREATE TABLE `blacklist` (
  `id` int(11) NOT NULL,
  `msisdn` bigint(20) NOT NULL,
  `reason` varchar(150) DEFAULT NULL,
  `status` int(3) NOT NULL DEFAULT '1',
  `created` datetime DEFAULT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `blacklist`
--

INSERT INTO `blacklist` (`id`, `msisdn`, `reason`, `status`, `created`, `updated`) VALUES
(1, 254703978228, NULL, 1, '2020-07-14 20:55:50', '2020-07-14 17:55:50'),
(2, 254704746823, NULL, 1, '2020-07-14 20:55:50', '2020-07-14 17:55:50');

-- --------------------------------------------------------

--
-- Table structure for table `code`
--

CREATE TABLE `code` (
  `code_id` bigint(20) NOT NULL,
  `code` varchar(25) NOT NULL,
  `distributor_id` int(11) NOT NULL,
  `status` int(3) NOT NULL DEFAULT '1',
  `created` datetime DEFAULT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `distributors`
--

CREATE TABLE `distributors` (
  `id` int(11) NOT NULL,
  `partner_name` varchar(65) NOT NULL,
  `status` int(3) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `distributors`
--

INSERT INTO `distributors` (`id`, `partner_name`, `status`, `created`, `updated`) VALUES
(1, 'Batch I', 1, '2020-08-19 15:13:35', '2020-10-12 07:26:46'),
(2, 'Batch II', 1, '2020-08-19 15:13:35', '2020-10-12 07:26:54'),
(3, 'Testing Codes', 1, '2020-08-19 15:13:35', '2020-10-12 07:27:07');

-- --------------------------------------------------------

--
-- Table structure for table `entries`
--

CREATE TABLE `entries` (
  `id` bigint(20) NOT NULL,
  `inbox_id` bigint(20) NOT NULL,
  `status` int(11) NOT NULL,
  `status_desc` varchar(300) NOT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `entries_redeemed`
--

CREATE TABLE `entries_redeemed` (
  `id` bigint(20) NOT NULL,
  `entry_id` bigint(20) NOT NULL,
  `code_id` bigint(20) NOT NULL,
  `distributor_id` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `inbox`
--

CREATE TABLE `inbox` (
  `inbox_id` bigint(20) NOT NULL,
  `profile_id` bigint(20) NOT NULL,
  `short_code` varchar(65) NOT NULL,
  `unique_id` varchar(200) NOT NULL,
  `message` varchar(700) DEFAULT NULL,
  `source` varchar(150) NOT NULL,
  `extra_data` varchar(700) DEFAULT NULL,
  `received_on` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `mechanics`
--

CREATE TABLE `mechanics` (
  `id` int(11) NOT NULL,
  `reward_id` int(11) NOT NULL,
  `entry_no` int(11) NOT NULL,
  `reward_name` varchar(100) NOT NULL,
  `reward_value` int(11) NOT NULL DEFAULT '0',
  `mechanic_limit` int(11) NOT NULL DEFAULT '0',
  `daily_mechanic_limit` int(11) NOT NULL DEFAULT '0',
  `status` int(3) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `mechanics`
--

INSERT INTO `mechanics` (`id`, `reward_id`, `entry_no`, `reward_name`, `reward_value`, `mechanic_limit`, `daily_mechanic_limit`, `status`, `created`, `updated`) VALUES
(1, 1, 3, 'MPESA KES 500', 500, 30, 2, 1, '2020-08-26 15:54:17', '2020-08-28 06:16:40'),
(2, 3, 3, 'AIRTIME KES 20', 20, 30, 2, 1, '2020-08-26 15:54:17', '2020-09-01 08:56:06'),
(3, 2, 3, '15SMS & 15MB Data', 15, 30, 2, 1, '2020-08-26 15:54:17', '2020-08-26 12:54:17'),
(4, 4, 3, 'Electricity Tokens', 30, 30, 2, 1, '2020-08-26 15:54:17', '2020-08-26 12:54:17'),
(5, 3, 3, 'AIRTIME KES 50', 50, 30, 2, 1, '2020-08-26 15:54:17', '2020-09-01 08:56:06'),
(6, 2, 3, '50SMS & 50MB Data', 50, 30, 2, 1, '2020-08-26 15:54:17', '2020-08-26 12:54:17');

-- --------------------------------------------------------

--
-- Table structure for table `mechanic_transaction`
--

CREATE TABLE `mechanic_transaction` (
  `id` bigint(20) NOT NULL,
  `mechanic_id` int(11) NOT NULL,
  `entry_id` bigint(20) NOT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `mpesa_withdrawal_dlr`
--

CREATE TABLE `mpesa_withdrawal_dlr` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `mpesa_transaction_id` varchar(65) DEFAULT NULL,
  `response_code` varchar(50) DEFAULT NULL,
  `response_status` varchar(150) DEFAULT NULL,
  `response_description` varchar(350) DEFAULT NULL,
  `b2c_balance` float DEFAULT '0',
  `transaction_amount` float DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `outbox`
--

CREATE TABLE `outbox` (
  `outbox_id` bigint(20) NOT NULL,
  `profile_id` bigint(20) NOT NULL,
  `message` varchar(1200) NOT NULL,
  `processed` int(11) NOT NULL DEFAULT '0',
  `nos` int(11) NOT NULL DEFAULT '0',
  `created_by` varchar(65) NOT NULL,
  `created` datetime DEFAULT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `outbox_dlr`
--

CREATE TABLE `outbox_dlr` (
  `id` bigint(20) NOT NULL,
  `outbox_id` varchar(65) NOT NULL,
  `correlator` varchar(65) NOT NULL,
  `delivery_state` varchar(15) NOT NULL,
  `delivery_status` varchar(65) NOT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `campaign_id` int(11) DEFAULT NULL,
  `sms_cost` float NOT NULL DEFAULT '0',
  `sms_pages` int(11) NOT NULL DEFAULT '1',
  `received_on` datetime NOT NULL,
  `timestamp` datetime NOT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `paybill_balance`
--

CREATE TABLE `paybill_balance` (
  `id` int(11) NOT NULL,
  `paybill` varchar(12) NOT NULL,
  `org_balance` float NOT NULL DEFAULT '0',
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `profile`
--

CREATE TABLE `profile` (
  `profile_id` bigint(20) NOT NULL,
  `msisdn` bigint(20) NOT NULL,
  `email_address` varchar(65) DEFAULT NULL,
  `first_name` varchar(25) DEFAULT NULL,
  `last_name` varchar(25) DEFAULT NULL,
  `surname` varchar(25) DEFAULT NULL,
  `network` varchar(15) NOT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `profile_accounts`
--

CREATE TABLE `profile_accounts` (
  `id` int(11) NOT NULL,
  `profile_id` bigint(20) NOT NULL,
  `meter_number` varchar(100) NOT NULL,
  `status` int(3) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `profile_attribute`
--

CREATE TABLE `profile_attribute` (
  `id` int(11) NOT NULL,
  `profile_id` bigint(20) NOT NULL,
  `points` float NOT NULL DEFAULT '0',
  `total_withdrawals` float NOT NULL DEFAULT '0',
  `total_entries` int(11) NOT NULL DEFAULT '0',
  `valid_entries` int(11) NOT NULL DEFAULT '0',
  `invalid_entries` int(11) NOT NULL DEFAULT '0',
  `total_referrals` int(11) NOT NULL DEFAULT '0',
  `successful_referrals` int(11) NOT NULL DEFAULT '0',
  `location` varchar(100) DEFAULT NULL,
  `garage_name` varchar(100) DEFAULT NULL,
  `learn_level` int(11) NOT NULL DEFAULT '0',
  `total_answered_question` int(11) NOT NULL DEFAULT '0',
  `correctly_answered_question` int(11) NOT NULL DEFAULT '0',
  `token` varchar(500) DEFAULT NULL,
  `pin` varchar(500) DEFAULT NULL,
  `origin` varchar(65) DEFAULT NULL,
  `frequency_of_use` int(11) NOT NULL DEFAULT '0',
  `first_won_date` datetime DEFAULT NULL,
  `last_won_date` datetime DEFAULT NULL,
  `first_withdrawal_date` datetime DEFAULT NULL,
  `last_withdrawal_date` datetime DEFAULT NULL,
  `last_use_date` datetime DEFAULT NULL,
  `status` int(3) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `profile_balance`
--

CREATE TABLE `profile_balance` (
  `id` bigint(20) NOT NULL,
  `profile_id` bigint(20) NOT NULL,
  `balance` float NOT NULL DEFAULT '0',
  `bonus` float NOT NULL DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `profile_login`
--

CREATE TABLE `profile_login` (
  `id` bigint(20) NOT NULL,
  `profile_id` bigint(20) NOT NULL,
  `verify_code` varchar(200) DEFAULT NULL,
  `successful_attempts` int(11) NOT NULL DEFAULT '0',
  `failed_attempts` int(11) DEFAULT '0',
  `cumlative_failed_attempts` int(11) DEFAULT '0',
  `last_failed_attempt` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `profile_login_activity`
--

CREATE TABLE `profile_login_activity` (
  `login_activity_id` bigint(20) NOT NULL,
  `profile_id` bigint(20) NOT NULL,
  `browser_name` varchar(30) DEFAULT NULL,
  `browser_version` varchar(30) DEFAULT NULL,
  `browser_os` varchar(30) DEFAULT NULL,
  `browser_src` varchar(300) DEFAULT NULL,
  `link_id` varchar(100) DEFAULT NULL,
  `acl` int(5) NOT NULL DEFAULT '0',
  `status` int(5) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `references_type`
--

CREATE TABLE `references_type` (
  `id` int(11) NOT NULL,
  `name` varchar(65) NOT NULL,
  `description` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `regions`
--

CREATE TABLE `regions` (
  `id` int(11) NOT NULL,
  `region_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `regions`
--

INSERT INTO `regions` (`id`, `region_name`) VALUES
(2, 'Coast'),
(1, 'Nairobi');

-- --------------------------------------------------------

--
-- Table structure for table `rewards`
--

CREATE TABLE `rewards` (
  `id` int(11) NOT NULL,
  `reward_name` varchar(65) NOT NULL,
  `reward_desc` varchar(150) NOT NULL,
  `status` int(3) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `rewards`
--

INSERT INTO `rewards` (`id`, `reward_name`, `reward_desc`, `status`) VALUES
(1, 'Cash', 'Instant Mpesa Transaction ', 1),
(2, 'Sms & Data', 'SMS and Data Bundles', 1),
(3, 'Airtime', 'Sends Airtime', 1),
(4, 'Electricity Tokens', 'Electricity Tokens', 1);

-- --------------------------------------------------------

--
-- Table structure for table `transaction`
--

CREATE TABLE `transaction` (
  `id` bigint(20) NOT NULL,
  `profile_id` bigint(20) NOT NULL,
  `amount` float NOT NULL DEFAULT '0',
  `reference_id` varchar(100) NOT NULL,
  `reference_type_id` int(11) NOT NULL,
  `transaction_reference_type_id` int(11) NOT NULL,
  `source` varchar(65) NOT NULL,
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_callback`
--

CREATE TABLE `transaction_callback` (
  `id` bigint(20) NOT NULL,
  `transaction_id` bigint(20) NOT NULL,
  `response_code` varchar(100) DEFAULT NULL,
  `response_description` varchar(300) DEFAULT NULL,
  `narration` varchar(200) DEFAULT NULL,
  `receipt_number` varchar(150) DEFAULT NULL,
  `retries` int(11) NOT NULL DEFAULT '0',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attribution`
--
ALTER TABLE `attribution`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq` (`profile_id`,`keyword_id`,`transaction_id`) USING BTREE,
  ADD KEY `profile_id` (`profile_id`),
  ADD KEY `keyword_id` (`keyword_id`);

--
-- Indexes for table `blacklist`
--
ALTER TABLE `blacklist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `msisdn` (`msisdn`),
  ADD KEY `reason` (`reason`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `code`
--
ALTER TABLE `code`
  ADD PRIMARY KEY (`code_id`),
  ADD KEY `distributor_id` (`distributor_id`);

--
-- Indexes for table `distributors`
--
ALTER TABLE `distributors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `partner_name` (`partner_name`),
  ADD KEY `created` (`created`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `entries`
--
ALTER TABLE `entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `inbox_id` (`inbox_id`),
  ADD KEY `status` (`status`),
  ADD KEY `status_desc` (`status_desc`),
  ADD KEY `created` (`created`);

--
-- Indexes for table `entries_redeemed`
--
ALTER TABLE `entries_redeemed`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_id` (`code_id`),
  ADD KEY `entry_id` (`entry_id`),
  ADD KEY `distributor_id` (`distributor_id`);

--
-- Indexes for table `inbox`
--
ALTER TABLE `inbox`
  ADD PRIMARY KEY (`inbox_id`),
  ADD UNIQUE KEY `unique_id` (`unique_id`,`profile_id`),
  ADD KEY `created` (`created`),
  ADD KEY `short_code` (`short_code`),
  ADD KEY `source` (`source`),
  ADD KEY `fk_inbox_profile` (`profile_id`);

--
-- Indexes for table `mechanics`
--
ALTER TABLE `mechanics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reward_id` (`reward_id`,`reward_name`),
  ADD KEY `mechanic_limit` (`mechanic_limit`,`daily_mechanic_limit`),
  ADD KEY `entry_no` (`entry_no`);

--
-- Indexes for table `mechanic_transaction`
--
ALTER TABLE `mechanic_transaction`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `enrty_id` (`entry_id`),
  ADD KEY `mechanic_id` (`mechanic_id`,`created`);

--
-- Indexes for table `mpesa_withdrawal_dlr`
--
ALTER TABLE `mpesa_withdrawal_dlr`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`);

--
-- Indexes for table `outbox`
--
ALTER TABLE `outbox`
  ADD PRIMARY KEY (`outbox_id`),
  ADD KEY `profile_id` (`profile_id`,`created_by`,`created`),
  ADD KEY `message` (`message`(767)),
  ADD KEY `nos` (`nos`);

--
-- Indexes for table `outbox_dlr`
--
ALTER TABLE `outbox_dlr`
  ADD PRIMARY KEY (`id`),
  ADD KEY `outbox_id` (`outbox_id`);

--
-- Indexes for table `paybill_balance`
--
ALTER TABLE `paybill_balance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `paybill` (`paybill`);

--
-- Indexes for table `profile`
--
ALTER TABLE `profile`
  ADD PRIMARY KEY (`profile_id`),
  ADD UNIQUE KEY `msisdn` (`msisdn`),
  ADD KEY `created` (`created`),
  ADD KEY `network` (`network`,`first_name`);

--
-- Indexes for table `profile_accounts`
--
ALTER TABLE `profile_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `profile_id` (`profile_id`);

--
-- Indexes for table `profile_attribute`
--
ALTER TABLE `profile_attribute`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `profile_id` (`profile_id`),
  ADD KEY `created` (`created`),
  ADD KEY `frequency_of_use` (`frequency_of_use`,`origin`),
  ADD KEY `status` (`status`),
  ADD KEY `points` (`points`),
  ADD KEY `total_tickets` (`total_entries`),
  ADD KEY `first_won_date` (`first_won_date`),
  ADD KEY `last_won_date` (`last_won_date`),
  ADD KEY `first_withdrawal_date` (`first_withdrawal_date`,`last_withdrawal_date`),
  ADD KEY `valid_entries` (`valid_entries`),
  ADD KEY `invalid_entries` (`invalid_entries`),
  ADD KEY `total_referrals` (`total_referrals`),
  ADD KEY `successful_referrals` (`successful_referrals`),
  ADD KEY `location` (`location`),
  ADD KEY `learn_level` (`learn_level`,`total_answered_question`,`correctly_answered_question`);

--
-- Indexes for table `profile_balance`
--
ALTER TABLE `profile_balance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `profile_id` (`profile_id`),
  ADD KEY `balance` (`balance`),
  ADD KEY `points` (`bonus`),
  ADD KEY `created` (`created`);

--
-- Indexes for table `profile_login`
--
ALTER TABLE `profile_login`
  ADD PRIMARY KEY (`id`),
  ADD KEY `profile_id` (`profile_id`),
  ADD KEY `verify_code` (`verify_code`),
  ADD KEY `failed_attempts` (`failed_attempts`),
  ADD KEY `cumlative_failed_attempts` (`cumlative_failed_attempts`),
  ADD KEY `last_failed_attempt` (`last_failed_attempt`),
  ADD KEY `created` (`created`),
  ADD KEY `successful_attempts` (`successful_attempts`);

--
-- Indexes for table `profile_login_activity`
--
ALTER TABLE `profile_login_activity`
  ADD PRIMARY KEY (`login_activity_id`),
  ADD UNIQUE KEY `profile_id` (`profile_id`),
  ADD KEY `created` (`created`);

--
-- Indexes for table `references_type`
--
ALTER TABLE `references_type`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `regions`
--
ALTER TABLE `regions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `region_name` (`region_name`);

--
-- Indexes for table `rewards`
--
ALTER TABLE `rewards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reward_name` (`reward_name`);

--
-- Indexes for table `transaction`
--
ALTER TABLE `transaction`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `service_id` (`reference_id`,`reference_type_id`,`transaction_reference_type_id`),
  ADD KEY `created` (`created`),
  ADD KEY `fk_transaction_references` (`transaction_reference_type_id`),
  ADD KEY `fk_trans_reference_id` (`reference_type_id`),
  ADD KEY `profile_id` (`profile_id`),
  ADD KEY `source` (`source`),
  ADD KEY `amount` (`amount`);

--
-- Indexes for table `transaction_callback`
--
ALTER TABLE `transaction_callback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`,`transaction_id`),
  ADD KEY `created` (`created`),
  ADD KEY `retries` (`retries`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attribution`
--
ALTER TABLE `attribution`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `blacklist`
--
ALTER TABLE `blacklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
--
-- AUTO_INCREMENT for table `code`
--
ALTER TABLE `code`
  MODIFY `code_id` bigint(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `distributors`
--
ALTER TABLE `distributors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;
--
-- AUTO_INCREMENT for table `entries`
--
ALTER TABLE `entries`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `entries_redeemed`
--
ALTER TABLE `entries_redeemed`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `inbox`
--
ALTER TABLE `inbox`
  MODIFY `inbox_id` bigint(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `mechanics`
--
ALTER TABLE `mechanics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
--
-- AUTO_INCREMENT for table `mechanic_transaction`
--
ALTER TABLE `mechanic_transaction`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `mpesa_withdrawal_dlr`
--
ALTER TABLE `mpesa_withdrawal_dlr`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `outbox`
--
ALTER TABLE `outbox`
  MODIFY `outbox_id` bigint(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `outbox_dlr`
--
ALTER TABLE `outbox_dlr`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `paybill_balance`
--
ALTER TABLE `paybill_balance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `profile`
--
ALTER TABLE `profile`
  MODIFY `profile_id` bigint(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `profile_accounts`
--
ALTER TABLE `profile_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `profile_attribute`
--
ALTER TABLE `profile_attribute`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `profile_balance`
--
ALTER TABLE `profile_balance`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `profile_login`
--
ALTER TABLE `profile_login`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `profile_login_activity`
--
ALTER TABLE `profile_login_activity`
  MODIFY `login_activity_id` bigint(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `references_type`
--
ALTER TABLE `references_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `regions`
--
ALTER TABLE `regions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
--
-- AUTO_INCREMENT for table `rewards`
--
ALTER TABLE `rewards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
--
-- AUTO_INCREMENT for table `transaction`
--
ALTER TABLE `transaction`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
