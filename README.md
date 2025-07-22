# Modern CRM - Headless SuiteCRM

A modern, headless CRM using SuiteCRM as the backend API with a React frontend.

## Architecture

- **Backend**: Full SuiteCRM (PHP) with V4.1 REST API
- **Frontend**: React + TypeScript + TailwindCSS + Vite  
- **Database**: MySQL 5.7 with SuiteCRM schema
- **Deployment**: Docker + docker-compose

## Structure

```
├── suitecrm/         # Complete SuiteCRM backend (PHP)
├── frontend/         # React frontend (TypeScript)
├── docker/           # Docker configurations
└── docker-compose.yml # Complete stack orchestration
```

## Quick Start

**One command to rule them all:**

```bash
docker-compose up --build
```

**Then:**
1. **SuiteCRM Setup**: Visit http://localhost:8080/install.php to complete SuiteCRM installation
2. **Frontend**: Visit http://localhost:3000 for the React frontend
3. **Both use the same data and user accounts!**

See **[SETUP.md](./SETUP.md)** for detailed setup instructions.

## Features

- ✅ **Full SuiteCRM Backend** - Complete CRM functionality
- ✅ **Modern React Frontend** - Fast, responsive UI
- ✅ **V4.1 REST API** - Mature, stable API
- ✅ **Session Authentication** - Simple, secure login flow
- ✅ **Docker Setup** - One-command deployment
- ✅ **TypeScript** - Type-safe frontend development

## Services

- **MySQL**: Port 3306 (SuiteCRM database)
- **SuiteCRM**: Port 8080 (PHP backend + admin interface) 
- **Frontend**: Port 3000 (React development server)

## Development

### Frontend Development
```bash
cd frontend
bun install
bun run dev
```

### SuiteCRM Customization
Add customizations to `suitecrm/custom/` folder - never modify core files.

### API Testing
Use the SuiteCRM V4.1 API at http://localhost:8080/service/v4_1/rest.php

## Next Steps

Once running, you can:
- **Backend Admin**: Access full SuiteCRM admin at http://localhost:8080
- **Frontend Dev**: Build custom React features at http://localhost:3000
- **Extend**: Add custom fields and modules using SuiteCRM's extension system
- **Deploy**: Use same Docker setup for production

## Documentation

- **[SETUP.md](./SETUP.md)** - Step-by-step setup guide
- **[Phase 1.5 Docs](.docs/phase-1.5.md)** - Headless architecture details



# Headless SuiteCRM Setup Guide

## 🎯 Complete Setup in 3 Steps

### Step 1: Start the Stack
```bash
docker-compose up --build
```

This will start:
- MySQL database (port 3306)
- SuiteCRM backend (port 8080) 
- React frontend (port 3000)

### Step 2: Install SuiteCRM

1. **Visit**: http://localhost:8080/install.php
2. **Database Configuration**:
   - Database Type: `MySQL`
   - Host Name: `mysql`
   - Database Name: `suitecrm`
   - Database User Name: `suitecrm`
   - Database Password: `suitecrm`
3. **Admin Account**: Create your admin user
4. **Complete Installation**

### Step 3: Use Your Headless CRM

The SuiteCRM V4.1 REST API is enabled by default - no additional configuration needed!

- **SuiteCRM Admin**: http://localhost:8080 (full CRM backend)
- **React Frontend**: http://localhost:3000 (modern UI)
- **API Endpoint**: `/service/v4_1/rest.php`

## ✅ Test Your Setup

1. **Backend**: Login to SuiteCRM at http://localhost:8080 with your admin account
2. **Frontend**: Login to React app at http://localhost:3000 with same credentials
3. **API**: Both interfaces use the same data and user accounts

## 🚨 Troubleshooting

**SuiteCRM Installation Issues:**
- Make sure MySQL is healthy: `docker-compose ps`
- Check logs: `docker-compose logs suitecrm`

**Frontend Can't Connect:**
- Verify SuiteCRM is accessible at http://localhost:8080
- Check that user credentials are correct
- Check browser console for CORS errors

**CORS Errors:**
- CORS is configured in `docker/suitecrm/apache-config.conf`
- Restart containers after any Apache config changes

**Database Issues:**
- Check MySQL logs: `docker-compose logs mysql`
- Ensure database credentials match in docker-compose.yml

## 🔧 Development

**Frontend Development:**
```bash
cd frontend
bun install
bun run dev  # Development server with hot reload
```

**SuiteCRM Customization:**
- Add custom modules to `suitecrm/custom/modules/`
- Add custom fields to `suitecrm/custom/Extension/modules/`
- Always use `custom/` folder, never modify core files
- Run Quick Repair after custom changes: Admin → Repair → Quick Repair and Rebuild

**API Testing:**
```bash
# Test login
curl -X POST http://localhost:8080/service/v4_1/rest.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "method=login&input_type=JSON&response_type=JSON&rest_data={\"user_auth\":{\"user_name\":\"admin\",\"password\":\"your_password\"}}"
```

**Database Access:**
```bash
docker exec -it suitecrm-mysql mysql -usuitecrm -psuitecrm suitecrm
```

## 🚀 Production Deployment

This same Docker setup can be used in production:

1. **Environment Variables**: Update database passwords and URLs
2. **SSL/HTTPS**: Add reverse proxy (nginx, Traefik, etc.)
3. **Volumes**: Ensure data persistence with proper Docker volumes
4. **Backups**: Regular MySQL backups of the `suitecrm` database

## 📚 Next Steps

- **Custom Fields**: Add AI scoring field using SuiteCRM's Studio
- **Custom Modules**: Create new modules for software sales workflow
- **Frontend Features**: Build modern UI components in React
- **API Integration**: Connect to external services (email, AI, etc.)