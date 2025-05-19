-- Create database
CREATE DATABASE IF NOT EXISTS spreadsheet_api;
USE spreadsheet_api;

-- Create spreadsheets table
CREATE TABLE IF NOT EXISTS spreadsheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    upload_date DATETIME NOT NULL,
    content_json LONGTEXT NOT NULL,
    summary TEXT NOT NULL,
    last_analysis TEXT,
    last_analysis_date DATETIME
);