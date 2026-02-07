# Flight Scheduler

A simple full-stack flight scheduling system built as a learning and portfolio project.
The application demonstrates backend business logic, REST APIs, and a React + TypeScript frontend.

## Features

- View available flights
- Seat allocation by class (first, business, economy)
- Passenger booking and cancellation
- Automatic waitlist handling (FIFO) per class
- Passenger status lookup
- Flight seat map view (per class)
- Simple React frontend consuming a PHP REST API

## Tech Stack

**Backend**
- PHP (built-in development server)
- REST-style API
- File-based state storage (no database)

**Frontend**
- React
- TypeScript
- Vite

## Project Structure

```text
flight_scheduler/
├── backend/
│   ├── public/
│   │   └── index.php
│   └── data/
│       └── state.json
├── frontend/
│   └── src/
│       └── App.tsx
└── README.md


The backend uses file-based storage instead of a database to keep the logic simple and explicit.
