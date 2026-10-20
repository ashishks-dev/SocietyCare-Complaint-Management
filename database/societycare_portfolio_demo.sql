-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 20, 2026 at 10:12 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `societycare_portfolio_demo`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `name`, `email`, `password`) VALUES
(1, 'Demo Administrator', 'admin@example.com', '$2y$12$/6.V8Zprf8/A1piVkXE/k.wocUX2TMc3SuhC2hVwJw7f7x.RYZonG');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`) VALUES
(1, 'Water Supply'),
(2, 'Electricity'),
(3, 'Lift'),
(4, 'Parking'),
(5, 'Security'),
(6, 'Cleanliness'),
(7, 'Maintenance'),
(8, 'Others');

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `complaint_id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `issue_started` varchar(50) DEFAULT NULL,
  `priority` enum('Low','Medium','High') DEFAULT 'Medium',
  `photo` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Assigned','In Progress','Resolved','Closed') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_remark` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`complaint_id`, `resident_id`, `category_id`, `title`, `description`, `location`, `issue_started`, `priority`, `photo`, `status`, `created_at`, `admin_remark`) VALUES
(1, 2, 1, 'Low Water Pressure in Block A', 'Residents of Block A have been experiencing low water pressure since yesterday morning. The water supply is insufficient for daily household activities. Kindly arrange an inspection and resolve the issue as soon as possible.', 'Block A, Residential Building', 'Today', 'High', NULL, 'Closed', '2026-10-14 16:45:21', 'The water supply system in Block A has been checked after the repair. Normal water pressure has been confirmed, and the maintenance work is complete. The complaint is now officially closed.'),
(2, 1, 2, 'Streetlights Not Working in Block B', 'Several streetlights near the main entrance of Block B have stopped working since yesterday evening. The area becomes very dark at night, making it difficult for residents to walk safely. Kindly arrange for the maintenance team to inspect and repair the faulty lights.', 'Block B, Main Entrance', 'Yesterday', 'High', NULL, 'Resolved', '2026-10-14 17:52:57', 'The electrical maintenance team inspected the streetlights near the main entrance of Block B and identified faulty wiring. The damaged wiring has been repaired, and all affected streetlights are now functioning properly. The issue has been resolved.'),
(3, 2, 3, 'Lift Making Unusual Noise', 'The passenger lift in Block A has been making unusual grinding noises while moving between floors for the past two days. Residents are concerned about its safety. Kindly arrange a technical inspection and necessary maintenance.', 'Block A, Passenger Lift', '2-3 Days Ago', 'High', NULL, 'In Progress', '2026-10-14 18:24:17', 'The lift maintenance technician has been assigned to inspect the passenger lift in Block A. Residents have been advised not to use the lift until the safety inspection is completed.'),
(4, 1, 6, 'Garbage Collection Delayed in Block B', 'Garbage collection has not been carried out in Block B for the past two days. Waste is accumulating near the designated collection area, causing an unpleasant smell and attracting insects. Kindly arrange immediate collection and ensure regular waste disposal.', 'Block B, Garbage Collection Area', '2-3 Days Ago', 'Medium', NULL, 'Pending', '2026-10-14 18:30:23', NULL),
(5, 2, 4, 'Unauthorized Vehicle Parking in Block A', 'An unidentified vehicle has been occupying a reserved parking space in Block A since yesterday. The vehicle does not belong to the resident assigned to this space. Kindly investigate the matter and ensure that parking regulations are followed.', 'Block A, Parking Area', 'Yesterday', 'Medium', NULL, 'Resolved', '2026-10-14 18:35:02', 'The security team identified the vehicle owner and contacted them regarding the unauthorized parking. The vehicle has been moved to its designated parking space, and the affected resident\'s parking space is now available. The issue has been resolved.'),
(6, 1, 5, 'CCTV Camera Not Working Near Block B', 'The CCTV camera installed near the main entrance of Block B has not been functioning since yesterday evening. This camera monitors an important entry point of the residential building. Kindly arrange an inspection and restore the camera\'s functionality as soon as possible.', 'Block B, Main Entrance', 'Yesterday', 'High', NULL, 'Pending', '2026-10-14 18:49:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `complaint_history`
--

CREATE TABLE `complaint_history` (
  `history_id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `remark` text DEFAULT NULL,
  `updated_by` varchar(50) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaint_history`
--

INSERT INTO `complaint_history` (`history_id`, `complaint_id`, `status`, `remark`, `updated_by`, `updated_at`) VALUES
(1, 1, 'Pending', 'Complaint submitted.', 'Resident', '2026-10-14 16:45:21'),
(2, 1, 'In Progress', 'The maintenance team has been assigned to inspect the water supply system in Block A. An inspection is scheduled to identify the cause of low water pressure.', 'Admin', '2026-10-14 17:14:33'),
(3, 1, 'Resolved', 'The maintenance team inspected the water supply system in Block A and identified a faulty pressure valve. The valve has been repaired, and normal water pressure has been restored. The issue is now resolved.', 'Admin', '2026-10-14 17:18:27'),
(4, 1, 'Closed', 'The water supply system in Block A has been checked after the repair. Normal water pressure has been confirmed, and the maintenance work is complete. The complaint is now officially closed.', 'Admin', '2026-10-14 17:23:23'),
(5, 2, 'Pending', 'Complaint submitted.', 'Resident', '2026-10-14 17:52:57'),
(6, 2, 'In Progress', 'The electrical maintenance team has been assigned to inspect the streetlights near the main entrance of Block B. The team will identify the fault and carry out the necessary repairs.', 'Admin', '2026-10-14 18:18:26'),
(7, 2, 'Resolved', 'The electrical maintenance team inspected the streetlights near the main entrance of Block B and identified faulty wiring. The damaged wiring has been repaired, and all affected streetlights are now functioning properly. The issue has been resolved.', 'Admin', '2026-10-14 18:20:25'),
(8, 3, 'Pending', 'Complaint submitted.', 'Resident', '2026-10-14 18:24:17'),
(9, 3, 'In Progress', 'The lift maintenance technician has been assigned to inspect the passenger lift in Block A. Residents have been advised not to use the lift until the safety inspection is completed.', 'Admin', '2026-10-14 18:27:28'),
(10, 4, 'Pending', 'Complaint submitted.', 'Resident', '2026-10-14 18:30:23'),
(11, 5, 'Pending', 'Complaint submitted.', 'Resident', '2026-10-14 18:35:02'),
(12, 5, 'In Progress', 'The society security team has been assigned to investigate the unauthorized vehicle parked in Block A. The team is checking parking records and contacting the vehicle owner to resolve the issue.', 'Admin', '2026-10-14 18:37:21'),
(13, 5, 'Resolved', 'The security team identified the vehicle owner and contacted them regarding the unauthorized parking. The vehicle has been moved to its designated parking space, and the affected resident\'s parking space is now available. The issue has been resolved.', 'Admin', '2026-10-14 18:46:10'),
(14, 6, 'Pending', 'Complaint submitted.', 'Resident', '2026-10-14 18:49:35');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` between 1 and 5),
  `comments` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedback_id`, `complaint_id`, `resident_id`, `rating`, `comments`, `submitted_at`) VALUES
(1, 1, 2, 5, 'The maintenance team responded promptly and successfully resolved the water pressure issue. The complaint tracking system kept me informed throughout the process. Satisfied with the service.', '2026-10-14 17:35:50');

-- --------------------------------------------------------

--
-- Table structure for table `residents`
--

CREATE TABLE `residents` (
  `resident_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `password` varchar(255) NOT NULL,
  `house_number` varchar(50) NOT NULL,
  `block` varchar(50) DEFAULT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Active','Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `residents`
--

INSERT INTO `residents` (`resident_id`, `full_name`, `email`, `mobile`, `password`, `house_number`, `block`, `registration_date`, `status`) VALUES
(1, 'Demo Resident One', 'resident1@example.com', '0000000000', '$2y$12$v9qd9ohJ.d.hrILDteI.nuX459JA9ihnHn6ckvR1OlxsxVjNgJXQC', 'A-101', 'A', '2026-06-30 12:12:14', 'Active'),
(2, 'Demo Resident Two', 'resident2@example.com', '0000000000', '$2y$12$Up0MFdaGNgcoTcly2xO1VuOok7YWGUw7owkzX8mCWoc2P./ehbNU2', 'A-102', 'A', '2026-07-04 14:38:15', 'Active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`complaint_id`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `complaint_history`
--
ALTER TABLE `complaint_history`
  ADD PRIMARY KEY (`history_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD UNIQUE KEY `uq_feedback_complaint_id` (`complaint_id`),
  ADD KEY `complaint_id` (`complaint_id`),
  ADD KEY `resident_id` (`resident_id`);

--
-- Indexes for table `residents`
--
ALTER TABLE `residents`
  ADD PRIMARY KEY (`resident_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `complaint_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `complaint_history`
--
ALTER TABLE `complaint_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `residents`
--
ALTER TABLE `residents`
  MODIFY `resident_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`resident_id`),
  ADD CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`),
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`resident_id`);
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
