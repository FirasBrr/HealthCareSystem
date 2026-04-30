# 🏥 MedicalApp - Healthcare Appointment Management System

![Symfony Version](https://img.shields.io/badge/Symfony-6.x-000000?logo=symfony)
![PHP Version](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php)
![Database](https://img.shields.io/badge/Database-TiDB%20Cloud-4479A1?logo=mysql)
![Deployed on](https://img.shields.io/badge/Deployed%20on-Render-46E3B7?logo=render)

## 📋 Overview

MedicalApp is a comprehensive healthcare management platform that connects patients with doctors. It allows patients to book appointments, view prescriptions, and manage their health records, while doctors can manage their schedules, view appointments, and issue prescriptions.

**🔗 Live Demo:** [https://healthcaresystem-1-ndad.onrender.com](https://healthcaresystem-1-ndad.onrender.com)

### Demo Credentials

| Role | Email             | Password |
|------|-------------------|----------|
| 👨‍💼 Admin | admin@test123.com | test123  |
| 👤 Patient | patient@mail.com  | test123  |
| 👨‍⚕️ Doctor | doctor2@mail.com  | test123  |

---

## ✨ Features

### 👤 Patient Features
- ✅ Book appointments with doctors
- ✅ View appointment history with filters
- ✅ Download prescriptions as PDF
- ✅ Find doctors by specialty
- ✅ View doctor profiles and availability

### 👨‍⚕️ Doctor Features
- ✅ Interactive calendar for availability management
- ✅ Click & drag to create time slots
- ✅ Confirm or cancel appointments
- ✅ Upload prescriptions for patients
- ✅ View patient history and profiles

### 👨‍💼 Admin Features
- ✅ Complete user management (doctors/patients)
- ✅ Appointment oversight
- ✅ System monitoring and statistics
- ✅ CRUD operations for all entities

---

## 🖼️ Screenshots

### Landing Page
![Landing Page](screenshots/homepage.png)

### Authentication
| Register | Login |
|----------|-------|
| ![Register](screenshots/register.png) | ![Login](screenshots/login.png) |
| ![Role Selection](screenshots/register-role.png) | |

### Patient Panel
| Dashboard | Appointments |
|-----------|--------------|
| ![Patient Dashboard](screenshots/patient-dashboard.png) | ![Patient Appointments](screenshots/patient-appointments.png) |

| Prescriptions | Find Doctors |
|---------------|--------------|
| ![Patient Prescriptions](screenshots/patient-prescriptions.png) | ![Find Doctors](screenshots/patient-doctors.png) |

### Doctor Panel
| Dashboard | Calendar |
|-----------|----------|
| ![Doctor Dashboard](screenshots/doctor-dashboard.png) | ![Doctor Calendar](screenshots/doctor-calendar.png) |

| Appointments | Prescriptions |
|--------------|---------------|
| ![Doctor Appointments](screenshots/doctor-appointments.png) | ![Doctor Prescriptions](screenshots/doctor-prescriptions.png) |

| Patients | Availability Booking |
|----------|---------------------|
| ![Doctor Patients](screenshots/doctor-patients.png) | ![Booking View](screenshots/doctor-availability-booking.png) |

### Admin Panel
| Dashboard | Appointments |
|-----------|--------------|
| ![Admin Dashboard](screenshots/admin-dashboard.png) | ![Admin Appointments](screenshots/admin-appointments.png) |

| Doctors Management | Patients Management |
|--------------------|---------------------|
| ![Admin Doctors](screenshots/admin-doctors.png) | ![Admin Patients](screenshots/admin-patients.png) |

---

## 🛠️ Tech Stack

| Category | Technology |
|----------|------------|
| **Backend** | Symfony 6 (PHP 8.2) |
| **Database** | TiDB Cloud (MySQL-compatible) |
| **Frontend** | Bootstrap 5, Twig, FullCalendar.js |
| **Calendar** | FullCalendar.io |
| **Icons** | Font Awesome 6 |
| **Deployment** | Render.com |
| **Container** | Docker |
| **Version Control** | Git & GitHub |

---

## 🚀 Quick Start

### Prerequisites
- PHP 8.2+
- Composer
- MySQL / TiDB Cloud account
- Docker (optional)

### Local Installation

1. **Clone the repository**
```bash
git clone https://github.com/FirasBrr/HealthCareSystem.git
cd HealthCareSystem
