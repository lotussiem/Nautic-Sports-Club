
# Activities Management Module – Nautical Sports Club

This repository contains the Activities Management Module developed as part of a larger web application for managing a Nautical Sports Club. The module was built using the Symfony framework and allows users to explore, manage, and filter water sports activities with support for image uploads, real-time weather data, and map-based location display.

---

## Project Overview

This module is a standalone part of a full-stack system built to simulate the management needs of a nautical sports club. It supports full CRUD operations on activities, advanced filtering, activity categorization, and dynamic data integrations via external APIs. It follows a modular and maintainable structure using Symfony 6.

---

## Features

- Full CRUD for water sport activities
- Categorization of activities (e.g. kayaking, windsurfing, paddleboarding)
- Search and filter by category, difficulty, and location
- Role-based access control (admin and regular user)
- Real-time weather data for activity locations using the OpenWeatherMap API
- Map integration to display activity locations using Leaflet.js 
- Image upload for each activity using VichUploaderBundle
- Responsive interface with Bootstrap and Twig

---

## Technologies and Tools Used

This module was developed using:

- PHP 8.2+
- Symfony 6
- MySQL 8
- Doctrine ORM
- Twig templating engine
- Bootstrap 5 for front-end design
- Git and GitHub for version control

Third-party integrations:

- OpenWeatherMap API (for live weather information)
- Leaflet.js or Google Maps (for interactive map display)
- VichUploaderBundle (for image upload and management)

---

## External API Integrations

**OpenWeatherMap API**  
This API is used to fetch current weather data (temperature, wind, and condition) based on the location of each activity. It ensures users can check weather conditions before registering for an outdoor event.

**Map API**  
An interactive map is embedded using Leaflet.js or Google Maps to display the geographic location of each activity, improving the user experience and spatial clarity.

**Image Upload**  
Activities support image uploads to display visual previews. Image handling is done through the VichUploaderBundle, which enables smooth upload, storage, and display within the UI.

---

## About This Repository

This repository contains only my personal contribution to a larger group project. I was fully responsible for the Activities Management Module. My work included:

- Designing and implementing the activity and category entities
- Building all related controllers, forms, views, and validation logic
- Integrating the Weather API and Map API
- Implementing image upload functionality
- Creating a responsive and user-friendly front-end
- Implementing search bars

Other modules from the complete project (not included here) involve:

- Authentication and profile management
- Equipment rental system
- Event registration
- Payment and feedback functionality

---

## Author

Meissa Cherni  
Software Engineering Student  


---

