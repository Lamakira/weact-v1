# WEACT

**Platform connecting Faces (talents) with Producers for creative missions in Benin and West Africa.**

WEACT is a marketplace that connects creative talents (Faces) such as actors, influencers, models, and content creators with Producers (agencies and individuals) looking for talent for their projects.

## Tech Stack

### Backend
- **PHP 8.2+** with **Laravel 12**
- **MySQL 8.0+** database
- **Laravel Sanctum** for API authentication

### Frontend
- **Vue 3** with Composition API
- **TypeScript** in strict mode
- **Pinia** for state management
- **Vue Router** for routing
- **Tailwind CSS 4.1** for styling
- **Vite 6+** for build tooling

## Prerequisites

Before you begin, ensure you have the following installed:

- **PHP** >= 8.2
- **Composer** >= 2.x
- **Node.js** >= 20.x
- **npm** >= 10.x
- **MySQL** >= 8.0

## Installation

### 1. Clone the repository

```bash
git clone <repository-url>
cd weact
```

### 2. Install dependencies

```bash
# Install all dependencies (backend + frontend)
npm run install:all

# Or install separately:
cd backend && composer install
cd ../frontend && npm install
```

### 3. Configure environment

```bash
# Backend
cp backend/.env.example backend/.env
# Edit backend/.env with your database credentials

# Frontend
cp frontend/.env.example frontend/.env
# Edit frontend/.env if needed (defaults should work for local dev)
```

### 4. Setup database

```bash
# Create the database
mysql -u root -p -e "CREATE DATABASE weact;"

# Run migrations
cd backend && php artisan migrate
```

### 5. Generate application key

```bash
cd backend && php artisan key:generate
```

## Development

### Start development servers

```bash
# Start both backend and frontend concurrently
npm run dev

# Or start individually:
npm run dev:backend   # Laravel on http://localhost:8000
npm run dev:frontend  # Vue on http://localhost:5173
```

### Available scripts

| Command | Description |
|---------|-------------|
| `npm run dev` | Start both backend and frontend servers |
| `npm run dev:backend` | Start Laravel server only |
| `npm run dev:frontend` | Start Vue dev server only |
| `npm run build` | Build frontend for production |
| `npm run install:all` | Install all dependencies |
| `npm run lint` | Run frontend linter |
| `npm run test:backend` | Run Laravel tests |
| `npm run test:frontend` | Run Vue tests |

## Project Structure

```
weact/
├── backend/                 # Laravel 12 REST API
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/  # Versioned API controllers
│   │   │   ├── Requests/            # Form Request validation
│   │   │   └── Resources/           # API Resources
│   │   ├── Models/                  # Eloquent models
│   │   ├── Policies/                # Authorization policies
│   │   └── Services/                # Business logic
│   ├── database/migrations/         # Database migrations
│   ├── routes/api.php               # API routes
│   └── ...
│
├── frontend/                # Vue 3 SPA
│   ├── src/
│   │   ├── assets/          # CSS and static assets
│   │   ├── components/ui/   # Shared UI components
│   │   ├── composables/     # Vue composables
│   │   ├── features/        # Feature modules
│   │   ├── layouts/         # Page layouts
│   │   ├── pages/           # Route-level views
│   │   ├── router/          # Vue Router config
│   │   ├── services/        # API services
│   │   ├── stores/          # Pinia stores
│   │   ├── types/           # TypeScript types
│   │   └── utils/           # Utility functions
│   └── ...
│
├── docs/                    # Documentation
├── package.json             # Root package with dev scripts
├── .gitignore              # Git ignore rules
└── README.md               # This file
```

## API Endpoints

Base URL: `http://localhost:8000/api/v1`

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/health` | GET | API health check |
| `/user` | GET | Get authenticated user (requires auth) |

*More endpoints will be added as features are implemented.*

## Design System

The frontend uses a custom design system based on Tailwind CSS:

- **Primary Color**: `#198496` (teal)
- **Font**: Inter

Color palette available as Tailwind classes:
- `bg-primary-50` to `bg-primary-900`
- `text-primary-50` to `text-primary-900`

## Testing

```bash
# Run backend tests
npm run test:backend

# Run frontend tests
npm run test:frontend
```

## Code Style

### Backend (Laravel)
- PSR-12 coding standard
- `declare(strict_types=1)` in all PHP files
- Form Requests for validation
- API Resources for responses

### Frontend (Vue)
- Composition API with `<script setup lang="ts">`
- TypeScript strict mode
- ESLint + Prettier for formatting

## Contributing

1. Create a feature branch: `git checkout -b feature/your-feature`
2. Make your changes
3. Run tests and linting
4. Submit a pull request

## License

MIT

---

**WEACT** - Connecting Faces with Producers

