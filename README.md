# 🌾 AgroSafeAI
## 🤖 AI-Powered Smart Agriculture Decision Support System

📘 **Course:** ITEP 308 – System Integration and Architecture I  
🗓 **Academic Term:** First Semester, Academic Year 2025–2026  
🏫 **Section:** 3WMAD-1  

---

## 📌 Project Overview

**AgroSafeAI** is a  web-based smart agriculture decision support system designed to help farmers 👨‍🌾 and agricultural administrators make **accurate, data-driven decisions**.

The system focuses on:
- 🌱 Crop disease diagnosis
- 💊 AI-trained treatment planning
- 📊 Smart prediction and analysis
- ⏱ Real-time insights using live weather and market data

By integrating machine learning,  real-time APIs, and  database-driven systems, AgroSafeAI aims to reduce crop loss, optimize resource usage, and support sustainable farming practices.

---

## 🎯 Objectives

- 🔍 Detect potential crop diseases early
- 🧠 Generate AI-based treatment plans
- 📈 Provide real-time predictions and smart insights
- 💰 Support cost-aware and ROI-driven decisions
- 🧩 Demonstrate full system integration using modern web technologies

---

## 🧠 Key Features

### 🌱 Disease Diagnosis
- AI-based crop disease classification
- Early detection to prevent severe crop damage

### 💊 AI-Trained Treatment Plan
- Automatic generation of treatment recommendations
- Fungicide suggestions based on trained AI models

### 📊 Smart Prediction and Analysis
- Water usage prediction
- Risk-aware decision support
- AI-driven insights for better farming decisions

### 🌦 Real-Time Environmental Monitoring
- Live weather data integration using Weather API
- Supports disease and irrigation predictions

### 💰 Smart ROI and Market Insights
-  Real-time market and currency data
-  Cost-aware treatment planning
-  Smart ROI-based decision support

### 👤 User and Admin System
-  Secure user authentication and sessions
-  Admin panel for system monitoring and model retraining

---

## 🏗 System Architecture Overview

AgroSafeAI follows a layered system architecture :

1. 🖥 **Frontend (User Interface)**
   - HTML, CSS, JavaScript, Bootstrap
   - Responsive and user-friendly design

2. ⚙ **Backend Application**
   - PHP 8.x
   - Handles business logic and request processing

3. 🤖 **Machine Learning Layer**
   - PHP-ML (PHP-AI)
   - Trained models stored as `.phpml` files

4. 🗄 **Database Layer**
   - MySQL (via XAMPP)
   - Stores users, sessions, prediction history, and logs

5. 🌐 **External APIs**
   -  Weather API for real-time environmental data
   -  Market and Currency API for live price and trade data

---

## 🛠 Technologies Used

### ⚙ Backend and Server
- PHP 8.x
- XAMPP (Apache + MySQL)
- Composer (dependency management)

### 🤖 Machine Learning
- PHP-ML (PHP-AI)
- Trained AI models:
  - 🌱 Disease Classifier
  - 💊 Fungicide Predictor
  - 💧 Water Predictor

### 🗄 Database and Storage
- MySQL
- 📄 CSV datasets for ML training

### 🎨 Frontend
- HTML
- CSS
- JavaScript
- Bootstrap

### 🌐 APIs
- 🌦 Weather API
- 💱 Market and Currency API

---

## Installation and Setup Instructions

### 1. Clone the Repository
```bash
git clone https://github.com/PaulPaolo2929/AgroSafeAI.git
``` 

### 2. Setup XAMPP

- Install XAMPP
- Start Apache and MySQL
- Move the project folder to:

```bash
htdocs/
``` 

### 3. Install Dependencies
Make sure Composer is installed, then run:

composer install

4. Database Setup

Open phpMyAdmin

Create a new MySQL database

Import the SQL file (if provided)

Update database credentials in:

includes/config.php

5. Train Machine Learning Models

Run the training script:

php train.php


This will generate .phpml model files inside the models/ directory.

6. Run the System

Open your browser and go to:

http://localhost/AgroSafeAI/

Live Deployment

User Login:
https://agrosafeai.infinityfreeapp.com/index.php

Admin Login:
https://agrosafeai.infinityfreeapp.com/admin/login.php

Presentation and Documentation

Final Presentation (Canva):
https://www.canva.com/design/DAG7Yi5BJSY/8VfjaV3IFT8SyFWz4RcQHA/edit

Final Files and Video (Google Drive):
https://drive.google.com/drive/folders/1Oe1kAQAojBfmmUTwVu4o3IQexz-6OTzM?usp=sharing

Project Members

Paul Paolo A. Mamugay

Kim Andrei Veloria

Mark Jesus Fidelino

Course Information

Course: ITEP 308 – System Integration and Architecture I
Academic Term: First Semester, Academic Year 2025–2026

Future Enhancements

Mobile-friendly Progressive Web App (PWA)

SMS or email alerts

Expanded training datasets

Automated model retraining

Advanced analytics dashboard

Conclusion

AgroSafeAI demonstrates a complete system integration project combining web development, machine learning, real-time APIs, and database systems. It delivers a practical and intelligent solution for modern agriculture while meeting academic and enterprise-level requirements.
