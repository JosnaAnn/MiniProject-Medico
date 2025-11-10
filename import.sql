-- Create database if not exists
CREATE DATABASE IF NOT EXISTS miniproject;
USE miniproject;

-- Hospitals table
CREATE TABLE hospitals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    contact VARCHAR(10) NOT NULL,
    hospital_code VARCHAR(5) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users table (for admins)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'superadmin') DEFAULT 'admin',
    hospital_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
);

-- Patients table
CREATE TABLE patients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_uid VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    age INT NOT NULL,
    gender ENUM('M', 'F', 'O') NOT NULL,
    phone VARCHAR(10) NOT NULL,
    place VARCHAR(255) NOT NULL,
    department VARCHAR(50) NOT NULL,
    token INT NOT NULL,
    token_date DATE NOT NULL,
    hospital_id INT NOT NULL,
    payment_status ENUM('pending', 'paid', 'free') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
);

-- Patient counter table (for generating UIDs)
CREATE TABLE patient_counter (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT UNIQUE NOT NULL,
    last_number INT DEFAULT 0,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
);

-- Insert default superadmin (password should be hashed in production)
INSERT INTO users (username, password, role) 
VALUES ('superadmin', 'super123', 'superadmin');

-- Create indexes for better performance
CREATE INDEX idx_patient_token ON patients(token_date, department, hospital_id);
CREATE INDEX idx_patient_hospital ON patients(hospital_id);
CREATE INDEX idx_user_hospital ON users(hospital_id);